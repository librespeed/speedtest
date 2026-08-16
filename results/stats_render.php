<?php

/**
 * Rendering for the aggregate telemetry summary.
 *
 * Shared by the authenticated stats page and the public monthly report so the
 * two cannot drift apart. The styling is inline rather than pulled from
 * frontend/styling: the results folder is optional and documented as something
 * a deployment may install on its own, so it cannot assume the frontend is
 * there to link against.
 */

/**
 * @param float|null $mbps
 *
 * @return string
 */
function statsFormatSpeed($mbps)
{
    if (null === $mbps) {
        return '—';
    }
    if ($mbps >= 1000) {
        return number_format($mbps / 1000, 2, '.', '').' Gbit/s';
    }
    if ($mbps >= 100) {
        return number_format($mbps, 0, '.', ' ').' Mbit/s';
    }

    return number_format($mbps, 1, '.', '').' Mbit/s';
}

/**
 * @param float $ms
 *
 * @return string
 */
function statsFormatLatency($ms)
{
    return null === $ms ? '—' : number_format($ms, 1, '.', '').' ms';
}

/**
 * @param int $n
 *
 * @return string
 */
function statsFormatCount($n)
{
    return number_format((int) $n, 0, '.', ' ');
}

/**
 * @param string $s
 *
 * @return string
 */
function statsEscape($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * The styles and the theme toggle, for the document head.
 *
 * @return string
 */
function statsRenderHead()
{
    return <<<'CSS'
<style>
    :root{
        --ls-page:#0b0616; --ls-card:#0e0720; --ls-panel:#17102a; --ls-edge:#2a2040;
        --ls-text:#ffffff; --ls-dim:#898591; --ls-dl:#5cf9fd; --ls-ul:#d63bc6;
    }
    /* The modern frontend is dark, so that is the default. The accents from
       colors.css are chosen against a dark background and are unreadable on a
       light one, so the light theme darkens them rather than reusing them. */
    @media (prefers-color-scheme: light){
        :root{
            --ls-page:#ececed; --ls-card:#ffffff; --ls-panel:#f6f6f9; --ls-edge:#dcdce4;
            --ls-text:#16121f; --ls-dim:#66636f; --ls-dl:#0d7f95; --ls-ul:#a3229a;
        }
    }
    /* An explicit choice outranks the system preference. Attribute selectors
       are more specific than the bare :root above, and a media query adds no
       specificity of its own, so these win in both directions. */
    :root[data-ls-theme="light"]{
        --ls-page:#ececed; --ls-card:#ffffff; --ls-panel:#f6f6f9; --ls-edge:#dcdce4;
        --ls-text:#16121f; --ls-dim:#66636f; --ls-dl:#0d7f95; --ls-ul:#a3229a;
    }
    :root[data-ls-theme="dark"]{
        --ls-page:#0b0616; --ls-card:#0e0720; --ls-panel:#17102a; --ls-edge:#2a2040;
        --ls-text:#ffffff; --ls-dim:#898591; --ls-dl:#5cf9fd; --ls-ul:#d63bc6;
    }
    .ls-theme{
        margin-left:auto; cursor:pointer;
        background:var(--ls-panel); color:var(--ls-dim);
        border:1px solid var(--ls-edge); border-radius:99px;
        font:inherit; font-size:.8em; padding:.35em .9em;
    }
    .ls-theme:hover{ color:var(--ls-text); }
    /* Without scripting there is nothing to toggle, and a dead control is
       worse than none: the page still follows the system preference. */
    .ls-theme[hidden]{ display:none; }
    /* The results pages ship no CSS reset, so padding would otherwise be added
       to every width below — enough to push two summary cards past a phone. */
    .ls-stats, .ls-stats *{ box-sizing:border-box; }
    .ls-stats{
        max-width:100%;
        background:var(--ls-card); color:var(--ls-text);
        font-family:"Inter","Segoe UI","Roboto",sans-serif;
        border-radius:14px; padding:1.5em; margin:2em 0;
    }
    .ls-head{ display:flex; flex-wrap:wrap; align-items:baseline; gap:.6em; }
    .ls-head .ls-mark{ font-size:1.15em; font-weight:600; }
    .ls-head .ls-server{ color:var(--ls-dim); font-size:.95em; }
    .ls-stats h2,.ls-stats h3{ color:var(--ls-text); font-weight:600; margin:0 0 .2em 0; }
    .ls-stats h3{ font-size:.85em; letter-spacing:.12em; text-transform:uppercase; color:var(--ls-dim); margin-top:2em; }
    .ls-stats .ls-note{ color:var(--ls-dim); font-size:.85em; margin:.4em 0 0 0; }
    .ls-cards{ display:flex; flex-wrap:wrap; gap:.8em; margin:1.2em 0; }
    .ls-card{ flex:1 1 10em; background:var(--ls-panel); border:1px solid var(--ls-edge); border-radius:12px; padding:1em; }
    .ls-card .ls-label{ color:var(--ls-dim); font-size:.8em; letter-spacing:.08em; text-transform:uppercase; }
    .ls-card .ls-value{ font-size:1.8em; font-weight:300; margin-top:.15em; }
    .ls-stats table{ width:100%; border-collapse:collapse; margin:.8em 0 0 0; border:none; }
    .ls-stats table,.ls-stats tr,.ls-stats th,.ls-stats td{ border:none; }
    .ls-stats th{ width:auto; text-align:left; color:var(--ls-dim); font-weight:600;
        font-size:.78em; letter-spacing:.08em; text-transform:uppercase;
        padding:.5em .6em; border-bottom:1px solid var(--ls-edge); }
    .ls-stats td{ padding:.5em .6em; border-bottom:1px solid var(--ls-edge); word-break:normal; }
    .ls-stats td.ls-num,.ls-stats th.ls-num{ text-align:right; font-variant-numeric:tabular-nums; }
    .ls-bar{ background:var(--ls-edge); border-radius:99px; height:.5em; overflow:hidden; min-width:6em; }
    .ls-bar span{ display:block; height:100%; background:var(--ls-dl); }
    .ls-bar.ls-ul span{ background:var(--ls-ul); }
    /* The client table carries eight columns, which no phone fits. Letting it
       scroll inside its own box keeps the page itself from scrolling sideways,
       which is the part that makes a layout feel broken. */
    .ls-scroll{ overflow-x:auto; }
    @media (max-width:40em){
        .ls-stats{ padding:1em; border-radius:10px; margin:1em 0; }
        .ls-card .ls-value{ font-size:1.5em; }
        .ls-stats th,.ls-stats td{ padding:.45em .5em; white-space:nowrap; }
        .ls-stats h3{ margin-top:1.5em; }
    }
</style>
<script>
(function () {
    var KEY = 'librespeed-stats-theme';
    var root = document.documentElement;

    function stored() {
        try {
            var v = window.localStorage.getItem(KEY);
            return v === 'light' || v === 'dark' ? v : null;
        } catch (e) {
            // Private browsing can refuse localStorage outright. The toggle
            // still works for the current page; it just will not be remembered.
            return null;
        }
    }

    function systemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches
            ? 'light'
            : 'dark';
    }

    function current() {
        return root.getAttribute('data-ls-theme') || systemTheme();
    }

    // Applied before the body is parsed, so the page never paints the wrong
    // theme first and corrects itself.
    var saved = stored();
    if (saved) {
        root.setAttribute('data-ls-theme', saved);
    }

    function label(button) {
        button.textContent = current() === 'dark' ? 'Light theme' : 'Dark theme';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var buttons = document.querySelectorAll('[data-ls-theme-toggle]');
        Array.prototype.forEach.call(buttons, function (button) {
            button.hidden = false;
            label(button);
            button.addEventListener('click', function () {
                var next = current() === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-ls-theme', next);
                try {
                    window.localStorage.setItem(KEY, next);
                } catch (e) {
                    // As above: not remembering is acceptable, failing is not.
                }
                Array.prototype.forEach.call(buttons, label);
            });
        });
    });
})();
</script>
CSS;
}

/**
 * Names the server the figures came from.
 *
 * A report is read as a screenshot or a PDF as often as in a browser, and by
 * then the address bar is gone. Without this the numbers do not say whose they
 * are. $stats_title is preferred over the requested host, which is set by the
 * client and can be anything.
 *
 * @return string
 */
function statsServerName()
{
    require TELEMETRY_SETTINGS_FILE;

    if (isset($stats_title) && '' !== trim((string) $stats_title)) {
        return trim((string) $stats_title);
    }

    if (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])) {
        return $_SERVER['HTTP_HOST'];
    }

    return '';
}

/**
 * @param array $summary
 *
 * @return string
 */
function statsRenderSummary(array $summary)
{
    $h = '<div class="ls-stats">';

    $server = statsServerName();
    $h .= '<div class="ls-head"><span class="ls-mark">LibreSpeed</span>';
    if ('' !== $server) {
        $h .= '<span class="ls-server">'.statsEscape($server).'</span>';
    }
    $h .= '<button type="button" class="ls-theme" data-ls-theme-toggle hidden></button>';
    $h .= '</div>';

    $h .= '<h2>Telemetry overview</h2>';
    $h .= '<p class="ls-note">'.statsEscape(substr($summary['from'], 0, 10)).' to '
        .statsEscape(substr($summary['to'], 0, 10)).', generated '.statsEscape($summary['generated']).'</p>';

    $cards = [
        ['Tests', statsFormatCount($summary['tests'])],
        ['Median download', $summary['download'] ? statsFormatSpeed($summary['download']['p50']) : '—'],
        ['Median upload', $summary['upload'] ? statsFormatSpeed($summary['upload']['p50']) : '—'],
        ['Median ping', $summary['ping'] ? statsFormatLatency($summary['ping']['p50']) : '—'],
    ];
    $h .= '<div class="ls-cards">';
    foreach ($cards as $card) {
        $h .= '<div class="ls-card"><div class="ls-label">'.statsEscape($card[0])
            .'</div><div class="ls-value">'.statsEscape($card[1]).'</div></div>';
    }
    $h .= '</div>';

    if ($summary['tests'] < 1) {
        return $h.'<p class="ls-note">No tests recorded in this period.</p></div>';
    }

    // Distribution
    if ($summary['download']) {
        $h .= '<h3>Download distribution</h3>';
        $h .= '<div class="ls-scroll"><table><tr><th>Percentile</th><th class="ls-num">Download</th><th class="ls-num">Upload</th></tr>';
        foreach ([10, 25, 50, 75, 90] as $p) {
            $h .= '<tr><td>P'.$p.'</td><td class="ls-num">'.statsEscape(statsFormatSpeed($summary['download']['p'.$p]))
                .'</td><td class="ls-num">'
                .statsEscape($summary['upload'] ? statsFormatSpeed($summary['upload']['p'.$p]) : '—')
                .'</td></tr>';
        }
        $h .= '<tr><td>Mean</td><td class="ls-num">'.statsEscape(statsFormatSpeed($summary['download']['mean']))
            .'</td><td class="ls-num">'
            .statsEscape($summary['upload'] ? statsFormatSpeed($summary['upload']['mean']) : '—')
            .'</td></tr>';
        $h .= '</table></div>';
        $h .= '<p class="ls-note">Percentiles are resolved to '
            .statsEscape((string) $summary['speed_resolution']).' Mbit/s. The mean is exact, and is the figure most'
            .' affected by a handful of very fast or fabricated results.</p>';
    }

    // Clients
    if (!empty($summary['clients'])) {
        $h .= '<h3>Clients</h3>';
        $h .= '<div class="ls-scroll"><table><tr><th>Client</th><th class="ls-num">Tests</th><th class="ls-num">Mean DL</th>'
            .'<th class="ls-num">Median DL</th><th class="ls-num">P90 DL</th>'
            .'<th class="ls-num">Mean UL</th><th class="ls-num">Median UL</th>'
            .'<th class="ls-num">IPv6</th></tr>';
        foreach ($summary['clients'] as $name => $c) {
            if ('Other' === $name && !isset($c['download'])) {
                $h .= '<tr><td>Other</td><td class="ls-num">'.statsFormatCount($c['tests'])
                    .'</td><td class="ls-num" colspan="6">'.statsEscape($c['groups'])
                    .' groups below the reporting threshold</td></tr>';

                continue;
            }
            $total = array_sum($c['families']);
            $v6 = isset($c['families']['IPv6']) ? $c['families']['IPv6'] : 0;
            $h .= '<tr><td>'.statsEscape($name).'</td>'
                .'<td class="ls-num">'.statsFormatCount($c['tests']).'</td>'
                .'<td class="ls-num">'.statsEscape($c['download'] ? statsFormatSpeed($c['download']['mean']) : '—').'</td>'
                .'<td class="ls-num">'.statsEscape($c['download'] ? statsFormatSpeed($c['download']['p50']) : '—').'</td>'
                .'<td class="ls-num">'.statsEscape($c['download'] ? statsFormatSpeed($c['download']['p90']) : '—').'</td>'
                .'<td class="ls-num">'.statsEscape($c['upload'] ? statsFormatSpeed($c['upload']['mean']) : '—').'</td>'
                .'<td class="ls-num">'.statsEscape($c['upload'] ? statsFormatSpeed($c['upload']['p50']) : '—').'</td>'
                .'<td class="ls-num">'.($total > 0 ? number_format($v6 / $total * 100, 1).'&nbsp;%' : '—').'</td></tr>';
        }
        $h .= '</table></div>';
        $h .= '<p class="ls-note">Clients are grouped from the User-Agent, which is chosen by the sender:'
            .' this describes what clients report themselves as. Individual User-Agent strings are never shown.</p>';
    }

    // Address family
    if (!empty($summary['families'])) {
        $totalFam = 0;
        foreach ($summary['families'] as $f) {
            $totalFam += $f['tests'];
        }
        $h .= '<h3>IPv4 and IPv6</h3><div class="ls-scroll"><table>';
        $h .= '<tr><th>Family</th><th class="ls-num">Tests</th><th class="ls-num">Share</th>'
            .'<th class="ls-num">Median DL</th><th style="width:40%"></th></tr>';
        foreach ($summary['families'] as $name => $f) {
            $share = $totalFam > 0 ? $f['tests'] / $totalFam * 100 : 0;
            $h .= '<tr><td>'.statsEscape($name).'</td>'
                .'<td class="ls-num">'.statsFormatCount($f['tests']).'</td>'
                .'<td class="ls-num">'.number_format($share, 1).'&nbsp;%</td>'
                .'<td class="ls-num">'.statsEscape(isset($f['download']) && $f['download'] ? statsFormatSpeed($f['download']['p50']) : '—').'</td>'
                .'<td><div class="ls-bar"><span style="width:'.number_format($share, 1).'%"></span></div></td></tr>';
        }
        $h .= '</table></div>';
    }

    // Countries
    if (!empty($summary['countries'])) {
        $h .= '<h3>Countries</h3><div class="ls-scroll"><table>';
        $h .= '<tr><th>Country</th><th class="ls-num">Tests</th><th class="ls-num">Median DL</th>'
            .'<th class="ls-num">Median UL</th><th class="ls-num">IPv6</th></tr>';
        foreach ($summary['countries'] as $name => $c) {
            if ('Other' === $name && !isset($c['download'])) {
                $h .= '<tr><td>Other</td><td class="ls-num">'.statsFormatCount($c['tests'])
                    .'</td><td class="ls-num" colspan="3">'.statsEscape($c['groups'])
                    .' countries below the reporting threshold</td></tr>';

                continue;
            }
            $h .= '<tr><td>'.statsEscape($name).'</td>'
                .'<td class="ls-num">'.statsFormatCount($c['tests']).'</td>'
                .'<td class="ls-num">'.statsEscape($c['download'] ? statsFormatSpeed($c['download']['p50']) : '—').'</td>'
                .'<td class="ls-num">'.statsEscape($c['upload'] ? statsFormatSpeed($c['upload']['p50']) : '—').'</td>'
                .'<td class="ls-num">'.number_format($c['ipv6_share'] * 100, 1).'&nbsp;%</td></tr>';
        }
        $h .= '</table></div>';
    }

    $h .= '<p class="ls-note">Aggregate figures only. Groups of fewer than '
        .statsEscape((string) $summary['threshold'])
        .' tests are not reported separately, and are collected into "Other" only when that in turn'
        .' reaches the threshold.';
    if ($summary['malformed'] > 0) {
        $h .= ' '.statsFormatCount($summary['malformed']).' of '.statsFormatCount($summary['tests'])
            .' submissions carried no usable download figure and are counted but not measured.';
    }
    $h .= '</p>';

    return $h.'</div>';
}
