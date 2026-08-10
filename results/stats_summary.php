<?php

/**
 * Aggregate telemetry statistics.
 *
 * This computes a summary of a period once and caches it, rather than querying
 * on every page view. That is what makes a public report safe to expose: the
 * page reads a handful of numbers from a file, so the cost of serving it does
 * not grow with the size of the table and cannot be used to make the database
 * do work on demand.
 *
 * Everything is derived in PHP rather than in SQL. Medians and percentiles have
 * no portable spelling across the four supported backends — PostgreSQL and
 * MSSQL have percentile_cont, MySQL needs window functions, SQLite has neither
 * — and the measurement columns are declared `text` everywhere, so aggregating
 * them in SQL means either a cast that a single malformed row can abort or a
 * silent string comparison that answers the wrong question.
 */

require_once __DIR__.'/telemetry_db.php';

/**
 * Groups smaller than this are not reported on their own.
 *
 * A country line covering three tests is close to naming a person, so anything
 * below the threshold is folded into a single "Other" row, and that row is
 * itself dropped unless it clears the threshold too.
 *
 * This hides how the small groups are made up, not that they exist: with the
 * total shown, their combined size is still the total minus the visible rows,
 * and where only one group was withheld that is its exact size. What it does
 * prevent is reporting a measurement for a group too small to report one for.
 */
const STATS_PRIVACY_THRESHOLD = 100;

/** Percentiles are resolved to this many Mbit/s. */
const STATS_SPEED_BUCKET = 1.0;

/** ...and this many milliseconds. */
const STATS_LATENCY_BUCKET = 0.1;

/**
 * A running total plus a sparse histogram.
 *
 * The histogram is what keeps this usable on a shared host: holding every
 * measurement to sort it would cost hundreds of megabytes on a busy instance,
 * while counting into buckets is bounded by the number of distinct buckets, so
 * a million tests collapse into a few thousand integers. The mean is summed
 * exactly; only the percentiles carry the bucket's resolution.
 *
 * @return array
 */
function statsNewSeries()
{
    return ['n' => 0, 'sum' => 0.0, 'hist' => []];
}

/**
 * @param array      $series
 * @param float|null $value
 * @param float      $bucket
 *
 * @return void
 */
function statsAdd(array &$series, $value, $bucket)
{
    if (null === $value) {
        return;
    }

    ++$series['n'];
    $series['sum'] += $value;

    $index = (int) floor($value / $bucket);
    if (isset($series['hist'][$index])) {
        ++$series['hist'][$index];
    } else {
        $series['hist'][$index] = 1;
    }
}

/**
 * Reduces a series to the figures that get reported.
 *
 * @param array $series
 * @param float $bucket
 * @param int[] $percentiles
 *
 * @return array|null
 */
function statsSummarise(array $series, $bucket, array $percentiles = [10, 25, 50, 75, 90])
{
    if (0 === $series['n']) {
        return null;
    }

    $out = [
        'tests' => $series['n'],
        'mean' => $series['sum'] / $series['n'],
    ];

    $hist = $series['hist'];
    ksort($hist);

    foreach ($percentiles as $p) {
        // Nearest-rank: the smallest bucket whose cumulative count reaches the
        // requested share. Interpolating would suggest a precision the bucket
        // width does not have.
        $target = $p / 100 * $series['n'];
        $seen = 0;
        $value = 0.0;
        foreach ($hist as $index => $count) {
            $seen += $count;
            if ($seen >= $target) {
                $value = $index * $bucket;

                break;
            }
        }
        $out['p'.$p] = $value;
    }

    return $out;
}

/**
 * Keeps a group name to text that can be encoded.
 *
 * Client and ISP strings are stored exactly as they arrived, so a group name
 * derived from one can hold any byte sequence. json_encode() refuses invalid
 * UTF-8 outright, which would leave the summary uncacheable, so anything that
 * is not valid is reported as unknown rather than carried through.
 *
 * @param string $name
 *
 * @return string
 */
function statsSafeName($name)
{
    // //u makes the match fail on invalid UTF-8, which is the cheapest way to
    // test a string for it.
    return preg_match('//u', $name) ? $name : 'Unknown';
}

/**
 * Names the kind of client a user agent belongs to.
 *
 * Deliberately coarse. The user agent is chosen by whoever sends it, so this is
 * a description of what clients claim to be, not a measurement; a handful of
 * buckets can be read that way honestly, while a long tail of exact strings
 * invites treating it as fact. The raw value is never reported.
 *
 * @param string $ua
 *
 * @return string
 */
function statsClassifyClient($ua)
{
    $ua = trim((string) $ua);
    if ('' === $ua) {
        return 'Unknown';
    }

    // The Rust port installs under the same binary name as the Go client, so
    // the longer prefix has to be tested first.
    if (0 === stripos($ua, 'librespeed-cli-rust/')) {
        return 'LibreSpeed CLI (Rust)';
    }
    if (0 === stripos($ua, 'librespeed-cli/')) {
        return 'LibreSpeed CLI (Go)';
    }
    if (false !== stripos($ua, 'Dalvik') || false !== stripos($ua, 'okhttp')) {
        return 'Android app';
    }
    if (0 === stripos($ua, 'Mozilla/')) {
        return 'Web browser';
    }

    return 'Other';
}

/**
 * Names the address family without disclosing the address.
 *
 * @param string $ip
 *
 * @return string
 */
function statsAddressFamily($ip)
{
    $ip = trim((string) $ip);
    if ('' === $ip || '0.0.0.0' === $ip) {
        // What a deployment with redact_ip_addresses stores. Reporting it as
        // IPv4 would invent a fact.
        return 'Unknown';
    }

    return false === strpos($ip, ':') ? 'IPv4' : 'IPv6';
}

/**
 * Digs the country out of the stored ISP information.
 *
 * There is no country column: the API path leaves it in rawIspInfo, and the
 * offline database path only glues it onto the end of processedString.
 *
 * @param string $ispinfo
 *
 * @return string
 */
function statsCountry($ispinfo)
{
    $decoded = json_decode((string) $ispinfo, true);
    if (!is_array($decoded)) {
        return 'Unknown';
    }

    if (isset($decoded['rawIspInfo']['country']) && is_string($decoded['rawIspInfo']['country'])) {
        $country = trim($decoded['rawIspInfo']['country']);
        if ('' !== $country) {
            return statsSafeName($country);
        }
    }

    if (isset($decoded['processedString']) && is_string($decoded['processedString'])) {
        // "<ip> - <isp>, <country>", with an optional " (<distance>)" suffix.
        $s = preg_replace('/\s*\([^)]*\)\s*$/', '', $decoded['processedString']);
        $comma = strrpos($s, ',');
        if (false !== $comma) {
            $country = trim(substr($s, $comma + 1));
            if ('' !== $country) {
                return statsSafeName($country);
            }
        }
    }

    return 'Unknown';
}

/**
 * Folds groups below the privacy threshold into a single "Other" entry.
 *
 * @param array $groups   name => ['tests' => int, ...]
 * @param int   $threshold
 *
 * @return array
 */
function statsApplyThreshold(array $groups, $threshold)
{
    $kept = [];
    $foldedTests = 0;
    $foldedGroups = 0;

    foreach ($groups as $name => $group) {
        if ($group['tests'] >= $threshold) {
            $kept[$name] = $group;
        } else {
            $foldedTests += $group['tests'];
            ++$foldedGroups;
        }
    }

    if ($foldedTests >= $threshold) {
        $kept['Other'] = ['tests' => $foldedTests, 'groups' => $foldedGroups];
    }

    return $kept;
}

/**
 * Builds the summary for a half-open period.
 *
 * @param string $from inclusive, "YYYY-MM-DD HH:MM:SS"
 * @param string $to   exclusive
 *
 * @return array|false
 */
function statsBuildSummary($from, $to)
{
    $stmt = getSpeedtestUsersBetween($from, $to);
    if (!($stmt instanceof PDOStatement)) {
        return false;
    }

    $overall = [
        'download' => statsNewSeries(),
        'upload' => statsNewSeries(),
        'ping' => statsNewSeries(),
        'jitter' => statsNewSeries(),
    ];
    $tests = 0;
    $malformed = 0;
    $clients = [];
    $families = [];
    $countries = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        ++$tests;

        $dl = normalizeMeasurement($row['dl']);
        $ul = normalizeMeasurement($row['ul']);
        $ping = normalizeMeasurement($row['ping']);
        $jitter = normalizeMeasurement($row['jitter']);

        if (null === $dl) {
            ++$malformed;
        }

        statsAdd($overall['download'], $dl, STATS_SPEED_BUCKET);
        statsAdd($overall['upload'], $ul, STATS_SPEED_BUCKET);
        statsAdd($overall['ping'], $ping, STATS_LATENCY_BUCKET);
        statsAdd($overall['jitter'], $jitter, STATS_LATENCY_BUCKET);

        $client = statsClassifyClient($row['ua']);
        $family = statsAddressFamily($row['ip']);
        $country = statsCountry($row['ispinfo']);

        if (!isset($clients[$client])) {
            $clients[$client] = [
                'tests' => 0,
                'download' => statsNewSeries(),
                'upload' => statsNewSeries(),
                'families' => [],
            ];
        }
        ++$clients[$client]['tests'];
        statsAdd($clients[$client]['download'], $dl, STATS_SPEED_BUCKET);
        statsAdd($clients[$client]['upload'], $ul, STATS_SPEED_BUCKET);
        if (!isset($clients[$client]['families'][$family])) {
            $clients[$client]['families'][$family] = 0;
        }
        ++$clients[$client]['families'][$family];

        if (!isset($families[$family])) {
            $families[$family] = ['tests' => 0, 'download' => statsNewSeries()];
        }
        ++$families[$family]['tests'];
        statsAdd($families[$family]['download'], $dl, STATS_SPEED_BUCKET);

        if (!isset($countries[$country])) {
            $countries[$country] = [
                'tests' => 0,
                'download' => statsNewSeries(),
                'upload' => statsNewSeries(),
                'ipv6' => 0,
            ];
        }
        ++$countries[$country]['tests'];
        statsAdd($countries[$country]['download'], $dl, STATS_SPEED_BUCKET);
        statsAdd($countries[$country]['upload'], $ul, STATS_SPEED_BUCKET);
        if ('IPv6' === $family) {
            ++$countries[$country]['ipv6'];
        }
    }

    $summary = [
        'from' => $from,
        'to' => $to,
        'generated' => gmdate('Y-m-d H:i:s').' UTC',
        'threshold' => STATS_PRIVACY_THRESHOLD,
        'speed_resolution' => STATS_SPEED_BUCKET,
        'latency_resolution' => STATS_LATENCY_BUCKET,
        'tests' => $tests,
        'malformed' => $malformed,
        'download' => statsSummarise($overall['download'], STATS_SPEED_BUCKET),
        'upload' => statsSummarise($overall['upload'], STATS_SPEED_BUCKET),
        'ping' => statsSummarise($overall['ping'], STATS_LATENCY_BUCKET),
        'jitter' => statsSummarise($overall['jitter'], STATS_LATENCY_BUCKET),
    ];

    $reduced = [];
    foreach ($clients as $name => $client) {
        $reduced[$name] = [
            'tests' => $client['tests'],
            'download' => statsSummarise($client['download'], STATS_SPEED_BUCKET),
            'upload' => statsSummarise($client['upload'], STATS_SPEED_BUCKET),
            'families' => $client['families'],
        ];
    }
    $summary['clients'] = statsApplyThreshold($reduced, STATS_PRIVACY_THRESHOLD);

    $reduced = [];
    foreach ($families as $name => $family) {
        $reduced[$name] = [
            'tests' => $family['tests'],
            'download' => statsSummarise($family['download'], STATS_SPEED_BUCKET),
        ];
    }
    $summary['families'] = statsApplyThreshold($reduced, STATS_PRIVACY_THRESHOLD);

    $reduced = [];
    foreach ($countries as $name => $country) {
        $reduced[$name] = [
            'tests' => $country['tests'],
            'download' => statsSummarise($country['download'], STATS_SPEED_BUCKET),
            'upload' => statsSummarise($country['upload'], STATS_SPEED_BUCKET),
            'ipv6_share' => $country['tests'] > 0 ? $country['ipv6'] / $country['tests'] : 0.0,
        ];
    }
    uasort($reduced, function ($a, $b) {
        return $b['tests'] - $a['tests'];
    });
    $summary['countries'] = statsApplyThreshold($reduced, STATS_PRIVACY_THRESHOLD);

    return $summary;
}

/**
 * The bounds of a calendar month, as the database stores them.
 *
 * @param string $month "YYYY-MM"
 *
 * @return array{0: string, 1: string}|null
 */
function statsMonthBounds($month)
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $month, $m)) {
        return null;
    }

    $year = (int) $m[1];
    $mon = (int) $m[2];
    if ($mon < 1 || $mon > 12) {
        return null;
    }

    $from = sprintf('%04d-%02d-01 00:00:00', $year, $mon);
    $nextYear = 12 === $mon ? $year + 1 : $year;
    $nextMon = 12 === $mon ? 1 : $mon + 1;
    $to = sprintf('%04d-%02d-01 00:00:00', $nextYear, $nextMon);

    return [$from, $to];
}

/**
 * Where cached summaries are written.
 *
 * @return string
 */
function statsCacheDir()
{
    require TELEMETRY_SETTINGS_FILE;

    if (isset($stats_cache_dir) && '' !== $stats_cache_dir) {
        return rtrim($stats_cache_dir, '/');
    }

    // Keyed by installation, so two deployments sharing a temporary directory
    // cannot land on the same path, and so a directory left behind by another
    // user cannot be written into.
    return rtrim(sys_get_temp_dir(), '/').'/librespeed-stats-'.substr(sha1(__DIR__), 0, 12);
}

/**
 * The month the database considers current, falling back to this machine's
 * clock if it cannot be asked.
 *
 * @return string "YYYY-MM"
 */
function statsCurrentMonth()
{
    $now = getDatabaseNow();
    if (null !== $now && preg_match('/^(\d{4})-(\d{2})/', $now, $m)) {
        return $m[1].'-'.$m[2];
    }

    return gmdate('Y-m');
}

/**
 * The month before the current one.
 *
 * @return string "YYYY-MM"
 */
function statsPreviousMonth()
{
    $current = statsCurrentMonth();

    return gmdate('Y-m', strtotime($current.'-01 12:00:00 -1 month'));
}

/**
 * Returns a month's summary, computing it only when the cache is missing or
 * older than the month it describes still being open.
 *
 * A finished month never changes, so its cache never expires. The current month
 * is recomputed at most once every $maxAge seconds, which is what stops a page
 * view from turning into a table scan.
 *
 * @param string $month "YYYY-MM"
 * @param int    $maxAge
 * @param bool   $build whether a stale cache may be regenerated here
 *
 * @return array|null
 */
function statsMonthlySummary($month, $maxAge = 3600, $build = true)
{
    $bounds = statsMonthBounds($month);
    if (null === $bounds) {
        return null;
    }

    $dir = statsCacheDir();
    $file = $dir.'/'.$month.'.json';
    $isCurrent = $month === statsCurrentMonth();

    if (is_readable($file)) {
        $age = time() - (int) filemtime($file);
        if (!$isCurrent || $age < $maxAge) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
    }

    if (!$build) {
        return null;
    }

    $summary = statsBuildSummary($bounds[0], $bounds[1]);
    if (false === $summary) {
        return null;
    }

    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $encoded = json_encode($summary);
    if (false !== $encoded && is_dir($dir) && is_writable($dir)) {
        // Written through a temporary file so a reader never sees half of it,
        // and only promoted once the whole of it is on disk. A short write left
        // in place would be read back as an unusable cache, and every request
        // would then scan the month again -- the one outcome this file exists
        // to prevent.
        $tmp = $file.'.'.getmypid().'.tmp';
        if (strlen($encoded) === @file_put_contents($tmp, $encoded)) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }

    return $summary;
}
