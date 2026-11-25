<?php

/*
 * Detect the client's IP and optionally add approximate ISP/location metadata.
 * processedString and rawIspInfo remain backward-compatible; location is
 * included only when valid approximate metadata is available.
 */

error_reporting(0);

define('API_KEY_FILE', __DIR__ . '/getIP_ipInfo_apikey.php');
define('SERVER_LOCATION_CACHE_FILE', __DIR__ . '/getIP_serverLocation.php');
define('OFFLINE_IPINFO_DB_FILE', __DIR__ . '/country_asn.mmdb');

require_once __DIR__ . '/getIP_util.php';

if (PHP_VERSION_ID >= 80100 && file_exists(__DIR__ . '/geoip2.phar')) {
    require_once __DIR__ . '/geoip2.phar';
}

function getLocalOrPrivateIpInfo($ip)
{
    if ('::1' === $ip) {
        return 'localhost IPv6 access';
    }
    if (stripos($ip, 'fe80:') === 0) {
        return 'link-local IPv6 access';
    }
    if (preg_match('/^(fc|fd)([0-9a-f]{0,4}:){1,7}[0-9a-f]{1,4}$/i', $ip) === 1) {
        return 'ULA IPv6 access';
    }
    if (strpos($ip, '127.') === 0) {
        return 'localhost IPv4 access';
    }
    if (strpos($ip, '10.') === 0) {
        return 'private IPv4 access';
    }
    if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip) === 1) {
        return 'private IPv4 access';
    }
    if (strpos($ip, '192.168.') === 0) {
        return 'private IPv4 access';
    }
    if (strpos($ip, '169.254.') === 0) {
        return 'link-local IPv4 access';
    }
    return null;
}

function getIpInfoEnvironmentValue($name)
{
    $value = getenv($name);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
        return $_ENV[$name];
    }
    if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }
    return null;
}

function isIpInfoDisabled()
{
    $value = getIpInfoEnvironmentValue('DISABLE_IPINFO');
    if ($value === null && defined('DISABLE_IPINFO')) {
        $value = constant('DISABLE_IPINFO');
    }
    if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
        return false;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function resolveIpInfoDbPath($path)
{
    if (!is_string($path) || trim($path) === '') {
        return null;
    }
    $path = trim($path);
    if (file_exists($path) || preg_match('/^(?:\/|[A-Za-z]:[\\\\\/])/', $path) === 1) {
        return $path;
    }
    return __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function getConfiguredIpInfoDbPath($environmentName, $defaultPath)
{
    $configured = getIpInfoEnvironmentValue($environmentName);
    return resolveIpInfoDbPath(is_string($configured) && $configured !== '' ? $configured : $defaultPath);
}

function normalizeIpInfoText($value)
{
    if (is_string($value) || is_numeric($value)) {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
    return null;
}

function normalizeIpInfoLocalizedName($value)
{
    if (is_string($value) || is_numeric($value)) {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
    if (!is_array($value)) {
        return null;
    }
    if (isset($value['en'])) {
        $name = normalizeIpInfoText($value['en']);
        if ($name !== null) {
            return $name;
        }
    }
    if (isset($value['names']) && is_array($value['names'])) {
        if (isset($value['names']['en'])) {
            $name = normalizeIpInfoText($value['names']['en']);
            if ($name !== null) {
                return $name;
            }
        }
        foreach ($value['names'] as $name) {
            $name = normalizeIpInfoText($name);
            if ($name !== null) {
                return $name;
            }
        }
    }
    if (isset($value['name'])) {
        $name = normalizeIpInfoText($value['name']);
        if ($name !== null) {
            return $name;
        }
    }
    foreach ($value as $name) {
        $name = normalizeIpInfoText($name);
        if ($name !== null) {
            return $name;
        }
    }
    return null;
}

function normalizeIpInfoCountryCode($data)
{
    if (!is_array($data)) {
        return null;
    }
    foreach (['country_code', 'country_iso_code', 'countryCode', 'iso_code'] as $key) {
        $value = normalizeIpInfoText($data[$key] ?? null);
        if ($value !== null && preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
            return strtoupper($value);
        }
    }
    if (isset($data['country']) && is_array($data['country']) && isset($data['country']['iso_code'])) {
        $value = normalizeIpInfoText($data['country']['iso_code']);
        if ($value !== null && preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
            return strtoupper($value);
        }
    }
    foreach (['registered_country', 'represented_country'] as $key) {
        if (isset($data[$key]) && is_array($data[$key]) && isset($data[$key]['iso_code'])) {
            $value = normalizeIpInfoText($data[$key]['iso_code']);
            if ($value !== null && preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
                return strtoupper($value);
            }
        }
    }
    $value = normalizeIpInfoText($data['country'] ?? null);
    if ($value !== null && preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
        return strtoupper($value);
    }
    return null;
}

function normalizeIpInfoCountryName($data)
{
    if (!is_array($data)) {
        return null;
    }
    foreach (['country_name', 'countryName'] as $key) {
        $value = normalizeIpInfoText($data[$key] ?? null);
        if ($value !== null) {
            return $value;
        }
    }
    foreach (['country', 'registered_country', 'represented_country'] as $key) {
        if (!isset($data[$key])) {
            continue;
        }
        $name = normalizeIpInfoLocalizedName($data[$key]);
        if ($name !== null) {
            if ($key === 'country' && preg_match('/^[A-Za-z]{2}$/', $name) === 1) {
                continue;
            }
            return $name;
        }
    }
    return null;
}

function normalizeIpInfoCityName($data)
{
    if (!is_array($data)) {
        return null;
    }
    foreach (['city_name', 'cityName'] as $key) {
        $value = normalizeIpInfoText($data[$key] ?? null);
        if ($value !== null) {
            return $value;
        }
    }
    if (isset($data['city'])) {
        return normalizeIpInfoLocalizedName($data['city']);
    }
    return null;
}

function normalizeIpInfoRegionName($data)
{
    if (!is_array($data)) {
        return null;
    }
    foreach (['region_name', 'regionName', 'subdivision_name', 'subdivisionName'] as $key) {
        $value = normalizeIpInfoText($data[$key] ?? null);
        if ($value !== null) {
            return $value;
        }
    }
    foreach (['region', 'subdivision'] as $key) {
        if (isset($data[$key])) {
            $name = normalizeIpInfoLocalizedName($data[$key]);
            if ($name !== null) {
                return $name;
            }
        }
    }
    if (isset($data['subdivisions']) && is_array($data['subdivisions']) && isset($data['subdivisions'][0])) {
        return normalizeIpInfoLocalizedName($data['subdivisions'][0]);
    }
    return null;
}

function parseIpInfoLocationString($loc)
{
    if (!is_string($loc) || substr_count($loc, ',') !== 1) {
        return null;
    }
    $parts = explode(',', $loc, 2);
    $latitude = trim($parts[0]);
    $longitude = trim($parts[1]);
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return null;
    }
    $latitude = (float) $latitude;
    $longitude = (float) $longitude;
    if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
        return null;
    }
    return [$latitude, $longitude];
}

function normalizeIpInfoCoordinates($data)
{
    if (!is_array($data)) {
        return null;
    }
    $coordinates = parseIpInfoLocationString($data['loc'] ?? null);
    if (is_array($coordinates)) {
        return $coordinates;
    }
    if (isset($data['location']) && is_array($data['location'])) {
        $latitude = $data['location']['latitude'] ?? $data['location']['lat'] ?? null;
        $longitude = $data['location']['longitude'] ?? $data['location']['lon'] ?? null;
        if (is_numeric($latitude) && is_numeric($longitude)) {
            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
            if ($latitude >= -90.0 && $latitude <= 90.0 && $longitude >= -180.0 && $longitude <= 180.0) {
                return [$latitude, $longitude];
            }
        }
    }
    $latitude = $data['latitude'] ?? $data['lat'] ?? null;
    $longitude = $data['longitude'] ?? $data['lon'] ?? null;
    if (is_numeric($latitude) && is_numeric($longitude)) {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        if ($latitude >= -90.0 && $latitude <= 90.0 && $longitude >= -180.0 && $longitude <= 180.0) {
            return [$latitude, $longitude];
        }
    }
    return null;
}

function normalizeApproximateLocation($data, $source)
{
    if (!is_array($data)) {
        return null;
    }
    $countryCode = normalizeIpInfoCountryCode($data);
    $countryName = normalizeIpInfoCountryName($data);
    if ($countryName === null && $countryCode !== null) {
        $countryName = $countryCode;
    }
    $city = normalizeIpInfoCityName($data);
    $region = normalizeIpInfoRegionName($data);
    $coordinates = normalizeIpInfoCoordinates($data);

    $location = [];
    if ($city !== null) {
        $location['city'] = $city;
    }
    if ($region !== null) {
        $location['region'] = $region;
    }
    if ($countryName !== null) {
        $location['country'] = $countryName;
    }
    if ($countryCode !== null) {
        $location['countryCode'] = $countryCode;
    }
    if (is_array($coordinates)) {
        $location['latitude'] = $coordinates[0];
        $location['longitude'] = $coordinates[1];
        $location['lat'] = $coordinates[0];
        $location['lon'] = $coordinates[1];
    }
    if (count($location) === 0) {
        return null;
    }
    $location['approximate'] = true;
    $location['source'] = $source;
    return $location;
}

function mergeApproximateLocations($apiLocation, $offlineLocation)
{
    if (!is_array($apiLocation)) {
        return $offlineLocation;
    }
    if (!is_array($offlineLocation)) {
        return $apiLocation;
    }

    $offlineSource = $offlineLocation['source'] ?? '';
    $preferOfflinePlace = in_array(
        $offlineSource,
        [
            'ipinfo_city_mmdb',
            'ipinfo_country_mmdb',
            'maxmind_mmdb',
            'maxmind_country_mmdb',
        ],
        true
    );

    $merged = $apiLocation;
    foreach (['city', 'region', 'latitude', 'longitude', 'lat', 'lon'] as $key) {
        if ($preferOfflinePlace && array_key_exists($key, $offlineLocation)) {
            $merged[$key] = $offlineLocation[$key];
        } elseif (!array_key_exists($key, $merged) && array_key_exists($key, $offlineLocation)) {
            $merged[$key] = $offlineLocation[$key];
        }
    }

    if ($preferOfflinePlace) {
        foreach (['country', 'countryCode'] as $key) {
            if (array_key_exists($key, $offlineLocation)) {
                $merged[$key] = $offlineLocation[$key];
            }
        }
    } else {
        foreach (['country', 'countryCode'] as $key) {
            if (!array_key_exists($key, $merged) && array_key_exists($key, $offlineLocation)) {
                $merged[$key] = $offlineLocation[$key];
            }
        }
    }

    $merged['approximate'] = true;
    $merged['source'] = $preferOfflinePlace ? $offlineSource : ($apiLocation['source'] ?? $offlineSource);
    return $merged;
}

function normalizeIspName($data)
{
    if (!is_array($data)) {
        return null;
    }
    if (isset($data['org']) && is_string($data['org']) && trim($data['org']) !== '') {
        $isp = trim(preg_replace('/^AS\d+\s*/i', '', $data['org']));
        if ($isp !== '') {
            return $isp;
        }
    }
    if (isset($data['asn'])) {
        if (is_array($data['asn']) && isset($data['asn']['name'])) {
            $isp = normalizeIpInfoText($data['asn']['name']);
            if ($isp !== null) {
                return $isp;
            }
        } elseif (is_string($data['asn'])) {
            $isp = trim(preg_replace('/^AS\d+\s*/i', '', $data['asn']));
            if ($isp !== '' && preg_match('/^AS\d+$/i', $isp) !== 1) {
                return $isp;
            }
        }
    }
    foreach (['as_name', 'autonomous_system_organization', 'organization', 'isp', 'network_name', 'organization_name'] as $key) {
        $isp = normalizeIpInfoText($data[$key] ?? null);
        if ($isp !== null) {
            return trim(preg_replace('/^AS\d+\s*/i', '', $isp));
        }
    }
    return null;
}

function ipInfoHttpGet($url)
{
    if (isset($GLOBALS['getIP_http_get']) && is_callable($GLOBALS['getIP_http_get'])) {
        $result = call_user_func($GLOBALS['getIP_http_get'], $url);
        return is_string($result) ? $result : null;
    }
    $context = stream_context_create([
        'http' => ['timeout' => 5, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $json = @file_get_contents($url, false, $context);
    return is_string($json) ? $json : null;
}

function buildIpInfoApiUrl($ip, $apiKey = '')
{
    $url = 'https://ipinfo.io/' . rawurlencode($ip) . '/json';
    if (is_string($apiKey) && $apiKey !== '') {
        $url .= '?token=' . rawurlencode($apiKey);
    }
    return $url;
}

function buildIpInfoServerUrl($apiKey = '')
{
    $url = 'https://ipinfo.io/json';
    if (is_string($apiKey) && $apiKey !== '') {
        $url .= '?token=' . rawurlencode($apiKey);
    }
    return $url;
}

function calculateIpInfoDistance($clientLoc, $serverLoc, $unit)
{
    $client = parseIpInfoLocationString($clientLoc);
    $server = parseIpInfoLocationString($serverLoc);
    if (!is_array($client) || !is_array($server) || !in_array($unit, ['mi', 'km'], true)) {
        return null;
    }
    $rad = M_PI / 180.0;
    $latitudeDelta = ($server[0] - $client[0]) * $rad;
    $longitudeDelta = ($server[1] - $client[1]) * $rad;
    $a = sin($latitudeDelta / 2.0) * sin($latitudeDelta / 2.0)
        + cos($client[0] * $rad) * cos($server[0] * $rad)
        * sin($longitudeDelta / 2.0) * sin($longitudeDelta / 2.0);
    $c = 2.0 * atan2(sqrt(max(0.0, $a)), sqrt(max(0.0, 1.0 - $a)));
    $distanceKm = 6371.0088 * $c;
    if ($unit === 'mi') {
        $distance = $distanceKm / 1.609344;
        $distance = round($distance, -1);
        if ($distance < 15) {
            $distance = '<15';
        }
        return $distance . ' mi';
    }
    $distance = round($distanceKm, -1);
    if ($distance < 20) {
        $distance = '<20';
    }
    return $distance . ' km';
}

function getIpInfoServerLoc($apiKey)
{
    $serverLoc = null;
    if (file_exists(SERVER_LOCATION_CACHE_FILE) && is_readable(SERVER_LOCATION_CACHE_FILE)) {
        try {
            require SERVER_LOCATION_CACHE_FILE;
        } catch (Throwable $e) {
            $serverLoc = null;
        }
    }
    if (is_string($serverLoc) && parseIpInfoLocationString($serverLoc) !== null) {
        return $serverLoc;
    }
    $json = ipInfoHttpGet(buildIpInfoServerUrl($apiKey));
    if (!is_string($json)) {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }
    $serverLoc = $data['loc'] ?? null;
    if (!is_string($serverLoc) || parseIpInfoLocationString($serverLoc) === null) {
        return null;
    }
    @file_put_contents(SERVER_LOCATION_CACHE_FILE, "<?php\n\n\$serverLoc = '" . addslashes($serverLoc) . "';\n");
    return $serverLoc;
}

function getIspInfo_ipinfoApi($ip)
{
    if (isIpInfoDisabled()) {
        return null;
    }
    $apiKey = '';
    if (file_exists(API_KEY_FILE) && is_readable(API_KEY_FILE)) {
        require API_KEY_FILE;
        if (isset($IPINFO_APIKEY) && is_string($IPINFO_APIKEY)) {
            $apiKey = trim($IPINFO_APIKEY);
        }
    }
    $json = ipInfoHttpGet(buildIpInfoApiUrl($ip, $apiKey));
    if (!is_string($json)) {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data) || isset($data['error']) || ($data['bogon'] ?? false) === true) {
        return null;
    }
    $isp = normalizeIspName($data);
    $countryCode = normalizeIpInfoCountryCode($data);
    $countryName = normalizeIpInfoCountryName($data);
    $location = normalizeApproximateLocation($data, 'ipinfo_api');

    if ($isp === null || $location === null ||
        !array_key_exists('city', $location) ||
        !array_key_exists('region', $location) ||
        !is_array(normalizeIpInfoCoordinates($location))) {
        $offlineJson = getIspInfo_ipinfoOfflineDb($ip);
        if (is_string($offlineJson)) {
            $offline = json_decode($offlineJson, true);
            if (is_array($offline)) {
                $offlineRaw = is_array($offline['rawIspInfo'] ?? null)
                    ? $offline['rawIspInfo']
                    : null;
                if ($isp === null && is_array($offlineRaw)) {
                    $isp = normalizeIspName($offlineRaw);
                }
                if (is_array($offlineLocation = $offline['location'] ?? null)) {
                    $location = mergeApproximateLocations($location, $offlineLocation);
                }
                if ($isp !== null) {
                    $orgIsAsnOnly = isset($data['org']) &&
                        is_string($data['org']) &&
                        preg_match('/^AS\d+\s*$/i', trim($data['org'])) === 1;
                    if (!isset($data['org']) || $orgIsAsnOnly) {
                        $data['org'] = $isp;
                    }
                    $data['as_name'] = $isp;
                }
            }
        }
        $countryCode = normalizeIpInfoCountryCode($location);
        $countryName = normalizeIpInfoCountryName($location);
    }

    if ($isp === null && $location === null) {
        return null;
    }

    $distance = null;
    if (isset($_GET['distance']) && in_array($_GET['distance'], ['mi', 'km'], true)) {
        $clientCoordinates = is_array($location)
            ? normalizeIpInfoCoordinates($location)
            : null;
        $clientLoc = is_array($clientCoordinates)
            ? $clientCoordinates[0] . ',' . $clientCoordinates[1]
            : ($data['loc'] ?? null);
        if (is_string($clientLoc) && parseIpInfoLocationString($clientLoc) !== null) {
            $serverLoc = getIpInfoServerLoc($apiKey);
            if (is_string($serverLoc)) {
                $distance = calculateIpInfoDistance($clientLoc, $serverLoc, $_GET['distance']);
            }
        }
    }

    $processedString = $ip;
    $processedCountry = $countryCode ?: $countryName;
    if ($isp !== null) {
        $processedString .= ' - ' . $isp;
        if ($processedCountry !== null) {
            $processedString .= ', ' . $processedCountry;
        }
    } elseif ($processedCountry !== null) {
        $processedString .= ' - ' . $processedCountry;
    }
    if (is_string($distance)) {
        $processedString .= ' (' . $distance . ')';
    }

    $response = [
        'processedString' => $processedString,
        'rawIspInfo' => $data ?: '',
    ];
    if ($location !== null) {
        $response['location'] = $location;
    }
    return json_encode($response);
}

function readIpInfoMmdb($ip, $path)
{
    if (!is_string($path) || $path === '' || !file_exists($path) || !is_readable($path)) {
        return null;
    }
    if (!class_exists('MaxMind\\Db\\Reader')) {
        return null;
    }
    try {
        $reader = new MaxMind\Db\Reader($path);
        $data = $reader->get($ip);
        return is_array($data) ? $data : null;
    } catch (Throwable $e) {
        return null;
    }
}

function getIspInfo_ipinfoOfflineDb($ip)
{
    if (isIpInfoDisabled()) {
        return null;
    }

    $asnPath = getConfiguredIpInfoDbPath('IPINFO_ASN_DB', OFFLINE_IPINFO_DB_FILE);
    $countryPath = getConfiguredIpInfoDbPath('IPINFO_COUNTRY_DB', $asnPath);
    $cityPath = getIpInfoEnvironmentValue('IPINFO_CITY_DB');
    if (!is_string($cityPath) || trim($cityPath) === '') {
        $cityPath = getIpInfoEnvironmentValue('IPINFO_CITY_MMDB');
    }
    if (!is_string($cityPath) || trim($cityPath) === '') {
        $cityPath = getIpInfoEnvironmentValue('MAXMIND_CITY_DB');
    }
    $cityPath = is_string($cityPath) && trim($cityPath) !== '' ? resolveIpInfoDbPath($cityPath) : null;

    $rawData = [];
    $asnData = readIpInfoMmdb($ip, $asnPath);
    if (is_array($asnData)) {
        $rawData = array_replace_recursive($rawData, $asnData);
    }
    $countryData = null;
    if (is_string($countryPath) && $countryPath !== '' && $countryPath !== $asnPath) {
        $countryData = readIpInfoMmdb($ip, $countryPath);
        if (is_array($countryData)) {
            $rawData = array_replace_recursive($rawData, $countryData);
        }
    }
    $cityData = null;
    if (is_string($cityPath) && $cityPath !== '') {
        $cityData = readIpInfoMmdb($ip, $cityPath);
        if (is_array($cityData)) {
            $rawData = array_replace_recursive($rawData, $cityData);
        }
    }
    if (count($rawData) === 0) {
        return null;
    }

    $isp = normalizeIspName(is_array($asnData) ? $asnData : $rawData);
    if ($isp === null) {
        $isp = normalizeIspName($rawData);
    }

    $location = null;
    if (is_array($cityData)) {
        $citySource = (isset($cityData['location']) || isset($cityData['subdivisions']) || isset($cityData['city']['names']))
            ? 'maxmind_mmdb'
            : 'ipinfo_city_mmdb';
        $location = normalizeApproximateLocation($cityData, $citySource);
    }
    if (!is_array($location) && is_array($countryData)) {
        $countrySource = (isset($countryData['location']) || isset($countryData['subdivisions']) || isset($countryData['country']['names']))
            ? 'maxmind_country_mmdb'
            : 'ipinfo_country_mmdb';
        $location = normalizeApproximateLocation($countryData, $countrySource);
    }
    if (!is_array($location) && is_array($asnData)) {
        $location = normalizeApproximateLocation($asnData, 'ipinfo_mmdb');
    }

    $countryCode = is_array($location)
        ? normalizeIpInfoCountryCode($location)
        : null;
    $countryName = is_array($location)
        ? normalizeIpInfoCountryName($location)
        : null;
    if ($isp === null && $location === null) {
        return null;
    }

    if ($isp !== null) {
        $rawData['as_name'] = $isp;
    }

    $processedString = $ip;
    $processedCountry = $countryName ?: $countryCode;
    if ($isp !== null) {
        $processedString .= ' - ' . $isp;
        if ($processedCountry !== null) {
            $processedString .= ', ' . $processedCountry;
        }
    } elseif ($processedCountry !== null) {
        $processedString .= ' - ' . $processedCountry;
    } else {
        $city = normalizeIpInfoCityName($rawData);
        $region = normalizeIpInfoRegionName($rawData);
        $place = [];
        if ($city !== null) {
            $place[] = $city;
        }
        if ($region !== null) {
            $place[] = $region;
        }
        if (count($place) > 0) {
            $processedString .= ' - ' . implode(', ', $place);
        }
    }

    $response = [
        'processedString' => $processedString,
        'rawIspInfo' => $rawData,
    ];
    if ($location !== null) {
        $response['location'] = $location;
    }
    return json_encode($response);
}

function formatResponse_simple($ip, $ispName = null)
{
    $processedString = $ip;
    if (is_string($ispName)) {
        $processedString .= ' - ' . $ispName;
    }
    return json_encode([
        'processedString' => $processedString,
        'rawIspInfo' => '',
    ]);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');
    if (isset($_GET['cors'])) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');

    $ip = getClientIp();
    if (isset($_GET['isp'])) {
        if (isIpInfoDisabled()) {
            echo formatResponse_simple($ip);
        } else {
            $localIpInfo = getLocalOrPrivateIpInfo($ip);
            if (is_string($localIpInfo)) {
                echo formatResponse_simple($ip, $localIpInfo);
            } else {
                $r = getIspInfo_ipinfoApi($ip);
                if (!is_null($r)) {
                    echo $r;
                } else {
                    $r = getIspInfo_ipinfoOfflineDb($ip);
                    if (!is_null($r)) {
                        echo $r;
                    } else {
                        echo formatResponse_simple($ip);
                    }
                }
            }
        }
    } else {
        echo formatResponse_simple($ip);
    }
}
