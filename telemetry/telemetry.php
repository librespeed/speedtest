<?php
include_once 'telemetryEntry.php';
require 'idObfuscation.php';

$entry = new TelemetryEntry();
$entry->ip = ($_SERVER['REMOTE_ADDR']);
$entry->ispinfo = ($_POST["ispinfo"]);
$entry->extra = ($_POST["extra"]);
$entry->useragent = ($_SERVER['HTTP_USER_AGENT']);
$entry->locale = ($_SERVER['HTTP_ACCEPT_LANGUAGE']) ?? "";
$entry->downloadspeed = ($_POST["dl"]);
$entry->uploadspeed = ($_POST["ul"]);
$entry->ping = ($_POST["ping"]);
$entry->jitter = ($_POST["jitter"]);
$entry->log = ($_POST["log"]);

$id = $repository->add($entry);
echo "id " . ($enable_id_obfuscation ? obfuscateId($id) : $id);
?>
