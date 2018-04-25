<?php
include_once('telemetry_settings.php');

$ip=($_SERVER['REMOTE_ADDR']);
$ua=($_SERVER['HTTP_USER_AGENT']);
$lang=""; if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $lang=($_SERVER['HTTP_ACCEPT_LANGUAGE']);
$dl=($_POST["dl"]);
$ul=($_POST["ul"]);
$ping=($_POST["ping"]);
$jitter=($_POST["jitter"]);
$log=($_POST["log"]);

if($db_type=="mysql"){
    $conn = new mysqli($MySql_hostname, $MySql_username, $MySql_password, $MySql_databasename) or die("1");
    $stmt = $conn->prepare("INSERT INTO speedtest_users (ip,ua,lang,dl,ul,ping,jitter,log) VALUES (?,?,?,?,?,?,?,?)") or die("2");
    $stmt->bind_param("ssssssss",$ip,$ua,$lang,$dl,$ul,$ping,$jitter,$log) or die("3");
    $stmt->execute() or die("4");
    $stmt->close() or die("5");
    $conn->close() or die("6");

}elseif($db_type=="sqlite"){
    $conn = new PDO("sqlite:$Sqlite_db_file") or die("1");
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
}elseif($db_type=="postgresql"){
    // Prepare connection parameters for db connection
    $conn_host = "host=$PostgreSql_hostname";
    $conn_db = "dbname=$PostgreSql_databasename";
    $conn_user = "user=$PostgreSql_username";
    $conn_password = "password=$PostgreSql_password";
    // Create db connection
    $conn = new PDO("pgsql:$conn_host;$conn_db;$conn_user;$conn_password") or die("1");
    $stmt = $conn->prepare("INSERT INTO speedtest_users (ip,ua,lang,dl,ul,ping,jitter,log) VALUES (?,?,?,?,?,?,?,?)") or die("2");
    $stmt->execute(array($ip,$ua,$lang,$dl,$ul,$ping,$jitter,$log)) or die("3");
    $conn = null;
}
elseif($db_type=="csv"){
    // Prepare the csv formatted string
    date_default_timezone_set($timezone);
    $date = date('Y-m-d H:i:s');
    $str = '"' . $date . '",';
    $str .= '"' . $ip . '",';
    $str .= '"' . $ua . '",';
    $str .= '"' . $dl . '",';
    $str .= '"' . $ul . '",';
    $str .= '"' . $ping . '",';
    $str .= '"' . $jitter . '"' . "\n";

    // Set header if this is a new file
    if (!file_exists($Csv_File)) {
        $header = '"date","ip","ua","download","upload","ping","jitter"' . "\n";
        file_put_contents($Csv_File, $header, FILE_APPEND);
    }

    // Writting line to file
    file_put_contents($Csv_File, $str, FILE_APPEND);
}
?>
