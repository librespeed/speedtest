<?php

/**
 * Tests for the telemetry aggregation helpers.
 *
 * Plain PHP on purpose: the repository has no PHP test framework and adding one
 * for seven functions would cost more than it is worth. Run with
 *
 *     php tests/telemetry_stats_test.php
 *
 * Exits non-zero on the first failing expectation's suite, and prints what
 * differed.
 */

chdir(__DIR__.'/../results');
require_once __DIR__.'/../results/stats_summary.php';

$failures = 0;
$checks = 0;

/**
 * @param mixed  $expected
 * @param mixed  $actual
 * @param string $what
 *
 * @return void
 */
function check($expected, $actual, $what)
{
    global $failures, $checks;
    ++$checks;
    if ($expected === $actual) {
        return;
    }
    ++$failures;
    fwrite(STDERR, sprintf(
        "FAIL %s\n  expected: %s\n  actual:   %s\n",
        $what,
        var_export($expected, true),
        var_export($actual, true)
    ));
}

// ---- normalizeMeasurement -------------------------------------------------
check(312.5, normalizeMeasurement('312.5'), 'a decimal string is a number');
check(0.0, normalizeMeasurement('0'), 'zero is a measurement, not a missing one');
check(312.5, normalizeMeasurement(312.5), 'a float passes through');
check(1.0e3, normalizeMeasurement('1e3'), 'exponent notation is numeric');
check(42.0, normalizeMeasurement('  42  '), 'surrounding space is trimmed');
check(null, normalizeMeasurement(''), 'the empty string is not a number');
check(null, normalizeMeasurement('   '), 'neither is whitespace');
check(null, normalizeMeasurement('abc'), 'nor a word');
check(null, normalizeMeasurement('12abc'), 'nor a number with a tail');
check(null, normalizeMeasurement(null), 'nor a missing field');
check(null, normalizeMeasurement([]), 'nor an array, which PHP would otherwise cast');
check(null, normalizeMeasurement(true), 'nor a boolean');
check(null, normalizeMeasurement('-5'), 'a negative speed is not a measurement');
check(null, normalizeMeasurement('NaN'), 'NaN is rejected');
check(null, normalizeMeasurement('INF'), 'so is infinity');

// ---- statsClassifyClient --------------------------------------------------
check('LibreSpeed CLI (Rust)', statsClassifyClient('librespeed-cli-rust/v0.1.0'), 'the Rust CLI');
check(
    'LibreSpeed CLI (Rust)',
    statsClassifyClient('librespeed-cli-rust/v0.1.0 (linux; powerpc)'),
    'the Rust CLI naming its platform'
);
check('LibreSpeed CLI (Go)', statsClassifyClient('librespeed-cli/1.0.12'), 'the Go CLI');
check(
    'LibreSpeed CLI (Go)',
    statsClassifyClient('librespeed-cli/1.0.12 (linux; arm64)'),
    'the Go CLI naming its platform'
);
check(
    'Web browser',
    statsClassifyClient('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/141.0'),
    'a browser'
);
check('Android app', statsClassifyClient('Dalvik/2.1.0 (Linux; U; Android 16)'), 'an Android app');
check('Android app', statsClassifyClient('okhttp/4.12.0'), 'an okhttp client');
check('Unknown', statsClassifyClient(''), 'a missing user agent');
check('Unknown', statsClassifyClient('   '), 'a blank user agent');
check('Other', statsClassifyClient('curl/8.7.1'), 'anything else');

// ---- statsAddressFamily ---------------------------------------------------
check('IPv4', statsAddressFamily('198.51.100.7'), 'an IPv4 address');
check('IPv6', statsAddressFamily('2001:db8::1'), 'an IPv6 address');
check('Unknown', statsAddressFamily('0.0.0.0'), 'the placeholder redaction stores');
check('Unknown', statsAddressFamily(''), 'a missing address');

// ---- statsCountry ---------------------------------------------------------
check(
    'CZ',
    statsCountry(json_encode(['rawIspInfo' => ['country' => 'CZ'], 'processedString' => 'x - y, ZZ'])),
    'the raw field wins over the string'
);
check(
    'Czechia',
    statsCountry(json_encode(['processedString' => '198.51.100.7 - Example ISP, Czechia'])),
    'the country is read off the processed string'
);
check(
    'Czechia',
    statsCountry(json_encode(['processedString' => '198.51.100.7 - Example ISP, Czechia (123.4 km)'])),
    'a distance suffix is ignored'
);
check('Unknown', statsCountry(''), 'no ISP information at all');
check('Unknown', statsCountry('not json'), 'unparseable ISP information');
check(
    'Unknown',
    statsCountry(json_encode(['processedString' => '198.51.100.7 - Example ISP'])),
    'an ISP string with no country'
);

// ---- statsSummarise -------------------------------------------------------
$series = statsNewSeries();
foreach (range(1, 100) as $v) {
    statsAdd($series, (float) $v, 1.0);
}
$s = statsSummarise($series, 1.0);
check(100, $s['tests'], 'every value counted');
check(50.5, $s['mean'], 'the mean is exact, not bucketed');
check(10.0, $s['p10'], 'P10 of 1..100');
check(50.0, $s['p50'], 'P50 of 1..100');
check(90.0, $s['p90'], 'P90 of 1..100');

$series = statsNewSeries();
statsAdd($series, null, 1.0);
check(null, statsSummarise($series, 1.0), 'a series of only nulls reports nothing');

$series = statsNewSeries();
statsAdd($series, 7.0, 1.0);
$s = statsSummarise($series, 1.0);
check(7.0, $s['p50'], 'a single value is its own median');
check(7.0, $s['mean'], 'and its own mean');

// ---- statsApplyThreshold --------------------------------------------------
$groups = ['big' => ['tests' => 500], 'small' => ['tests' => 60], 'tiny' => ['tests' => 50]];
$kept = statsApplyThreshold($groups, 100);
check(['big', 'Other'], array_keys($kept), 'small groups fold into Other');
check(110, $kept['Other']['tests'], 'Other carries their combined size');
check(2, $kept['Other']['groups'], 'and how many were folded');

$kept = statsApplyThreshold(['big' => ['tests' => 500], 'tiny' => ['tests' => 50]], 100);
check(['big'], array_keys($kept), 'an Other below the threshold is dropped rather than shown');

$kept = statsApplyThreshold([], 100);
check([], $kept, 'no groups at all');

// ---- statsSafeName --------------------------------------------------------
check('Czechia', statsSafeName('Czechia'), 'plain ASCII passes');
check('Česko', statsSafeName('Česko'), 'valid UTF-8 passes');
check('Unknown', statsSafeName("\xC3\x28"), 'invalid UTF-8 becomes unknown');
// A group name that json_encode() refuses would leave the whole summary
// uncacheable, and every request would rebuild it with a full table scan.
check(
    'Unknown',
    statsCountry(json_encode(['rawIspInfo' => ['country' => 'CZ']]) === false ? '' : json_encode(['processedString' => "1.2.3.4 - ISP, \xC3\x28"])),
    'a country carrying invalid UTF-8 does not reach the summary'
);
$encodable = json_encode(['x' => statsSafeName("\xC3\x28")]);
check(true, false !== $encodable, 'a sanitised name is encodable');

// ---- statsMonthBounds -----------------------------------------------------
check(
    ['2026-08-01 00:00:00', '2026-09-01 00:00:00'],
    statsMonthBounds('2026-08'),
    'a month in the middle of the year'
);
check(
    ['2026-12-01 00:00:00', '2027-01-01 00:00:00'],
    statsMonthBounds('2026-12'),
    'December rolls into the next year'
);
check(null, statsMonthBounds('2026-13'), 'a thirteenth month is rejected');
check(null, statsMonthBounds('2026-00'), 'and a zeroth');
check(null, statsMonthBounds('not-a-month'), 'and nonsense');
check(null, statsMonthBounds(''), 'and nothing');

printf("%d checks, %d failures\n", $checks, $failures);
exit($failures > 0 ? 1 : 0);
