<?php
session_start();
error_reporting(0);

require 'telemetry_settings.php';
require_once 'telemetry_db.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

/**
 * Get analytics data from database
 */
function getAnalyticsData() {
    $pdo = getPdo();
    if (!($pdo instanceof PDO)) {
        return false;
    }

    require TELEMETRY_SETTINGS_FILE;

    $data = [
        'total_tests' => 0,
        'avg_download' => 0,
        'avg_upload' => 0,
        'avg_ping' => 0,
        'avg_jitter' => 0,
        'tests_by_day' => [],
        'download_distribution' => [],
        'upload_distribution' => [],
        'ping_distribution' => [],
        'browsers' => [],
        'recent_speeds' => [],
        'countries' => [],
        'cities' => [],
        'isps' => [],
        'unique_countries' => 0
    ];

    try {
        // Total tests
        $stmt = $pdo->query('SELECT COUNT(*) as total FROM speedtest_users');
        $data['total_tests'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Average speeds
        $stmt = $pdo->query('SELECT
            AVG(CAST(dl AS DECIMAL(10,2))) as avg_dl,
            AVG(CAST(ul AS DECIMAL(10,2))) as avg_ul,
            AVG(CAST(ping AS DECIMAL(10,2))) as avg_ping,
            AVG(CAST(jitter AS DECIMAL(10,2))) as avg_jitter,
            MAX(CAST(dl AS DECIMAL(10,2))) as max_dl,
            MAX(CAST(ul AS DECIMAL(10,2))) as max_ul,
            MIN(CAST(ping AS DECIMAL(10,2))) as min_ping
            FROM speedtest_users
            WHERE dl IS NOT NULL AND dl != ""');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['avg_download'] = round((float)$row['avg_dl'], 2);
        $data['avg_upload'] = round((float)$row['avg_ul'], 2);
        $data['avg_ping'] = round((float)$row['avg_ping'], 2);
        $data['avg_jitter'] = round((float)$row['avg_jitter'], 2);
        $data['max_download'] = round((float)$row['max_dl'], 2);
        $data['max_upload'] = round((float)$row['max_ul'], 2);
        $data['min_ping'] = round((float)$row['min_ping'], 2);

        // Tests by day (last 30 days)
        if ('mssql' === $db_type) {
            $stmt = $pdo->query("SELECT
                CONVERT(VARCHAR(10), timestamp, 120) as day,
                COUNT(*) as count
                FROM speedtest_users
                WHERE timestamp >= DATEADD(day, -30, GETDATE())
                GROUP BY CONVERT(VARCHAR(10), timestamp, 120)
                ORDER BY day ASC");
        } elseif ('postgresql' === $db_type) {
            $stmt = $pdo->query("SELECT
                TO_CHAR(timestamp, 'YYYY-MM-DD') as day,
                COUNT(*) as count
                FROM speedtest_users
                WHERE timestamp >= NOW() - INTERVAL '30 days'
                GROUP BY TO_CHAR(timestamp, 'YYYY-MM-DD')
                ORDER BY day ASC");
        } else {
            $stmt = $pdo->query("SELECT
                DATE(timestamp) as day,
                COUNT(*) as count
                FROM speedtest_users
                WHERE timestamp >= DATE('now', '-30 days')
                GROUP BY DATE(timestamp)
                ORDER BY day ASC");
        }
        $data['tests_by_day'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Download speed distribution (buckets)
        $stmt = $pdo->query('SELECT dl FROM speedtest_users WHERE dl IS NOT NULL AND dl != ""');
        $downloads = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $data['download_distribution'] = createSpeedBuckets($downloads);

        // Upload speed distribution
        $stmt = $pdo->query('SELECT ul FROM speedtest_users WHERE ul IS NOT NULL AND ul != ""');
        $uploads = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $data['upload_distribution'] = createSpeedBuckets($uploads);

        // Ping distribution
        $stmt = $pdo->query('SELECT ping FROM speedtest_users WHERE ping IS NOT NULL AND ping != ""');
        $pings = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $data['ping_distribution'] = createPingBuckets($pings);

        // Browser distribution from user agent
        $stmt = $pdo->query('SELECT ua FROM speedtest_users WHERE ua IS NOT NULL AND ua != ""');
        $userAgents = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $data['browsers'] = parseBrowsers($userAgents);

        // Recent speed trends (last 50 tests for line chart)
        if ('mssql' === $db_type) {
            $stmt = $pdo->query('SELECT TOP(50) timestamp, dl, ul, ping FROM speedtest_users ORDER BY timestamp DESC');
        } else {
            $stmt = $pdo->query('SELECT timestamp, dl, ul, ping FROM speedtest_users ORDER BY timestamp DESC LIMIT 50');
        }
        $data['recent_speeds'] = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        // Location data from ispinfo
        $stmt = $pdo->query('SELECT ispinfo FROM speedtest_users WHERE ispinfo IS NOT NULL AND ispinfo != ""');
        $ispInfos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $locationData = parseLocationData($ispInfos);
        $data['countries'] = $locationData['countries'];
        $data['cities'] = $locationData['cities'];
        $data['isps'] = $locationData['isps'];
        $data['unique_countries'] = count($locationData['countries']);

    } catch (Exception $e) {
        return false;
    }

    return $data;
}

/**
 * Create speed distribution buckets
 */
function createSpeedBuckets($speeds) {
    $buckets = [
        '0-10' => 0,
        '10-25' => 0,
        '25-50' => 0,
        '50-100' => 0,
        '100-250' => 0,
        '250-500' => 0,
        '500-1000' => 0,
        '1000+' => 0
    ];

    foreach ($speeds as $speed) {
        $s = (float)$speed;
        if ($s < 10) $buckets['0-10']++;
        elseif ($s < 25) $buckets['10-25']++;
        elseif ($s < 50) $buckets['25-50']++;
        elseif ($s < 100) $buckets['50-100']++;
        elseif ($s < 250) $buckets['100-250']++;
        elseif ($s < 500) $buckets['250-500']++;
        elseif ($s < 1000) $buckets['500-1000']++;
        else $buckets['1000+']++;
    }

    return $buckets;
}

/**
 * Create ping distribution buckets
 */
function createPingBuckets($pings) {
    $buckets = [
        '0-10' => 0,
        '10-25' => 0,
        '25-50' => 0,
        '50-100' => 0,
        '100-200' => 0,
        '200+' => 0
    ];

    foreach ($pings as $ping) {
        $p = (float)$ping;
        if ($p < 10) $buckets['0-10']++;
        elseif ($p < 25) $buckets['10-25']++;
        elseif ($p < 50) $buckets['25-50']++;
        elseif ($p < 100) $buckets['50-100']++;
        elseif ($p < 200) $buckets['100-200']++;
        else $buckets['200+']++;
    }

    return $buckets;
}

/**
 * Parse user agents to extract browser info
 */
function parseBrowsers($userAgents) {
    $browsers = [
        'Chrome' => 0,
        'Firefox' => 0,
        'Safari' => 0,
        'Edge' => 0,
        'Opera' => 0,
        'Other' => 0
    ];

    foreach ($userAgents as $ua) {
        if (stripos($ua, 'Edg') !== false) {
            $browsers['Edge']++;
        } elseif (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) {
            $browsers['Opera']++;
        } elseif (stripos($ua, 'Chrome') !== false) {
            $browsers['Chrome']++;
        } elseif (stripos($ua, 'Firefox') !== false) {
            $browsers['Firefox']++;
        } elseif (stripos($ua, 'Safari') !== false) {
            $browsers['Safari']++;
        } else {
            $browsers['Other']++;
        }
    }

    return $browsers;
}

/**
 * Parse location data from ispinfo JSON
 */
function parseLocationData($ispInfos) {
    $countries = [];
    $cities = [];
    $isps = [];

    foreach ($ispInfos as $ispinfo) {
        $data = json_decode($ispinfo, true);
        if (!is_array($data)) {
            continue;
        }

        // Get rawIspInfo which contains the actual location data
        $raw = isset($data['rawIspInfo']) ? $data['rawIspInfo'] : null;
        if (!is_array($raw)) {
            continue;
        }

        // Country - can be 'country' (code) or 'country_name' (from offline db)
        $country = null;
        if (!empty($raw['country_name'])) {
            $country = $raw['country_name'];
        } elseif (!empty($raw['country'])) {
            $country = getCountryName($raw['country']);
        }
        if ($country) {
            $countries[$country] = ($countries[$country] ?? 0) + 1;
        }

        // City
        if (!empty($raw['city'])) {
            $city = $raw['city'];
            $cities[$city] = ($cities[$city] ?? 0) + 1;
        }

        // ISP - can be 'org', 'as_name', or in 'asn.name'
        $isp = null;
        if (!empty($raw['as_name'])) {
            $isp = $raw['as_name'];
        } elseif (!empty($raw['org'])) {
            // Remove AS number prefix if present
            $isp = preg_replace('/^AS\d+\s+/', '', $raw['org']);
        } elseif (isset($raw['asn']) && is_array($raw['asn']) && !empty($raw['asn']['name'])) {
            $isp = $raw['asn']['name'];
        }
        if ($isp) {
            $isps[$isp] = ($isps[$isp] ?? 0) + 1;
        }
    }

    // Sort by count descending and limit to top 10
    arsort($countries);
    arsort($cities);
    arsort($isps);

    return [
        'countries' => array_slice($countries, 0, 10, true),
        'cities' => array_slice($cities, 0, 10, true),
        'isps' => array_slice($isps, 0, 10, true)
    ];
}

/**
 * Convert country code to country name
 */
function getCountryName($code) {
    $countries = [
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra',
        'AO' => 'Angola', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AU' => 'Australia',
        'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BH' => 'Bahrain', 'BD' => 'Bangladesh',
        'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin',
        'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia', 'BW' => 'Botswana',
        'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
        'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada',
        'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China',
        'CO' => 'Colombia', 'CD' => 'Congo', 'CR' => 'Costa Rica', 'HR' => 'Croatia',
        'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark',
        'DJ' => 'Djibouti', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt',
        'SV' => 'El Salvador', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FI' => 'Finland',
        'FR' => 'France', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia',
        'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece', 'GT' => 'Guatemala',
        'GN' => 'Guinea', 'HT' => 'Haiti', 'HN' => 'Honduras', 'HK' => 'Hong Kong',
        'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia',
        'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel',
        'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan',
        'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan',
        'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LY' => 'Libya',
        'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MK' => 'Macedonia', 'MG' => 'Madagascar',
        'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta',
        'MX' => 'Mexico', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia',
        'ME' => 'Montenegro', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar',
        'NA' => 'Namibia', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand',
        'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NO' => 'Norway',
        'OM' => 'Oman', 'PK' => 'Pakistan', 'PA' => 'Panama', 'PY' => 'Paraguay',
        'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal',
        'QA' => 'Qatar', 'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda',
        'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SG' => 'Singapore',
        'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SO' => 'Somalia', 'ZA' => 'South Africa',
        'KR' => 'South Korea', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan',
        'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
        'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TN' => 'Tunisia',
        'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'UG' => 'Uganda', 'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
        'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
        'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
    ];
    return $countries[$code] ?? $code;
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>LibreSpeed - Analytics</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style type="text/css">
            html, body {
                margin: 0;
                padding: 0;
                border: none;
                width: 100%;
                min-height: 100%;
            }
            html {
                background-color: hsl(198, 72%, 35%);
                font-family: "Segoe UI", "Roboto", sans-serif;
            }
            body {
                background-color: #FFFFFF;
                box-sizing: border-box;
                width: 100%;
                max-width: 90em;
                margin: 4em auto;
                box-shadow: 0 1em 6em #00000080;
                padding: 1em 2em 4em 2em;
                border-radius: 0.4em;
            }
            h1, h2, h3 {
                font-weight: 300;
                margin-bottom: 0.5em;
                color: #333;
            }
            h1 {
                text-align: center;
                margin-bottom: 1em;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5em;
                margin: 2em 0;
            }
            .stat-card {
                background: linear-gradient(135deg, hsl(198, 72%, 45%), hsl(198, 72%, 35%));
                color: white;
                padding: 1.5em;
                border-radius: 0.5em;
                text-align: center;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .stat-card h3 {
                color: rgba(255,255,255,0.9);
                font-size: 0.9em;
                margin: 0 0 0.5em 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .stat-card .value {
                font-size: 2.5em;
                font-weight: 600;
            }
            .stat-card .unit {
                font-size: 0.8em;
                opacity: 0.8;
            }
            .charts-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 2em;
                margin: 2em 0;
            }
            .chart-container {
                background: #f8f9fa;
                padding: 1.5em;
                border-radius: 0.5em;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .chart-container h3 {
                margin-top: 0;
                color: #555;
            }
            .chart-wrapper {
                position: relative;
                height: 300px;
            }
            .full-width {
                grid-column: 1 / -1;
            }
            .login-form, .logout-form {
                margin: 1em 0;
            }
            .login-form input, .logout-form input {
                padding: 0.5em 1em;
                margin: 0.25em;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .login-form input[type="submit"], .logout-form input[type="submit"] {
                background: hsl(198, 72%, 35%);
                color: white;
                border: none;
                cursor: pointer;
            }
            .login-form input[type="submit"]:hover, .logout-form input[type="submit"]:hover {
                background: hsl(198, 72%, 45%);
            }
            .nav-links {
                text-align: center;
                margin: 1em 0;
            }
            .nav-links a {
                color: hsl(198, 72%, 35%);
                text-decoration: none;
                margin: 0 1em;
            }
            .nav-links a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <h1>LibreSpeed - Analytics</h1>
        <?php
        if (!isset($stats_password) || $stats_password === 'PASSWORD') {
            ?>
            Please set $stats_password in telemetry_settings.php to enable access.
            <?php
        } elseif ($_SESSION['logged'] === true) {
            if ($_GET['op'] === 'logout') {
                $_SESSION['logged'] = false;
                ?><script type="text/javascript">window.location=location.protocol+"//"+location.host+location.pathname;</script><?php
            } else {
                $analytics = getAnalyticsData();
                if ($analytics === false) {
                    echo '<p>Error loading analytics data. Please check database configuration.</p>';
                } else {
                ?>
                <div class="nav-links">
                    <a href="stats.php">View Test Records</a>
                    <a href="analytics.php">Analytics Dashboard</a>
                </div>
                <form action="analytics.php" method="GET" class="logout-form">
                    <input type="hidden" name="op" value="logout" />
                    <input type="submit" value="Logout" />
                </form>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Tests</h3>
                        <div class="value"><?= number_format($analytics['total_tests']) ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Avg Download</h3>
                        <div class="value"><?= $analytics['avg_download'] ?><span class="unit"> Mbps</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Avg Upload</h3>
                        <div class="value"><?= $analytics['avg_upload'] ?><span class="unit"> Mbps</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Avg Ping</h3>
                        <div class="value"><?= $analytics['avg_ping'] ?><span class="unit"> ms</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Max Download</h3>
                        <div class="value"><?= $analytics['max_download'] ?><span class="unit"> Mbps</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Max Upload</h3>
                        <div class="value"><?= $analytics['max_upload'] ?><span class="unit"> Mbps</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Best Ping</h3>
                        <div class="value"><?= $analytics['min_ping'] ?><span class="unit"> ms</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Avg Jitter</h3>
                        <div class="value"><?= $analytics['avg_jitter'] ?><span class="unit"> ms</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Countries</h3>
                        <div class="value"><?= $analytics['unique_countries'] ?></div>
                    </div>
                </div>

                <div class="charts-grid">
                    <div class="chart-container full-width">
                        <h3>Tests per Day (Last 30 Days)</h3>
                        <div class="chart-wrapper">
                            <canvas id="testsPerDayChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container full-width">
                        <h3>Recent Speed Trends</h3>
                        <div class="chart-wrapper">
                            <canvas id="speedTrendChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3>Download Speed Distribution (Mbps)</h3>
                        <div class="chart-wrapper">
                            <canvas id="downloadDistChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3>Upload Speed Distribution (Mbps)</h3>
                        <div class="chart-wrapper">
                            <canvas id="uploadDistChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3>Ping Distribution (ms)</h3>
                        <div class="chart-wrapper">
                            <canvas id="pingDistChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3>Browser Distribution</h3>
                        <div class="chart-wrapper">
                            <canvas id="browserChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3>Top Countries</h3>
                        <div class="chart-wrapper">
                            <canvas id="countryChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3>Top Cities</h3>
                        <div class="chart-wrapper">
                            <canvas id="cityChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container full-width">
                        <h3>Top ISPs</h3>
                        <div class="chart-wrapper">
                            <canvas id="ispChart"></canvas>
                        </div>
                    </div>
                </div>

                <script>
                    // Color palette
                    const colors = {
                        primary: 'hsl(198, 72%, 35%)',
                        primaryLight: 'hsl(198, 72%, 55%)',
                        download: 'rgba(46, 204, 113, 0.8)',
                        upload: 'rgba(155, 89, 182, 0.8)',
                        ping: 'rgba(241, 196, 15, 0.8)',
                        chartColors: [
                            'rgba(52, 152, 219, 0.8)',
                            'rgba(46, 204, 113, 0.8)',
                            'rgba(155, 89, 182, 0.8)',
                            'rgba(241, 196, 15, 0.8)',
                            'rgba(231, 76, 60, 0.8)',
                            'rgba(149, 165, 166, 0.8)'
                        ]
                    };

                    // Tests per day chart
                    const testsPerDayData = <?= json_encode($analytics['tests_by_day']) ?>;
                    new Chart(document.getElementById('testsPerDayChart'), {
                        type: 'bar',
                        data: {
                            labels: testsPerDayData.map(d => d.day),
                            datasets: [{
                                label: 'Tests',
                                data: testsPerDayData.map(d => d.count),
                                backgroundColor: colors.primary,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });

                    // Speed trends chart
                    const recentSpeeds = <?= json_encode($analytics['recent_speeds']) ?>;
                    new Chart(document.getElementById('speedTrendChart'), {
                        type: 'line',
                        data: {
                            labels: recentSpeeds.map((d, i) => i + 1),
                            datasets: [{
                                label: 'Download (Mbps)',
                                data: recentSpeeds.map(d => parseFloat(d.dl) || 0),
                                borderColor: colors.download,
                                backgroundColor: 'transparent',
                                tension: 0.3
                            }, {
                                label: 'Upload (Mbps)',
                                data: recentSpeeds.map(d => parseFloat(d.ul) || 0),
                                borderColor: colors.upload,
                                backgroundColor: 'transparent',
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'top' }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });

                    // Download distribution chart
                    const downloadDist = <?= json_encode($analytics['download_distribution']) ?>;
                    new Chart(document.getElementById('downloadDistChart'), {
                        type: 'bar',
                        data: {
                            labels: Object.keys(downloadDist),
                            datasets: [{
                                label: 'Tests',
                                data: Object.values(downloadDist),
                                backgroundColor: colors.download,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });

                    // Upload distribution chart
                    const uploadDist = <?= json_encode($analytics['upload_distribution']) ?>;
                    new Chart(document.getElementById('uploadDistChart'), {
                        type: 'bar',
                        data: {
                            labels: Object.keys(uploadDist),
                            datasets: [{
                                label: 'Tests',
                                data: Object.values(uploadDist),
                                backgroundColor: colors.upload,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });

                    // Ping distribution chart
                    const pingDist = <?= json_encode($analytics['ping_distribution']) ?>;
                    new Chart(document.getElementById('pingDistChart'), {
                        type: 'bar',
                        data: {
                            labels: Object.keys(pingDist),
                            datasets: [{
                                label: 'Tests',
                                data: Object.values(pingDist),
                                backgroundColor: colors.ping,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });

                    // Browser distribution chart
                    const browsers = <?= json_encode($analytics['browsers']) ?>;
                    new Chart(document.getElementById('browserChart'), {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(browsers),
                            datasets: [{
                                data: Object.values(browsers),
                                backgroundColor: colors.chartColors
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right' }
                            }
                        }
                    });

                    // Country distribution chart
                    const countries = <?= json_encode($analytics['countries']) ?>;
                    new Chart(document.getElementById('countryChart'), {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(countries),
                            datasets: [{
                                data: Object.values(countries),
                                backgroundColor: [
                                    'rgba(52, 152, 219, 0.8)',
                                    'rgba(46, 204, 113, 0.8)',
                                    'rgba(155, 89, 182, 0.8)',
                                    'rgba(241, 196, 15, 0.8)',
                                    'rgba(231, 76, 60, 0.8)',
                                    'rgba(26, 188, 156, 0.8)',
                                    'rgba(230, 126, 34, 0.8)',
                                    'rgba(149, 165, 166, 0.8)',
                                    'rgba(52, 73, 94, 0.8)',
                                    'rgba(127, 140, 141, 0.8)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right' }
                            }
                        }
                    });

                    // City distribution chart
                    const cities = <?= json_encode($analytics['cities']) ?>;
                    new Chart(document.getElementById('cityChart'), {
                        type: 'bar',
                        data: {
                            labels: Object.keys(cities),
                            datasets: [{
                                label: 'Tests',
                                data: Object.values(cities),
                                backgroundColor: 'rgba(26, 188, 156, 0.8)',
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: { beginAtZero: true }
                            }
                        }
                    });

                    // ISP distribution chart
                    const isps = <?= json_encode($analytics['isps']) ?>;
                    new Chart(document.getElementById('ispChart'), {
                        type: 'bar',
                        data: {
                            labels: Object.keys(isps),
                            datasets: [{
                                label: 'Tests',
                                data: Object.values(isps),
                                backgroundColor: 'rgba(230, 126, 34, 0.8)',
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: { beginAtZero: true }
                            }
                        }
                    });
                </script>
                <?php
                }
            }
        } elseif ($_GET['op'] === 'login' && $_POST['password'] === $stats_password) {
            $_SESSION['logged'] = true;
            ?><script type="text/javascript">window.location=location.protocol+"//"+location.host+location.pathname;</script><?php
        } else {
            ?>
            <form action="analytics.php?op=login" method="POST" class="login-form">
                <h3>Login</h3>
                <input type="password" name="password" placeholder="Password" value=""/>
                <input type="submit" value="Login" />
            </form>
            <?php
        }
        ?>
    </body>
</html>
