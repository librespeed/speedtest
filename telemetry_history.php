<?php
//returns a json history of recent speedtests
header('Content-Type: application/json');
include_once('telemetry_settings.php');
include_once('ip_in_range.php'); // delivers functions for comparing IP Ranges



//define admin IP-range wich are allowed to see full history
$admin_v6 = "2001:db8::/32";
$admin_v4 = "203.0.113.0/24";
$ip=($_SERVER['REMOTE_ADDR']);

$data = array();

if(ipv6_in_range(ip2long6($ip), $admin_v6) OR ipv4_in_range(ip2long($ip), $admin_v4)){
 	//admin
	$mysql_query = "SELECT `timestamp` as 'Time', `ip` as 'IP',`dl` as 'Download',`ul` as 'Upload',`ping` as 'Latency',`jitter`, `ua` as 'UserAgent' FROM `speedtest_users` ORDER BY `timestamp` DESC limit 0,30";

}
else {
	//regular user
	$mysql_query = "SELECT `timestamp` as 'Time', `ip` as 'IP',`dl` as 'Download',`ul` as 'Upload',`ping` as 'Latency',`jitter` as 'Jitter' FROM `speedtest_users` WHERE `ip` = '".$ip."' ORDER BY `timestamp` DESC limit 0,20";
	}

if($db_type=="mysql"){
    $conn = new mysqli($MySql_hostname, $MySql_username, $MySql_password, $MySql_databasename) or die("1");

    if ($result = $conn->query($mysql_query) or die("2")) {
        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
                $data[] = $row;
        }
    }
    $conn->close() or die("3");

}elseif($db_type=="sqlite"){
	$data [] = array("info" => "DB Type not supported, yet");
}elseif($db_type=="postgresql"){
	$data [] = array("info" => "DB Type not supported, yet");
}

echo json_encode($data);

?>
