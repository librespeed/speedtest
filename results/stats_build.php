<?php

/**
 * Builds cached monthly summaries. Intended for cron, not for the web.
 *
 * Usage:
 *   php stats_build.php [YYYY-MM ...]
 *
 * With no arguments it rebuilds the current month and the one before it, which
 * is what a nightly job wants: the current month keeps moving, and the previous
 * one needs a final pass once it closes.
 *
 * Running this from cron is what lets stats_public.php stay read-only. Without
 * it, the public report simply reports that no summary exists yet rather than
 * building one on a visitor's request.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("This script is meant to be run from the command line.\n");
}

chdir(__DIR__);
require_once __DIR__.'/stats_summary.php';

$months = array_slice($argv, 1);
if (!$months) {
    $months = [statsPreviousMonth(), statsCurrentMonth()];
}

$status = 0;
foreach ($months as $month) {
    $bounds = statsMonthBounds($month);
    if (null === $bounds) {
        fwrite(STDERR, "not a month: $month\n");
        $status = 1;

        continue;
    }

    $started = microtime(true);
    // maxAge 0 forces a rebuild even when a cached copy is fresh.
    $summary = statsMonthlySummary($month, 0);
    if (null === $summary) {
        fwrite(STDERR, "failed to build $month\n");
        $status = 1;

        continue;
    }

    printf(
        "%s: %d tests, %d without a usable download figure, %.1fs\n",
        $month,
        $summary['tests'],
        $summary['malformed'],
        microtime(true) - $started
    );
}

exit($status);
