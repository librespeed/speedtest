<?php

/**
 * Public monthly aggregate report.
 *
 * Off unless $stats_public_report is enabled. It answers only from a cached
 * summary and never queries the database on a visitor's behalf, so the cost of
 * serving it is the cost of reading one small file however many people ask at
 * once. The month being reported is always a finished one, which is both the
 * reason the cache can be permanent and the reason the figures do not shift
 * under a reader.
 */

require 'telemetry_settings.php';
require_once 'stats_summary.php';
require_once 'stats_render.php';

header('Content-Type: text/html; charset=utf-8');

$enabled = isset($stats_public_report) && true === $stats_public_report;

// Default to the most recent finished month, and refuse to serve any other
// kind. The current month is still being written to, so publishing it would
// mean figures that move under a reader and disagree with a copy taken an hour
// earlier. stats.php shows it to a logged-in operator, where that is expected;
// a public report should only carry a month that is done.
$month = statsPreviousMonth();
if (isset($_GET['month']) && is_string($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $month = $_GET['month'];
}
$finished = $month < statsCurrentMonth();

// A visitor never triggers a build: an uncached month simply is not available
// yet. Otherwise a request for an arbitrary month would be a way to ask the
// server to scan its telemetry table, which is exactly what this page exists to
// avoid. Summaries are produced by stats.php or by running stats_build.php.
$summary = ($enabled && $finished) ? statsMonthlySummary($month, 3600, false) : null;

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>LibreSpeed - Statistics</title>
        <?php echo statsRenderHead(); ?>
        <style>
            html{ background:var(--ls-page); font-family:"Inter","Segoe UI","Roboto",sans-serif; }
            body{ max-width:60em; margin:0 auto; padding:2em 1em; color:var(--ls-text); }
            h1{ font-weight:300; }
            a{ color:var(--ls-dl); }
            .ls-months{ margin:1em 0; color:var(--ls-dim); font-size:.9em; }
        </style>
    </head>
    <body>
        <h1>LibreSpeed statistics</h1>
        <?php
        if (!$enabled) {
            echo '<p>Public statistics are not enabled on this server.</p>';
        } elseif (!$finished) {
            echo '<p>'.statsEscape($month).' is still in progress. Reports are published once a month is complete.</p>';
        } elseif (null === $summary) {
            echo '<p>No report has been generated for '.statsEscape($month).' yet.</p>';
        } else {
            echo statsRenderSummary($summary);

            $months = [];
            for ($i = 1; $i <= 6; ++$i) {
                $m = gmdate('Y-m', strtotime(statsCurrentMonth().'-01 12:00:00 -'.$i.' month'));
                if (null !== statsMonthlySummary($m, 3600, false)) {
                    $months[] = '<a href="?month='.statsEscape($m).'">'.statsEscape($m).'</a>';
                }
            }
            if ($months) {
                echo '<p class="ls-months">Other months: '.implode(' · ', $months).'</p>';
            }
        }
        ?>
    </body>
</html>
