<?php
session_start();
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
<title>HTML5 Speedtest - Stats</title>
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
	div.blueTable {
		border: 1px solid #1C6EA4;
		background-color: #EEEEEE;
		width: 100%;
		text-align: left;
		border-collapse: collapse;
	}
	.divTable.blueTable .divTableCell, .divTable.blueTable .divTableHead {
		border: 1px solid #AAAAAA;
		padding: 3px 2px;
	}
	.divTable.blueTable .divTableBody .divTableCell {
		font-size: 13px;
	}
	.divTable.blueTable .divTableRow:nth-child(even) {
		background: #D0E4F5;
	}
	.divTable.blueTable .divTableHeading {
		background: #1C6EA4;
		background: -moz-linear-gradient(top, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
		background: -webkit-linear-gradient(top, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
		background: linear-gradient(to bottom, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
		border-bottom: 2px solid #444444;
	}
	.divTable.blueTable .divTableHeading .divTableHead {
		font-size: 15px;
		font-weight: bold;
		color: #FFFFFF;
		border-left: 2px solid #D0E4F5;
	}
	.divTable.blueTable .divTableHeading .divTableHead:first-child {
		border-left: none;
	}

	.blueTable .tableFootStyle {
		font-size: 14px;
		font-weight: bold;
		color: #FFFFFF;
		background: #D0E4F5;
		background: -moz-linear-gradient(top, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
		background: -webkit-linear-gradient(top, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
		background: linear-gradient(to bottom, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
		border-top: 2px solid #444444;
	}
	.blueTable .tableFootStyle {
		font-size: 14px;
	}
	.blueTable .tableFootStyle .links {
		text-align: right;
	}
	.blueTable .tableFootStyle .links a{
		display: inline-block;
		background: #1C6EA4;
		color: #FFFFFF;
		padding: 2px 8px;
		border-radius: 5px;
	}
	.blueTable.outerTableFooter {
		border-top: none;
	}
	.blueTable.outerTableFooter .tableFootStyle {
		padding: 3px 5px; 
	}
	.divTable{ display: table; }
	.divTableRow { display: table-row; }
	.divTableHeading { display: table-header-group;}
	.divTableCell, .divTableHead { display: table-cell;}
	.divTableHeading { display: table-header-group;}
	.divTableFoot { display: table-footer-group;}
	.divTableBody { display: table-row-group;}
</style>
</head>
<body>
<h1>HTML5 Speedtest - Stats</h1>
<?php
include_once('telemetryEntry.php');
require "idObfuscation.php";

if($stats_password=="PASSWORD"){
	?>
		Please set $stats_password in telemetry_settings.php to enable access.
	<?php
} else if ($_SESSION["logged"]===true){
	if ($_GET["op"]=="logout"){
		$_SESSION["logged"]=false;
		?><script type="text/javascript">window.location=location.protocol+"//"+location.host+location.pathname;</script><?php
	} else {
?>
	<form action="stats.php" method="GET"><input type="hidden" name="op" value="logout" /><input type="submit" value="Logout" /></form>
	<form action="stats.php" method="GET">
		<h3>Search test results</h6>
		<input type="hidden" name="op" value="id" />
		<input type="text" name="id" id="id" placeholder="Test ID" value=""/>
		<input type="submit" value="Find" />
		<input type="submit" onclick="document.getElementById('id').value=''" value="Show last 100 tests" />
	</form>
	<div class="divTable blueTable">
		<div class="divTableHeading">
			<div class="divTableRow">
				<div class="divTableHead">Test ID</div>
				<div class="divTableHead">Date and time</div>
				<div class="divTableHead">IP and ISP Info</div>
				<div class="divTableHead">User agent and locale</div>
				<div class="divTableHead">Download speed</div>
				<div class="divTableHead">Upload speed</div>
				<div class="divTableHead">Ping</div>
				<div class="divTableHead">Jitter</div>
				<div class="divTableHead">Log</div>
				<div class="divTableHead">Extra info</div>
			</div>
		</div>
		<div class="divTableBody">
	<?php
		$entries = null;
		if($_GET["op"]=="id"&&!empty($_GET["id"])){
			$id=$_GET["id"];
			if ($enable_id_obfuscation) $id = deobfuscateId($id);
			$entries = array($repository->find($id));
		}else{
			$entries = $repository->findAll();
		}
	foreach ($entries as $entry) {
		$obfuscateId = ($enable_id_obfuscation ? obfuscateId($entry->id) : $entry->id);
	 ?>
	 	<div class="divTableRow">
	 		<div class="divTableCell"><a href="/results?id=<?=htmlspecialchars($obfuscateId, ENT_HTML5, 'UTF-8') ?>"><?=htmlspecialchars($obfuscateId, ENT_HTML5, 'UTF-8') ?></a></div>
	 		<div class="divTableCell" style="white-space:nowrap"><?=htmlspecialchars($entry->datetime, ENT_HTML5, 'UTF-8') ?></div>
	 		<div class="divTableCell"><?=$entry->ip ?><br/><?=htmlspecialchars($entry->ispinfo, ENT_HTML5, 'UTF-8') ?></div>
	 		<div class="divTableCell"><?=$entry->useragent ?><br/><?=htmlspecialchars($entry->locale, ENT_HTML5, 'UTF-8') ?></div>
	 		<div class="divTableCell"><?=htmlspecialchars($entry->downloadspeed, ENT_HTML5, 'UTF-8') ?></div>
	 		<div class="divTableCell"><?=htmlspecialchars($entry->uploadspeed, ENT_HTML5, 'UTF-8') ?></div>
	 		<div class="divTableCell"><?=htmlspecialchars($entry->ping, ENT_HTML5, 'UTF-8') ?></div>
	 		<div class="divTableCell"><?=htmlspecialchars($entry->jitter, ENT_HTML5, 'UTF-8') ?></div>
	 		<div class="divTableCell"><?=htmlspecialchars($entry->log, ENT_HTML5, 'UTF-8') ?></div>
	 		<div class="divTableCell"><?=htmlspecialchars($entry->extra, ENT_HTML5, 'UTF-8') ?></div>
	 	</div>
	 <?php
	 	}
	 ?>
		</div>
</div>
<?php
	}
} else {
	if($_GET["op"]=="login"&&$_POST["password"]===$stats_password){
		$_SESSION["logged"]=true;
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
}
?>
</body>
</html>
