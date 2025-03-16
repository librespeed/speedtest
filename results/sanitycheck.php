<?php
require_once 'telemetry_settings.php';
require_once 'telemetry_db.php';
require_once '../backend/getIP_util.php';

error_reporting(E_ALL);

$pass="<span class='Pass'>Pass</span>";
$failed="<span class='Failed'>failed</span>";
$na="<span class='na'>N/A</span>";
?>

<html>
<head>
<title>Speed Test installation sanity check</title>
<style>
	table,th,td { border: 1px solid;}
	.Pass   { color:green;}
	.Failed { color:red;}
	.na     { color:orange;}
	.SectionHeading { font-style: italic;}
</style>
</head>
<body>
<table><tr><th>Item</th><th>Status</th><th>Comment</th></tr>
<tr><td colspan="3" class='SectionHeading'>PHP extensions</td></tr>

<tr><td>gd</td><td>
<?php
if(extension_loaded('gd')){
	echo $pass;
} else {
	echo $failed;
}
?>
</td><td>Used to render result images.</td></tr>

<tr><td>openssl</td><td>
<?php
if(extension_loaded('openssl')){
	echo $pass;
} else {
	echo $failed;
}
?>
</td><td></td></tr>

<tr><td>pdo_sqlsrv</td><td>
<?php
if(!isset($db_type) || $db_type != 'mssql'){
	echo $na;
}elseif(extension_loaded('pdo_sqlsrv')){
	echo $pass;
} else {
	echo $failed;
}
?>
</td><td>Only required if using MS SQL.</td></tr>

<tr><td>pdo_mysql</td><td>
<?php
if(!isset($db_type) || $db_type != 'mysql'){
	echo $na;
}elseif(extension_loaded('pdo_mysql')){
	echo $pass;
} else {
	echo $failed;
}
?>
</td><td>Only required if using mysql.</td></tr>

<tr><td>pdo_sqlite</td><td>
<?php
if(!isset($db_type) || $db_type != 'sqlite'){
	echo $na;
}elseif(extension_loaded('pdo_sqlite')){
	echo $pass;
} else {
	echo $failed;
}
?>
</td><td>Only required if using sqlite.</td></tr>


<tr><td>pdo_pgsql</td><td>
<?php
if(!isset($db_type) || $db_type != 'postgresql'){
	echo $na;
}elseif(extension_loaded('pdo_pgsql')){
	echo $pass;
} else {
	echo $failed;
}
?>
</td><td>Only required if using postgresql.</td></tr>


<tr><td colspan="3" class='SectionHeading'>Database check</td></tr>
<tr><td>Connecting to DB</td><td>
<?php
	$pdo = getPdo(true);
	if (($pdo instanceof PDO)) {
		echo $pass;
		echo "</td><td></td>";
	} else {
		echo $failed;
		echo "</td><td>". htmlspecialchars($pdo) . "</td>";
	}
?>
</tr>

<tr><td>Insert into DB</td><td>
<?php
	$ip = getClientIp();
	$ispinfo="";
	$extra='{"DBTest":"This is a simple test of the database.  No speed test was done."}';
	$ua = $_SERVER['HTTP_USER_AGENT'];
	$lang = '';
	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
	}

	$dl=$ul=$ping=$jitter="";
	$log="";

	$insertResult = insertSpeedtestUser($ip, $ispinfo, $extra, $ua, $lang, $dl, $ul, $ping, $jitter, $log, true);
	
	if(($insertResult instanceof Exception)){
		echo $failed;
		echo "</td><td>";
		echo htmlspecialchars($insertResult->getMessage()) . "</td>";
	} else {
		echo $pass;
		echo "</td><td></td>";
	}
?>
</tr>

<tr><td>Read from DB</td><td>
<?php
	if(!($insertResult instanceof Exception)){
		$QueryResult = getSpeedtestUserById($insertResult,true);
		
		if(($QueryResult instanceof Exception)){
			echo $failed;
			echo "</td><td>";
			echo htmlspecialchars($insertResult->getMessage()) . "</td>";
		} elseif(!is_array($QueryResult)) {
			echo $failed;
			echo "</td><td>Test result not retrieved from database.</td>";
		} else {
			echo $pass;
			echo "</td><td></td>";
		}
	} else {
		echo "</td><td>Insert failed so can't test reading inserted data</td>";
	}
?>
</tr>

</table>
</body>
</html>
<?php
/*
$speedtests = getLatestSpeedtestUsers();
print_r ($speedtests);


echo '   ';
print_r($pdo);
if(!isset($pdo)){
	echo 'got nothing';
}
if($pdo == false){
	echo 'got a false';
}
if (!($pdo instanceof PDO)) {
    echo 'not a PDO';
}
if (($pdo instanceof PDO)) {
    echo 'is PDO';
}


$speedtest = getSpeedtestUserById(1);
print_r ($speedtest);
*/
?>
