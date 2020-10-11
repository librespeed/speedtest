<?php
session_start();
error_reporting(0);

require 'telemetry_settings.php';
require_once 'telemetry_db.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html>
    <head>
        <title>LibreSpeed - Stats</title>
        <style type="text/css">
            html,body{
                margin:0;
                padding:0;
                border:none;
                width:100%; min-height:100%;
            }
            html{
                background-color: hsl(198,72%,35%);
                font-family: "Segoe UI","Roboto",sans-serif;
            }
            body{
                background-color:#FFFFFF;
                box-sizing:border-box;
                width:100%;
                max-width:70em;
                margin:4em auto;
                box-shadow:0 1em 6em #00000080;
                padding:1em 1em 4em 1em;
                border-radius:0.4em;
            }
            h1,h2,h3,h4,h5,h6{
                font-weight:300;
                margin-bottom: 0.1em;
            }
            h1{
                text-align:center;
            }
            table{
                margin:2em 0;
                width:100%;
            }
            table, tr, th, td {
                border: 1px solid #AAAAAA;
            }
            th {
                width: 6em;
            }
            td {
                word-break: break-all;
            }
            div {
                margin: 1em 0;
            }
        </style>
    </head>
    <body>
        <h1>LibreSpeed - Stats</h1>
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
                ?>
                <form action="stats.php" method="GET"><input type="hidden" name="op" value="logout" /><input type="submit" value="Logout" /></form>
                <form action="stats.php" method="GET">
                    <h3>Search test results</h3>
                    <input type="hidden" name="op" value="id" />
                    <input type="text" name="id" id="id" placeholder="Test ID" value=""/>
                    <input type="submit" value="Find" />
                    <input type="submit" onclick="document.getElementById('id').value=''" value="Show last 100 tests" />
                </form>
                <?php
                if ($_GET['op'] === 'id' && !empty($_GET['id'])) {
                    $speedtest = getSpeedtestUserById($_GET['id']);
                    $speedtests = [];
                    if (false === $speedtest) {
                        echo '<div>There was an error trying to fetch the speedtest result for ID "'.$_GET['id'].'".</div>';
                    } elseif (null === $speedtest) {
                        echo '<div>Could not find a speedtest result for ID "'.$_GET['id'].'".</div>';
                    } else {
                        $speedtests = [$speedtest];
                    }
                } else {
                    $speedtests = getLatestSpeedtestUsers();
                    if (false === $speedtests) {
                        echo '<div>There was an error trying to fetch latest speedtest results.</div>';
                    } elseif (empty($speedtests)) {
                        echo '<div>Could not find any speedtest results in database.</div>';
                    }
                }
                foreach ($speedtests as $speedtest) {
                    ?>
                    <table>
                        <tr>
                            <th>Test ID</th>
                            <td><?= htmlspecialchars($speedtest['id_formatted'], ENT_HTML5, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Date and time</th>
                            <td><?= htmlspecialchars($speedtest['timestamp'], ENT_HTML5, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>IP and ISP Info</th>
                            <td>
                                <?= htmlspecialchars($speedtest['ip'], ENT_HTML5, 'UTF-8') ?><br/>
                                <?= htmlspecialchars($speedtest['ispinfo'], ENT_HTML5, 'UTF-8') ?>
                            </td>
                        </tr>
                        <tr>
                            <th>User agent and locale</th>
                            <td><?= htmlspecialchars($speedtest['ua'], ENT_HTML5, 'UTF-8') ?><br/>
                                <?= htmlspecialchars($speedtest['lang'], ENT_HTML5, 'UTF-8') ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Download speed</th>
                            <td><?= htmlspecialchars($speedtest['dl'], ENT_HTML5, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Upload speed</th>
                            <td><?= htmlspecialchars($speedtest['ul'], ENT_HTML5, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Ping</th>
                            <td><?= htmlspecialchars($speedtest['ping'], ENT_HTML5, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Jitter</th>
                            <td><?= htmlspecialchars($speedtest['jitter'], ENT_HTML5, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Log</th>
                            <td><?= htmlspecialchars($speedtest['log'], ENT_HTML5, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Extra info</th>
                            <td><?= htmlspecialchars($speedtest['extra'], ENT_HTML5, 'UTF-8') ?></td>
                        </tr>
                    </table>
                    <?php
                }
            }
        } elseif ($_GET['op'] === 'login' && $_POST['password'] === $stats_password) {
            $_SESSION['logged'] = true;
            ?><script type="text/javascript">window.location=location.protocol+"//"+location.host+location.pathname;</script><?php
        } else {
            ?>
            <form action="stats.php?op=login" method="POST">
                <h3>Login</h3>
                <input type="password" name="password" placeholder="Password" value=""/>
                <input type="submit" value="Login" />
            </form>
            <?php
        }
        ?>
    </body>
</html>
