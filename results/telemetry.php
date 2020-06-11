<?php
include_once('telemetry_settings.php');
require 'idObfuscation.php';

$ip=($_SERVER['REMOTE_ADDR']);
$ispinfo=($_POST["ispinfo"]);
$extra=($_POST["extra"]);
$ua=($_SERVER['HTTP_USER_AGENT']);
$lang=""; if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $lang=($_SERVER['HTTP_ACCEPT_LANGUAGE']);
$dl=($_POST["dl"]);
$ul=($_POST["ul"]);
$ping=($_POST["ping"]);
$jitter=($_POST["jitter"]);
$log=($_POST["log"]);

if($redact_ip_addresses){
    $ip="0.0.0.0";
    $ipv4_regex='/(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/';
    $ipv6_regex='/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/';
    $hostname_regex='/"hostname":"([^\\\\"]|\\\\")*"/';
    $ispinfo=preg_replace($ipv4_regex,"0.0.0.0",$ispinfo);
    $ispinfo=preg_replace($ipv6_regex,"0.0.0.0",$ispinfo);
    $ispinfo=preg_replace($hostname_regex,"\"hostname\":\"REDACTED\"",$ispinfo);
    $log=preg_replace($ipv4_regex,"0.0.0.0",$log);
    $log=preg_replace($ipv6_regex,"0.0.0.0",$log);
    $log=preg_replace($hostname_regex,"\"hostname\":\"REDACTED\"",$log);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if($db_type=="mysql"){
    $conn = new mysqli($MySql_hostname, $MySql_username, $MySql_password, $MySql_databasename, $MySql_port) or die("1");
    $stmt = $conn->prepare("INSERT INTO speedtest_users (ip,ispinfo,extra,ua,lang,dl,ul,ping,jitter,log) VALUES (?,?,?,?,?,?,?,?,?,?)") or die("2");
    $stmt->bind_param("ssssssssss",$ip,$ispinfo,$extra,$ua,$lang,$dl,$ul,$ping,$jitter,$log) or die("3");
	$stmt->execute() or die("4");
    $stmt->close() or die("5");
	$id=$conn->insert_id;
	echo "id ".($enable_id_obfuscation?obfuscateId($id):$id);
    $conn->close() or die("6");

}elseif($db_type=="sqlite"){
    $conn = new PDO("sqlite:$Sqlite_db_file") or die("1");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `speedtest_users` (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
		`ispinfo`    text,
		`extra`    text,
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
    $stmt = $conn->prepare("INSERT INTO speedtest_users (ip,ispinfo,extra,ua,lang,dl,ul,ping,jitter,log) VALUES (?,?,?,?,?,?,?,?,?,?)") or die("2");
    $stmt->execute(array($ip,$ispinfo,$extra,$ua,$lang,$dl,$ul,$ping,$jitter,$log)) or die("3");
	$id=$conn->lastInsertId();
	echo "id ".($enable_id_obfuscation?obfuscateId($id):$id);
    $conn = null;
}elseif($db_type=="postgresql"){
    // Prepare connection parameters for db connection
    $conn_host = "host=$PostgreSql_hostname";
    $conn_db = "dbname=$PostgreSql_databasename";
    $conn_user = "user=$PostgreSql_username";
    $conn_password = "password=$PostgreSql_password";
    // Create db connection
    $conn = new PDO("pgsql:$conn_host;$conn_db;$conn_user;$conn_password") or die("1");
    $stmt = $conn->prepare("INSERT INTO speedtest_users (ip,ispinfo,extra,ua,lang,dl,ul,ping,jitter,log) VALUES (?,?,?,?,?,?,?,?,?,?)") or die("2");
    $stmt->execute(array($ip,$ispinfo,$extra,$ua,$lang,$dl,$ul,$ping,$jitter,$log)) or die("3");
	$id=$conn->lastInsertId();
	echo "id ".($enable_id_obfuscation?obfuscateId($id):$id);
    $conn = null;
}
else die("-1");
?>
