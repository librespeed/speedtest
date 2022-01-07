<?php

require 'telemetry_settings.php';
require_once 'telemetry_db.php';
require_once '../backend/getIP_util.php';

$ip = getClientIp();
$ispinfo = $_POST['ispinfo'];
$extra = $_POST['extra'];
$ua = $_SERVER['HTTP_USER_AGENT'];
$lang = '';
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
}
$dl = $_POST['dl'];
$ul = $_POST['ul'];
$ping = $_POST['ping'];
$jitter = $_POST['jitter'];
$log = $_POST['log'];

if (isset($redact_ip_addresses) && true === $redact_ip_addresses) {
    $ip = '0.0.0.0';
    $ipv4_regex = '/(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/';
    $ipv6_regex = '/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/';
    $hostname_regex = '/"hostname":"([^\\\\"]|\\\\")*"/';
    $ispinfo = preg_replace($ipv4_regex, '0.0.0.0', $ispinfo);
    $ispinfo = preg_replace($ipv6_regex, '0.0.0.0', $ispinfo);
    $ispinfo = preg_replace($hostname_regex, '"hostname":"REDACTED"', $ispinfo);
    $log = preg_replace($ipv4_regex, '0.0.0.0', $log);
    $log = preg_replace($ipv6_regex, '0.0.0.0', $log);
    $log = preg_replace($hostname_regex, '"hostname":"REDACTED"', $log);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$id = insertSpeedtestUser($ip, $ispinfo, $extra, $ua, $lang, $dl, $ul, $ping, $jitter, $log);
if (false === $id) {
    exit(1);
}

echo 'id '.$id;
