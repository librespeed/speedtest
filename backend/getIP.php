<?php

/*
 * This script detects the client's IP address and fetches ISP info from ipinfo.io/
 * Output from this script is a JSON string composed of 2 objects: a string called processedString which contains the combined IP, ISP, Country and distance as it can be presented to the user; and an object called rawIspInfo which contains the raw data from ipinfo.io (or an empty string if isp detection is disabled or if it failed).
 * Client side, the output of this script can be treated as JSON or as regular text. If the output is regular text, it will be shown to the user as is.
 */

error_reporting(0);

define('API_KEY_FILE', 'getIP_ipInfo_apikey.php');
define('SERVER_LOCATION_CACHE_FILE', 'getIP_serverLocation.php');
define('OFFLINE_IPINFO_DB_FILE', 'country_asn.mmdb');

require_once 'getIP_util.php';

function getLocalOrPrivateIpInfo($ip){
    // ::1/128 is the only localhost ipv6 address. there are no others, no need to strpos this
    if ('::1' === $ip) {
        return 'localhost IPv6 access';
    }
    // simplified IPv6 link-local address (should match fe80::/10)
    if (stripos($ip, 'fe80:') === 0) {
        return 'link-local IPv6 access';
    }
    // fc00::/7 Unique Local IPv6 Unicast Addresses
    if (preg_match('/^(fc|fd)([0-9a-f]{0,4}:){1,7}[0-9a-f]{1,4}$/i', $ip) === 1) {
        return 'ULA IPv6 access';
    }
    // anything within the 127/8 range is localhost ipv4, the ip must start with 127.0
    if (strpos($ip, '127.') === 0) {
        return 'localhost IPv4 access';
    }
    // 10/8 private IPv4
    if (strpos($ip, '10.') === 0) {
        return 'private IPv4 access';
    }
    // 172.16/12 private IPv4
    if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip) === 1) {
        return 'private IPv4 access';
    }
    // 192.168/16 private IPv4
    if (strpos($ip, '192.168.') === 0) {
        return 'private IPv4 access';
    }
    // IPv4 link-local
    if (strpos($ip, '169.254.') === 0) {
        return 'link-local IPv4 access';
    }
    return null;
}

function getIspInfo_ipinfoApi($ip){
    if (!file_exists(API_KEY_FILE) || !is_readable(API_KEY_FILE)){
        return null;
    }
    require API_KEY_FILE;
    if(empty($IPINFO_APIKEY)){
        return null;
    }
    $json = file_get_contents('https://ipinfo.io/' . $ip . '/json?token=' . $IPINFO_APIKEY);
    if (!is_string($json)) {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }
    $isp=null;
    //ISP name, if present, is either in org or asn.name
    if (array_key_exists('org', $data) && is_string($data['org']) && !empty($data['org'])) {
        // Remove AS##### from ISP name, if present
        $isp = preg_replace('/AS\\d+\\s/', '', $data['org']);
    } elseif (array_key_exists('asn', $data) && is_array($data['asn']) && !empty($data['asn']) && array_key_exists('name', $data['asn']) && is_string($data['asn']['name'])) {
        $isp = $data['asn']['name'];
    } else{
        return null;
    }
    $country=null;
    if(array_key_exists('country',$data) && is_string($data['country'])){
        $country = $data['country'];
    }
    //If requested by the client (and we have the required information), calculate the distance
    $distance=null;
    if(isset($_GET['distance']) && ($_GET['distance']==='mi' || $_GET['distance']==='km') && array_key_exists('loc', $data) && is_string($data['loc'])){
        $unit = $_GET['distance'];
        $clientLoc = $data['loc'];
        $serverLoc = null;
        if (file_exists(SERVER_LOCATION_CACHE_FILE) && is_readable(SERVER_LOCATION_CACHE_FILE)) {
            require SERVER_LOCATION_CACHE_FILE;
        }
        if (!is_string($serverLoc) || empty($serverLoc)) {
            $json = file_get_contents('https://ipinfo.io/json?token=' . $IPINFO_APIKEY);
            if (!is_string($json)) {
                return null;
            }
            $sdata = json_decode($json, true);
            if (!is_array($sdata) || !array_key_exists('loc', $sdata) || !is_string($sdata['loc']) || empty($sdata['loc'])) {
                return null;
            }
            $serverLoc = $sdata['loc'];
            file_put_contents(SERVER_LOCATION_CACHE_FILE, "<?php\n\n\$serverLoc = '" . addslashes($serverLoc) . "';\n");
        }
        list($clientLatitude, $clientLongitude) = explode(',', $clientLoc);
        list($serverLatitude, $serverLongitude) = explode(',', $serverLoc);
        //distance calculation adapted from http://www.codexworld.com
        $rad = M_PI / 180;
        $dist = acos(sin($clientLatitude * $rad) * sin($serverLatitude * $rad) + cos($clientLatitude * $rad) * cos($serverLatitude * $rad) * cos(($clientLongitude - $serverLongitude) * $rad)) / $rad * 60 * 1.853;
        if ($unit === 'mi') {
            $dist /= 1.609344;
            $dist = round($dist, -1);
            if ($dist < 15) {
                $dist = '<15';
            }
            $distance = $dist . ' mi';
        }elseif ($unit === 'km') {
            $dist = round($dist, -1);
            if ($dist < 20) {
                $dist = '<20';
            }
            $distance =  $dist . ' km';
        }
    }
    $processedString=$ip.' - '.$isp;
    if(is_string($country)){
        $processedString.=', '.$country;
    }
    if(is_string($distance)){
        $processedString.=' ('.$distance.')';
    }
    return json_encode([
        'processedString' => $processedString,
        'rawIspInfo' => $data ?: '',
    ]);
}

if (PHP_MAJOR_VERSION >= 8){
    require_once("geoip2.phar");
}
function getIspInfo_ipinfoOfflineDb($ip){
    if (PHP_MAJOR_VERSION < 8 || !file_exists(OFFLINE_IPINFO_DB_FILE) || !is_readable(OFFLINE_IPINFO_DB_FILE)){
        return null;
    }
    $reader = new MaxMind\Db\Reader(OFFLINE_IPINFO_DB_FILE);
    $data = $reader->get($ip);
    if(!is_array($data)){
        return null;
    }
    $processedString = $ip.' - ' . $data['as_name'] . ', ' . $data['country_name'];
    return json_encode([
        'processedString' => $processedString,
        'rawIspInfo' => $data ?: '',
    ]);
}

function formatResponse_simple($ip,$ispName=null){
    $processedString=$ip;
    if(is_string($ispName)){
        $processedString.=' - '.$ispName;
    }
    return json_encode([
        'processedString' => $processedString,
        'rawIspInfo' => '',
    ]);
}

header('Content-Type: application/json; charset=utf-8');
if (isset($_GET['cors'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$ip = getClientIp();
//if the user requested the ISP info, we first try to fetch it using ipinfo.io (if there is no api key set it fails without sending data, it can also fail because of rate limiting or invalid responses), then we try with the offline db, if that also fails (or if ISP info was not requested) we just respond with the IP address
if(isset($_GET['isp'])){
    $localIpInfo = getLocalOrPrivateIpInfo($ip);
    //local ip, no need to fetch further information
    if (is_string($localIpInfo)) {
        echo formatResponse_simple($ip,$localIpInfo);
    }else{
        $r=getIspInfo_ipinfoApi($ip);
        if(!is_null($r)){
            echo $r;
        }else{
            $r=getIspInfo_ipinfoOfflineDb($ip);
            if(!is_null($r)){
                echo $r;
            }else{
                echo formatResponse_simple($ip);
            }
        }
    }
}else{
    echo formatResponse_simple($ip);
}
