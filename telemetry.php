<?php
$db_type="sqlite"; //Type db mysql or sqlite

$ip=($_SERVER['REMOTE_ADDR']);
$ua=($_SERVER['HTTP_USER_AGENT']);
$lang=($_SERVER['HTTP_ACCEPT_LANGUAGE']);
$dl=($_POST["dl"]);
$ul=($_POST["ul"]);
$ping=($_POST["ping"]);
$jitter=($_POST["jitter"]);
$log=($_POST["log"]);

if($db_type=="mysql"){
        $MySql_username="USERNAME";
        $MySql_password="PASSWORD";
        $MySql_hostname="DB_HOSTNAME";
        $MySql_databasename="DB_NAME";


    $conn = new mysqli($MySql_hostname, $MySql_username, $MySql_password, $MySql_databasename) or die("1");
    $stmt = $conn->prepare("INSERT INTO speedtest_users (ip,ua,lang,dl,ul,ping,jitter,log) VALUES (?,?,?,?,?,?,?,?)") or die("2");
    $stmt->bind_param("ssssssss",$ip,$ua,$lang,$dl,$ul,$ping,$jitter,$log) or die("3");
    $stmt->execute() or die("4");
    $stmt->close() or die("5");
    $conn->close() or die("6");

}elseif($db_type=="sqlite"){

    $file_db = "../telemetry.sql";

    $conn = new PDO("sqlite:$file_db") or die("1");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `speedtest_users` (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        `timestamp`     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ip`    text NOT NULL,
        `ua`    text NOT NULL,
        `lang`  text NOT NULL,
        `dl`    text,
        `ul`    text,
        `ping`  text,
        `jitter`        text,
        `log`   longtext
        );
    ");
    $stmt = $conn->prepare("INSERT INTO speedtest_users (ip,ua,lang,dl,ul,ping,jitter,log) VALUES (?,?,?,?,?,?,?,?)") or die("2");
    $stmt->execute(array($ip,$ua,$lang,$dl,$ul,$ping,$jitter,$log)) or die("3");
    $conn = null;

}
?>
