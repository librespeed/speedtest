<?php

/*
 * This script detects the client's IP address and fetches ISP info from ipinfo.io.
 * It outputs a JSON string with two fields: 'processedString' (containing combined IP, ISP, country, and distance)
 * and 'rawIspInfo' (containing raw data from ipinfo.io or an empty string if detection failed).
 * Client-side, the output can be treated as JSON or displayed as regular text.
 */

error_reporting(0);

define('API_KEY_FILE', 'getIP_ipInfo_apikey.php');
define('SERVER_LOCATION_CACHE_FILE', 'getIP_serverLocation.php');
define('OFFLINE_IPINFO_DB_FILE', 'country_asn.mmdb');

require_once 'getIP_util.php';

/**
 * Fetch the client's IP address.
 * @return string
 */
function getClientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'; // Default to localhost if IP is not available.
}

/**
 * Determine if the IP is local/private.
 * @param string $ip
 * @return string|null
 */
function getLocalOrPrivateIpInfo(string $ip): ?string {
    if ($ip === '::1') return 'localhost IPv6 access';
    if (stripos($ip, 'fe80:') === 0) return 'link-local IPv6 access';
    if (preg_match('/^(fc|fd)([0-9a-f]{0,4}:){1,7}[0-9a-f]{1,4}$/i', $ip)) return 'ULA IPv6 access';
    if (strpos($ip, '127.') === 0) return 'localhost IPv4 access';
    if (strpos($ip, '10.') === 0) return 'private IPv4 access';
    if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) return 'private IPv4 access';
    if (strpos($ip, '192.168.') === 0) return 'private IPv4 access';
    if (strpos($ip, '169.254.') === 0) return 'link-local IPv4 access';
    return null;
}

/**
 * Fetch ISP info from the ipinfo.io API.
 * @param string $ip
 * @return array|null
 */
function getIspInfo_ipinfoApi(string $ip): ?array {
    if (!file_exists(API_KEY_FILE) || !is_readable(API_KEY_FILE)) return null;
    require API_KEY_FILE;

    if (empty($IPINFO_APIKEY)) return null;

    $url = 'https://ipinfo.io/' . $ip . '/json?token=' . $IPINFO_APIKEY;

    $options = ['http' => ['timeout' => 5]];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) return null;

    $data = json_decode($response, true);
    if (!is_array($data)) return null;

    $isp = $data['org'] ?? $data['asn']['name'] ?? null;
    $isp = preg_replace('/AS\\d+\\s/', '', $isp); // Clean up AS info

    $country = $data['country'] ?? null;

    $distance = calculateDistance($data['loc'] ?? '', $ip);

    $processedString = buildProcessedString($ip, $isp, $country, $distance);

    return [
        'processedString' => $processedString,
        'rawIspInfo' => $data,
    ];
}

/**
 * Calculate the distance between the client and server based on location data.
 * @param string $clientLoc
 * @param string $ip
 * @return string|null
 */
function calculateDistance(string $clientLoc, string $ip): ?string {
    if (!isset($_GET['distance']) || empty($clientLoc)) return null;

    $unit = $_GET['distance'];
    if ($unit !== 'mi' && $unit !== 'km') return null;

    $serverLoc = getServerLocation();
    if (empty($serverLoc)) return null;

    [$clientLatitude, $clientLongitude] = explode(',', $clientLoc);
    [$serverLatitude, $serverLongitude] = explode(',', $serverLoc);

    // Calculate distance using haversine formula
    $rad = M_PI / 180;
    $dist = acos(sin($clientLatitude * $rad) * sin($serverLatitude * $rad)
        + cos($clientLatitude * $rad) * cos($serverLatitude * $rad)
        * cos(($clientLongitude - $serverLongitude) * $rad)) * 60 * 1.853;

    if ($unit === 'mi') {
        $dist /= 1.609344;
        $dist = round($dist, -1);
        $distance = $dist < 15 ? '<15 mi' : "{$dist} mi";
    } else {
        $dist = round($dist, -1);
        $distance = $dist < 20 ? '<20 km' : "{$dist} km";
    }

    return $distance;
}

/**
 * Get server location for distance calculation.
 * @return string|null
 */
function getServerLocation(): ?string {
    if (file_exists(SERVER_LOCATION_CACHE_FILE) && is_readable(SERVER_LOCATION_CACHE_FILE)) {
        require SERVER_LOCATION_CACHE_FILE;
        return $serverLoc ?? null;
    }
    return null;
}

/**
 * Build the processed string with IP, ISP, country, and distance.
 * @param string $ip
 * @param string|null $isp
 * @param string|null $country
 * @param string|null $distance
 * @return string
 */
function buildProcessedString(string $ip, ?string $isp, ?string $country, ?string $distance): string {
    $output = $ip;
    if ($isp) $output .= " - $isp";
    if ($country) $output .= ", $country";
    if ($distance) $output .= " ($distance)";
    return $output;
}

/**
 * Simple IP response if ISP detection is not requested or fails.
 * @param string $ip
 * @param string|null $ispName
 * @return array
 */
function formatSimpleResponse(string $ip, ?string $ispName = null): array {
    $processedString = $ispName ? "$ip - $ispName" : $ip;
    return ['processedString' => $processedString, 'rawIspInfo' => ''];
}

// Response handling
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ip = getClientIp();

if (isset($_GET['isp'])) {
    $localIpInfo = getLocalOrPrivateIpInfo($ip);
    if ($localIpInfo) {
        echo json_encode(formatSimpleResponse($ip, $localIpInfo));
    } else {
        $ispInfo = getIspInfo_ipinfoApi($ip);
        echo json_encode($ispInfo ?? formatSimpleResponse($ip));
    }
} else {
    echo json_encode(formatSimpleResponse($ip));
}