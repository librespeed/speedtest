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
define('IP_PROVIDER_REGISTRY_FILE', __DIR__ . '/../ip-providers.json');
define(
    'IP_PROVIDER_CACHE_FILE',
    rtrim(sys_get_temp_dir(), '/\\') . '/librespeed-ip-provider-cache-' .
        hash('sha256', __DIR__) . '.json'
);
define(
    'IP_PROVIDER_CACHE_LOCK_FILE',
    rtrim(sys_get_temp_dir(), '/\\') . '/librespeed-ip-provider-cache-' .
        hash('sha256', __DIR__) . '.lock'
);

require_once __DIR__ . '/getIP_util.php';

if (PHP_VERSION_ID >= 80100 && file_exists(__DIR__ . '/geoip2.phar')) {
    require_once __DIR__ . '/geoip2.phar';
}

function getLocalOrPrivateIpInfo($ip)
{
    if (isPublicIpAddress($ip)) {
        return null;
    }
    if ('::1' === $ip) {
        return 'localhost IPv6 access';
    }
    if (stripos($ip, 'fe80:') === 0) {
        return 'link-local IPv6 access';
    }
    if (stripos($ip, 'fec0:') === 0) {
        return 'site-local IPv6 access';
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
    if (preg_match('/^100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\./', $ip) === 1) {
        return 'shared CGNAT IPv4 access';
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
        $latitude = round((float) $coordinates[0], 3);
        $longitude = round((float) $coordinates[1], 3);
        $location['latitude'] = $latitude;
        $location['longitude'] = $longitude;
        $location['lat'] = $latitude;
        $location['lon'] = $longitude;
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

    $primaryCode = normalizeIpInfoCountryCode($apiLocation);
    $fallbackCode = normalizeIpInfoCountryCode($offlineLocation);
    if (is_string($primaryCode) && is_string($fallbackCode) &&
        $primaryCode !== $fallbackCode) {
        return $apiLocation;
    }

    $primaryHasPlace = array_key_exists('city', $apiLocation) ||
        array_key_exists('region', $apiLocation) ||
        array_key_exists('latitude', $apiLocation) ||
        array_key_exists('longitude', $apiLocation);
    $fallbackHasPlace = array_key_exists('city', $offlineLocation) ||
        array_key_exists('region', $offlineLocation) ||
        array_key_exists('latitude', $offlineLocation) ||
        array_key_exists('longitude', $offlineLocation);

    $merged = $apiLocation;
    if ($primaryHasPlace) {
        $primaryCity = normalizeIpInfoText($apiLocation['city'] ?? null);
        $fallbackCity = normalizeIpInfoText($offlineLocation['city'] ?? null);
        $primaryRegion = normalizeIpInfoText($apiLocation['region'] ?? null);
        $fallbackRegion = normalizeIpInfoText($offlineLocation['region'] ?? null);
        $cityConflict = is_string($primaryCity) && is_string($fallbackCity) &&
            strcasecmp($primaryCity, $fallbackCity) !== 0;
        $regionConflict = is_string($primaryRegion) && is_string($fallbackRegion) &&
            strcasecmp($primaryRegion, $fallbackRegion) !== 0;
        $primaryCoordinates = normalizeIpInfoCoordinates($apiLocation);
        $fallbackCoordinates = normalizeIpInfoCoordinates($offlineLocation);
        $coordinateConflict =
            (is_array($primaryCoordinates) &&
             is_array($fallbackCoordinates) &&
             abs($primaryCoordinates[0] - $fallbackCoordinates[0]) > 0.01) ||
            (is_array($primaryCoordinates) &&
             is_array($fallbackCoordinates) &&
             abs($primaryCoordinates[1] - $fallbackCoordinates[1]) > 0.01);
        if ($fallbackHasPlace && ($cityConflict || $regionConflict || $coordinateConflict)) {
            return $apiLocation;
        }
        foreach (['city', 'region', 'latitude', 'longitude', 'lat', 'lon'] as $key) {
            if (!array_key_exists($key, $merged) && array_key_exists($key, $offlineLocation)) {
                $merged[$key] = $offlineLocation[$key];
            }
        }
        foreach (['country', 'countryCode'] as $key) {
            if (!array_key_exists($key, $merged) && array_key_exists($key, $offlineLocation)) {
                $merged[$key] = $offlineLocation[$key];
            }
        }
    } else if ($fallbackHasPlace) {
        foreach (['city', 'region', 'latitude', 'longitude', 'lat', 'lon',
                  'country', 'countryCode'] as $key) {
            if (array_key_exists($key, $offlineLocation)) {
                $merged[$key] = $offlineLocation[$key];
            }
        }
        $merged['source'] = $offlineLocation['source'] ?? ($apiLocation['source'] ?? 'provider');
    } else {
        foreach (['country', 'countryCode'] as $key) {
            if (!array_key_exists($key, $merged) && array_key_exists($key, $offlineLocation)) {
                $merged[$key] = $offlineLocation[$key];
            }
        }
    }

    $merged['approximate'] = true;
    if (!isset($merged['source'])) {
        $merged['source'] = $apiLocation['source'] ?? ($offlineLocation['source'] ?? 'provider');
    }
    return $merged;
}

function supplementLocationCountry($location, $fallbackLocation)
{
    if (!is_array($location)) {
        return $fallbackLocation;
    }
    if (!is_array($fallbackLocation)) {
        return $location;
    }

    $fallbackCountry = $fallbackLocation['country'] ?? null;
    $fallbackCode = $fallbackLocation['countryCode'] ?? null;
    $validFallbackPair = is_string($fallbackCountry) &&
        $fallbackCountry !== '' &&
        is_string($fallbackCode) &&
        $fallbackCode !== '';
    if (!$validFallbackPair) {
        return $location;
    }

    $locationCountry = $location['country'] ?? null;
    $locationCode = $location['countryCode'] ?? null;

    if (!is_string($locationCountry) && !is_string($locationCode)) {
        $location['country'] = $fallbackCountry;
        $location['countryCode'] = $fallbackCode;
        return $location;
    }

    if (is_string($locationCountry) && !is_string($locationCode) &&
        ($locationCountry === $fallbackCountry || $locationCountry === $fallbackCode)) {
        $location['countryCode'] = $fallbackCode;
        return $location;
    }

    if (is_string($locationCode) && !is_string($locationCountry) &&
        ($locationCode === $fallbackCode || $locationCode === $fallbackCountry)) {
        $location['country'] = $fallbackCountry;
        return $location;
    }

    return $location;
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

function sanitizeIpInfoRawIspInfo($data, $source)
{
    if (!is_array($data)) {
        return '';
    }
    $raw = [];
    foreach (
        [
            'ip',
            'hostname',
            'city',
            'region',
            'country',
            'country_name',
            'country_code',
            'loc',
            'org',
            'as_name',
            'organization',
            'isp',
            'network_name',
            'organization_name',
            'autonomous_system_organization',
            'as_number',
            'asn_name',
            'postal',
            'timezone',
        ] as $key
    ) {
        if (array_key_exists($key, $data) &&
            (is_string($data[$key]) || is_numeric($data[$key]))) {
            $raw[$key] = $data[$key];
        }
    }
    if (isset($raw['loc']) && is_string($raw['loc'])) {
        $coordinates = parseIpInfoLocationString($raw['loc']);
        if (is_array($coordinates)) {
            $raw['loc'] = round($coordinates[0], 3) . ',' . round($coordinates[1], 3);
        } else {
            unset($raw['loc']);
        }
    }
    if (isset($data['asn']) && is_array($data['asn']) &&
        isset($data['asn']['name']) && is_string($data['asn']['name'])) {
        $raw['asn'] = ['name' => $data['asn']['name']];
    } elseif (isset($data['asn']) && is_string($data['asn'])) {
        $raw['asn'] = $data['asn'];
    }
    if (is_string($source) && $source !== '') {
        $raw['source'] = $source;
    }
    return count($raw) > 0 ? $raw : '';
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
    if (!isPublicIpAddress($ip)) {
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
        'rawIspInfo' => sanitizeIpInfoRawIspInfo($data, 'ipinfo_api'),
    ];
    if ($location !== null) {
        $response['location'] = $location;
    }
    return json_encode($response);
}

function getIpInfoApiKey()
{
    $apiKey = getIpInfoEnvironmentValue('IPINFO_APIKEY');
    if (is_string($apiKey) && trim($apiKey) !== '') {
        return trim($apiKey);
    }
    if (file_exists(API_KEY_FILE) && is_readable(API_KEY_FILE)) {
        require API_KEY_FILE;
        if (isset($IPINFO_APIKEY) && is_string($IPINFO_APIKEY)) {
            return trim($IPINFO_APIKEY);
        }
    }
    return '';
}

function readIpProviderJson($path)
{
    if (!is_string($path) || $path === '' || !file_exists($path) || !is_readable($path)) {
        return null;
    }
    $json = @file_get_contents($path);
    $data = is_string($json) ? json_decode($json, true) : null;
    return is_array($data) ? $data : null;
}

function writeIpProviderJson($path, $data)
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return false;
    }
    $temp = $path . '.tmp-' . getmypid();
    if (@file_put_contents($temp, $json, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($temp, $path)) {
        @unlink($temp);
        return false;
    }
    return true;
}

function readIpProviderCache()
{
    $cache = readIpProviderJson(IP_PROVIDER_CACHE_FILE);
    if (!is_array($cache)) {
        $cache = [];
    }
    foreach (['positive', 'negative', 'cooldowns'] as $key) {
        if (!isset($cache[$key]) || !is_array($cache[$key])) {
            $cache[$key] = [];
        }
    }
    $now = time();
    foreach (['positive', 'negative', 'cooldowns'] as $section) {
        foreach ($cache[$section] as $key => $entry) {
            if (!is_array($entry) || !isset($entry['expires']) ||
                (int) $entry['expires'] <= $now) {
                unset($cache[$section][$key]);
            }
        }
    }
    if (count($cache['positive']) > 5000) {
        uasort($cache['positive'], function ($a, $b) {
            return (int) ($a['expires'] ?? 0) <=> (int) ($b['expires'] ?? 0);
        });
        $cache['positive'] = array_slice($cache['positive'], -5000, null, true);
    }
    if (count($cache['negative']) > 5000) {
        uasort($cache['negative'], function ($a, $b) {
            return (int) ($a['expires'] ?? 0) <=> (int) ($b['expires'] ?? 0);
        });
        $cache['negative'] = array_slice($cache['negative'], -5000, null, true);
    }
    if (count($cache['cooldowns']) > 100) {
        uasort($cache['cooldowns'], function ($a, $b) {
            return (int) ($a['expires'] ?? 0) <=> (int) ($b['expires'] ?? 0);
        });
        $cache['cooldowns'] = array_slice($cache['cooldowns'], -100, null, true);
    }
    return $cache;
}

function providerCacheKey($ip)
{
    return hash('sha256', $ip);
}

function getPositiveProviderCache($ip)
{
    $cache = readIpProviderCache();
    $key = providerCacheKey($ip);
    $entry = $cache['positive'][$key] ?? null;
    if (!is_array($entry) || !isset($entry['expires'], $entry['response']) ||
        (int) $entry['expires'] <= time()) {
        return null;
    }
    $response = is_array($entry['response']) ? $entry['response'] : null;
    if (!is_array($response)) {
        return null;
    }
    $response['processedString'] = $ip;
    $raw = is_array($response['rawIspInfo'] ?? null)
        ? $response['rawIspInfo']
        : [];
    $isp = normalizeIspName($raw);
    $location = is_array($response['location'] ?? null)
        ? $response['location']
        : null;
    $countryCode = is_array($location)
        ? normalizeIpInfoCountryCode($location)
        : null;
    $countryName = is_array($location)
        ? normalizeIpInfoCountryName($location)
        : null;
    $processedCountry = $countryCode ?: $countryName;
    if ($isp !== null) {
        $response['processedString'] .= ' - ' . $isp;
        if ($processedCountry !== null) {
            $response['processedString'] .= ', ' . $processedCountry;
        }
    } elseif ($processedCountry !== null) {
        $response['processedString'] .= ' - ' . $processedCountry;
    }
    if (isset($_GET['distance']) && in_array($_GET['distance'], ['mi', 'km'], true) &&
        is_array($location)) {
        $coordinates = normalizeIpInfoCoordinates($location);
        if (is_array($coordinates)) {
            $serverLoc = getIpInfoServerLoc(getIpInfoApiKey());
            if (is_string($serverLoc)) {
                $distance = calculateIpInfoDistance(
                    $coordinates[0] . ',' . $coordinates[1],
                    $serverLoc,
                    $_GET['distance']
                );
                if (is_string($distance)) {
                    $response['processedString'] .= ' (' . $distance . ')';
                }
            }
        }
    }
    return $response;
}

function sanitizeProviderCacheResponse($response)
{
    if (!is_array($response)) {
        return null;
    }
    unset($response['processedString']);
    if (isset($response['rawIspInfo']) && is_array($response['rawIspInfo'])) {
        foreach (['ip', 'hostname', 'loc', 'postal', 'timezone'] as $key) {
            unset($response['rawIspInfo'][$key]);
        }
    }
    return $response;
}

function modifyIpProviderCache($callback)
{
    $lock = @fopen(IP_PROVIDER_CACHE_LOCK_FILE, 'c');
    if (!is_resource($lock)) {
        $cache = readIpProviderCache();
        $callback($cache);
        return writeIpProviderJson(IP_PROVIDER_CACHE_FILE, $cache);
    }
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        return false;
    }
    $cache = readIpProviderCache();
    $callback($cache);
    $written = writeIpProviderJson(IP_PROVIDER_CACHE_FILE, $cache);
    flock($lock, LOCK_UN);
    fclose($lock);
    return $written;
}

function setPositiveProviderCache($ip, $response)
{
    $ttl = 21600;
    $registry = loadIpProviderRegistry();
    if (is_array($registry) && isset($registry['positive_cache_ttl_seconds'])) {
        $ttl = max(1, (int) $registry['positive_cache_ttl_seconds']);
    }
    $cachedResponse = sanitizeProviderCacheResponse($response);
    if (!is_array($cachedResponse)) {
        return;
    }
    modifyIpProviderCache(function (&$cache) use ($ttl, $ip, $cachedResponse) {
        $cache['positive'][providerCacheKey($ip)] = [
            'expires' => time() + $ttl,
            'response' => $cachedResponse,
        ];
    });
}

function getNegativeProviderCache($ip)
{
    $cache = readIpProviderCache();
    $key = providerCacheKey($ip);
    $entry = $cache['negative'][$key] ?? null;
    return is_array($entry) && isset($entry['expires']) &&
        (int) $entry['expires'] > time();
}

function setNegativeProviderCache($ip)
{
    $ttl = 300;
    $registry = loadIpProviderRegistry();
    if (is_array($registry) && isset($registry['negative_cache_ttl_seconds'])) {
        $ttl = max(1, (int) $registry['negative_cache_ttl_seconds']);
    }
    modifyIpProviderCache(function (&$cache) use ($ttl, $ip) {
        $cache['negative'][providerCacheKey($ip)] = [
            'expires' => time() + $ttl,
        ];
    });
}

function isProviderCoolingDown($providerId)
{
    $cache = readIpProviderCache();
    $entry = $cache['cooldowns'][$providerId] ?? null;
    return is_array($entry) && isset($entry['expires']) &&
        (int) $entry['expires'] > time();
}

function setProviderCooldown($providerId)
{
    $ttl = 900;
    $registry = loadIpProviderRegistry();
    if (is_array($registry) && isset($registry['cooldown_seconds'])) {
        $ttl = max(1, (int) $registry['cooldown_seconds']);
    }
    modifyIpProviderCache(function (&$cache) use ($ttl, $providerId) {
        $cache['cooldowns'][$providerId] = [
            'expires' => time() + $ttl,
        ];
    });
}

function loadIpProviderRegistry()
{
    $registry = null;
    $configured = getIpInfoEnvironmentValue('IP_PROVIDER_REGISTRY_FILE');
    $candidates = [];
    if (is_string($configured) && $configured !== '') {
        $candidates[] = resolveIpInfoDbPath($configured);
    }
    $candidates[] = __DIR__ . '/ip-providers.json';
    $candidates[] = __DIR__ . '/../ip-providers.json';
    $candidates[] = '/speedtest/ip-providers.json';
    $candidates = array_unique(array_filter($candidates));
    foreach ($candidates as $candidate) {
        $registry = readIpProviderJson($candidate);
        if (is_array($registry)) {
            break;
        }
    }
    if (!is_array($registry) || !isset($registry['providers']) ||
        !is_array($registry['providers'])) {
        return [
            'provider_timeout_seconds' => 1.5,
            'max_attempts' => 8,
            'positive_cache_ttl_seconds' => 21600,
            'negative_cache_ttl_seconds' => 300,
            'cooldown_seconds' => 900,
            'providers' => [],
        ];
    }
    return $registry;
}

function providerHttpGet($url, $timeoutSeconds)
{
    if (isset($GLOBALS['getIP_provider_http_get']) &&
        is_callable($GLOBALS['getIP_provider_http_get'])) {
        $result = call_user_func(
            $GLOBALS['getIP_provider_http_get'],
            $url,
            $timeoutSeconds
        );
        return is_string($result) ? $result : null;
    }
    $context = stream_context_create([
        'http' => [
            'timeout' => max(0.1, (float) $timeoutSeconds),
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $json = @file_get_contents($url, false, $context);
    return is_string($json) ? $json : null;
}

function providerString($value)
{
    if (is_string($value) || is_numeric($value)) {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
    return null;
}

function providerNestedValue($data, $path)
{
    $value = $data;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return null;
        }
        $value = $value[$key];
    }
    return $value;
}

function roundProviderCoordinate($value)
{
    if (!is_numeric($value)) {
        return null;
    }
    $value = round((float) $value, 3);
    if ($value == 0.0) {
        $value = 0.0;
    }
    return $value;
}

function normalizeProviderResult($providerId, $data, $ip)
{
    if (!is_array($data)) {
        return null;
    }

    $isp = null;
    $city = null;
    $region = null;
    $countryName = null;
    $countryCode = null;
    $latitude = null;
    $longitude = null;
    $postal = null;
    $timezone = null;

    switch ($providerId) {
        case 'ipinfo':
            $mapped = [
                'org' => $data['org'] ?? null,
                'asn' => $data['asn'] ?? null,
                'as_name' => $data['as_name'] ?? null,
            ];
            $isp = normalizeIspName($mapped);
            $city = normalizeIpInfoCityName($data);
            $region = normalizeIpInfoRegionName($data);
            $countryName = normalizeIpInfoCountryName($data);
            $countryCode = normalizeIpInfoCountryCode($data);
            $coordinates = normalizeIpInfoCoordinates($data);
            $postal = providerString($data['postal'] ?? null);
            $timezone = providerString($data['timezone'] ?? null);
            break;

        case 'ipwhois':
            if (($data['success'] ?? true) === false) {
                return null;
            }
            $isp = providerString(providerNestedValue($data, 'connection.isp')) ??
                providerString(providerNestedValue($data, 'connection.org')) ??
                providerString($data['isp'] ?? null) ??
                providerString($data['org'] ?? null);
            $city = providerString($data['city'] ?? null);
            $region = providerString($data['region'] ?? null);
            $countryName = providerString($data['country'] ?? null);
            $countryCode = providerString($data['country_code'] ?? null);
            $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            break;

        case 'freeipapi':
            $isp = providerString($data['asnOrganization'] ?? null) ??
                providerString($data['asn'] ?? null);
            $city = providerString($data['cityName'] ?? null);
            $region = providerString($data['regionName'] ?? null);
            $countryName = providerString($data['countryName'] ?? null);
            $countryCode = providerString($data['countryCode'] ?? null);
            $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            break;

        case 'iplocate':
            $isp = providerString(providerNestedValue($data, 'network.autonomous_system_name')) ??
                providerString(providerNestedValue($data, 'network.autonomous_system_organization')) ??
                providerString($data['organization'] ?? null);
            $city = providerString($data['city'] ?? null);
            $region = providerString($data['subdivision'] ?? null);
            $countryName = providerString($data['country_name'] ?? null) ??
                providerString($data['country'] ?? null);
            $countryCode = providerString($data['country_code'] ?? null) ??
                providerString($data['country_code2'] ?? null);
            $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            break;

        case 'ip2location':
            $isp = providerString($data['as'] ?? null) ??
                providerString($data['as_name'] ?? null) ??
                providerString(providerNestedValue($data, 'asn.name')) ??
                providerString($data['asn'] ?? null);
            $city = providerString($data['city_name'] ?? null) ??
                providerString($data['city'] ?? null);
            $region = providerString($data['region_name'] ?? null) ??
                providerString($data['region'] ?? null);
            $countryName = providerString($data['country_name'] ?? null);
            $countryCode = providerString($data['country_code'] ?? null);
            $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            break;

        case 'ipapiis':
            $isp = providerString(providerNestedValue($data, 'company.name')) ??
                providerString(providerNestedValue($data, 'network.asn_name')) ??
                providerString(providerNestedValue($data, 'asn.name'));
            $city = providerString(providerNestedValue($data, 'location.city')) ??
                providerString($data['city'] ?? null);
            $region = providerString(providerNestedValue($data, 'location.region')) ??
                providerString($data['region'] ?? null);
            $countryName = providerString(providerNestedValue($data, 'location.country')) ??
                providerString($data['country'] ?? null);
            $countryCode = providerString(providerNestedValue($data, 'location.country_code')) ??
                providerString($data['country_code'] ?? null);
            $latitude = roundProviderCoordinate(providerNestedValue($data, 'location.latitude'));
            if ($latitude === null) {
                $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            }
            $longitude = roundProviderCoordinate(providerNestedValue($data, 'location.longitude'));
            if ($longitude === null) {
                $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            }
            break;

        case 'dbip':
            $isp = providerString($data['asn'] ?? null) ??
                providerString($data['organization'] ?? null);
            $city = providerString($data['city'] ?? null);
            $region = providerString($data['stateProv'] ?? null) ??
                providerString($data['region'] ?? null);
            $countryName = providerString($data['countryName'] ?? null);
            $countryCode = providerString($data['countryCode'] ?? null);
            $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            break;

        case 'geojs':
            $isp = providerString($data['organization'] ?? null) ??
                providerString($data['asn'] ?? null);
            $city = providerString($data['city'] ?? null);
            $region = providerString($data['region'] ?? null);
            $countryName = providerString($data['country'] ?? null);
            $countryCode = providerString($data['country_code'] ?? null);
            $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            break;

        case 'geolocationdb':
            $city = providerString($data['city'] ?? null);
            $region = providerString($data['state'] ?? null);
            $countryName = providerString($data['country_name'] ?? null);
            $countryCode = providerString($data['country_code'] ?? null);
            $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            break;

        case 'iplocation':
            $isp = providerString($data['isp'] ?? null) ??
                providerString($data['asn'] ?? null);
            $city = providerString($data['city'] ?? null);
            $region = providerString($data['region_name'] ?? null);
            $countryName = providerString($data['country_name'] ?? null);
            $countryCode = providerString($data['country_code2'] ?? null) ??
                providerString($data['country_code'] ?? null);
            $latitude = roundProviderCoordinate($data['latitude'] ?? null);
            $longitude = roundProviderCoordinate($data['longitude'] ?? null);
            break;

        default:
            return null;
    }

    if (isset($coordinates) && is_array($coordinates)) {
        $latitude = roundProviderCoordinate($coordinates[0]);
        $longitude = roundProviderCoordinate($coordinates[1]);
    }
    if (is_string($countryCode)) {
        $countryCode = strtoupper($countryCode);
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            $countryCode = null;
        }
    }
    if ($countryName === null && $countryCode !== null) {
        $countryName = $countryCode;
    }
    $latitude = is_numeric($latitude) ? roundProviderCoordinate($latitude) : null;
    $longitude = is_numeric($longitude) ? roundProviderCoordinate($longitude) : null;
    if ($latitude !== null && ($latitude < -90.0 || $latitude > 90.0)) {
        $latitude = null;
    }
    if ($longitude !== null && ($longitude < -180.0 || $longitude > 180.0)) {
        $longitude = null;
    }

    $location = null;
    $locationData = [
        'city' => $city,
        'region' => $region,
        'country' => $countryName,
        'country_code' => $countryCode,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];
    $location = normalizeApproximateLocation([
        'city' => $city,
        'region' => $region,
        'country' => $countryName,
        'countryCode' => $countryCode,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ], $providerId);
    if ($isp === null && $location === null) {
        return null;
    }

    $processedCountry = $countryCode ?: $countryName;
    $processed = $ip;
    if ($isp !== null) {
        $processed .= ' - ' . $isp;
        if ($processedCountry !== null) {
            $processed .= ', ' . $processedCountry;
        }
    } elseif ($processedCountry !== null) {
        $processed .= ' - ' . $processedCountry;
    }

    $rawIspInfo = ['ip' => $ip];
    if ($hostname = providerString($data['hostname'] ?? null)) {
        $rawIspInfo['hostname'] = $hostname;
    }
    if ($city !== null) {
        $rawIspInfo['city'] = $city;
    }
    if ($region !== null) {
        $rawIspInfo['region'] = $region;
    }
    if ($countryName !== null) {
        $rawIspInfo['country_name'] = $countryName;
    }
    if ($countryCode !== null) {
        $rawIspInfo['country'] = $countryCode;
        $rawIspInfo['country_code'] = $countryCode;
    }
    if ($latitude !== null && $longitude !== null) {
        $rawIspInfo['loc'] = $latitude . ',' . $longitude;
    }
    if ($isp !== null) {
        $rawIspInfo['org'] = $isp;
        $rawIspInfo['as_name'] = $isp;
    }
    if ($postal !== null) {
        $rawIspInfo['postal'] = $postal;
    }
    if ($timezone !== null) {
        $rawIspInfo['timezone'] = $timezone;
    }
    $rawIspInfo['source'] = $providerId;

    return [
        'processedString' => $processed,
        'rawIspInfo' => $rawIspInfo,
        'location' => $location,
    ];
}

function providerUrlFor($provider, $ip)
{
    $urlTemplate = $provider['url_template'] ?? '';
    if (!is_string($urlTemplate) || strpos($urlTemplate, '{ip}') === false) {
        return null;
    }
    $url = str_replace('{ip}', rawurlencode($ip), $urlTemplate);
    if (($provider['id'] ?? '') === 'ipinfo') {
        $apiKeyEnv = $provider['api_key_env'] ?? 'IPINFO_APIKEY';
        $apiKey = getIpInfoEnvironmentValue($apiKeyEnv);
        if (!is_string($apiKey) || trim($apiKey) === '') {
            $apiKey = getIpInfoApiKey();
        } else {
            $apiKey = trim($apiKey);
        }
        if ($apiKey !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') .
                'token=' . rawurlencode($apiKey);
        }
    }
    return $url;
}

function mergeEarlierProviderLocation($providerLocation, $earlierLocation)
{
    if (!is_array($providerLocation)) {
        return $earlierLocation;
    }
    if (!is_array($earlierLocation)) {
        return $providerLocation;
    }

    $providerHasPlace = array_key_exists('city', $providerLocation) ||
        array_key_exists('region', $providerLocation) ||
        array_key_exists('latitude', $providerLocation) ||
        array_key_exists('longitude', $providerLocation);
    $earlierHasPlace = array_key_exists('city', $earlierLocation) ||
        array_key_exists('region', $earlierLocation) ||
        array_key_exists('latitude', $earlierLocation) ||
        array_key_exists('longitude', $earlierLocation);

    $providerCode = $providerLocation['countryCode'] ?? null;
    $earlierCode = $earlierLocation['countryCode'] ?? null;
    if (is_string($providerCode) && is_string($earlierCode) &&
        strtoupper($providerCode) !== strtoupper($earlierCode)) {
        $providerHasPlace = array_key_exists('city', $providerLocation) ||
            array_key_exists('region', $providerLocation) ||
            array_key_exists('latitude', $providerLocation) ||
            array_key_exists('longitude', $providerLocation);
        if ($providerHasPlace) {
            return $providerLocation;
        }
        return $earlierLocation;
    }

    if (!$providerHasPlace && $earlierHasPlace) {
        $merged = $earlierLocation;
        if (!array_key_exists('country', $merged) && array_key_exists('country', $providerLocation)) {
            $merged['country'] = $providerLocation['country'];
        }
        if (!array_key_exists('countryCode', $merged) && array_key_exists('countryCode', $providerLocation)) {
            $merged['countryCode'] = $providerLocation['countryCode'];
        }
        $merged['source'] = $earlierLocation['source'] ?? ($providerLocation['source'] ?? 'provider');
        $merged['approximate'] = true;
        return $merged;
    }

    $merged = $providerLocation;
    foreach (['city', 'region', 'latitude', 'longitude', 'lat', 'lon'] as $key) {
        if (!array_key_exists($key, $merged) && array_key_exists($key, $earlierLocation)) {
            $merged[$key] = $earlierLocation[$key];
        }
    }
    foreach (['country', 'countryCode'] as $key) {
        if (!array_key_exists($key, $merged) && array_key_exists($key, $earlierLocation)) {
            $merged[$key] = $earlierLocation[$key];
        }
    }
    $merged['approximate'] = true;
    return $merged;
}

function mergeProviderResponseWithLocal($localJson, $providerResponse)
{
    if (!is_array($providerResponse)) {
        return is_string($localJson) ? json_decode($localJson, true) : null;
    }
    $local = is_string($localJson) ? json_decode($localJson, true) : null;
    if (!is_array($local)) {
        return $providerResponse;
    }

    $localRaw = is_array($local['rawIspInfo'] ?? null)
        ? $local['rawIspInfo']
        : [];
    $providerRaw = is_array($providerResponse['rawIspInfo'] ?? null)
        ? $providerResponse['rawIspInfo']
        : [];
    $raw = array_replace_recursive($localRaw, $providerRaw);

    $localLocation = is_array($local['location'] ?? null)
        ? $local['location']
        : null;
    $providerLocation = is_array($providerResponse['location'] ?? null)
        ? $providerResponse['location']
        : null;
    $location = mergeApproximateLocations($providerLocation, $localLocation);

    $localIsp = normalizeIspName($localRaw);
    $providerIsp = normalizeIspName($providerRaw);
    $isp = $providerIsp ?: $localIsp;
    $countryCode = is_array($location)
        ? normalizeIpInfoCountryCode($location)
        : null;
    $countryName = is_array($location)
        ? normalizeIpInfoCountryName($location)
        : null;
    $ip = providerString($localRaw['ip'] ?? null) ??
        providerString($providerRaw['ip'] ?? null) ??
        '';

    $processed = $ip;
    $processedCountry = $countryCode ?: $countryName;
    if ($isp !== null) {
        $processed .= ' - ' . $isp;
        if ($processedCountry !== null) {
            $processed .= ', ' . $processedCountry;
        }
    } elseif ($processedCountry !== null) {
        $processed .= ' - ' . $processedCountry;
    }

    $response = [
        'processedString' => $processed,
        'rawIspInfo' => $raw,
    ];
    if ($location !== null) {
        $response['location'] = $location;
    }
    return $response;
}

function getIspInfoProviderFallback($ip)
{
    if (isIpInfoDisabled()) {
        return null;
    }
    if (!isPublicIpAddress($ip)) {
        return null;
    }
    $cached = getPositiveProviderCache($ip);
    if (is_array($cached)) {
        return json_encode($cached);
    }
    if (getNegativeProviderCache($ip)) {
        return null;
    }

    $localJson = getIspInfo_ipinfoOfflineDb($ip);
    $local = is_string($localJson) ? json_decode($localJson, true) : null;
    $localComplete = is_array($local) &&
        isset($local['location']['city'], $local['location']['country']) &&
        is_array(normalizeIpInfoCoordinates($local['location'])) &&
        normalizeIspName(is_array($local['rawIspInfo'] ?? null) ? $local['rawIspInfo'] : null) !== null;
    if ($localComplete) {
        setPositiveProviderCache($ip, $local);
        return $localJson;
    }

    $registry = loadIpProviderRegistry();
    $timeout = isset($registry['provider_timeout_seconds'])
        ? (float) $registry['provider_timeout_seconds']
        : 1.5;
    $maxAttempts = isset($registry['max_attempts'])
        ? max(1, min(8, (int) $registry['max_attempts']))
        : 8;
    $attempted = 0;
    $providerResponse = null;
    $locationOnlyProviderResponse = null;

    foreach ($registry['providers'] as $provider) {
        if ($attempted >= $maxAttempts) {
            break;
        }
        if (($provider['enabled'] ?? false) !== true) {
            continue;
        }
        $providerId = $provider['id'] ?? '';
        if ($providerId === '' || isProviderCoolingDown($providerId)) {
            continue;
        }
        $attempted++;
        $url = providerUrlFor($provider, $ip);
        if (!is_string($url)) {
            setProviderCooldown($providerId);
            continue;
        }
        $json = providerHttpGet($url, $timeout);
        if (!is_string($json)) {
            setProviderCooldown($providerId);
            continue;
        }
        $data = json_decode($json, true);
        $normalized = normalizeProviderResult(
            $provider['normalizer'] ?? $providerId,
            $data,
            $ip
        );
        if (!is_array($normalized)) {
            setProviderCooldown($providerId);
            continue;
        }
        $normalizedIsp = normalizeIspName(
            is_array($normalized['rawIspInfo'] ?? null)
                ? $normalized['rawIspInfo']
                : null
        );
        if ($normalizedIsp === null) {
            if ($locationOnlyProviderResponse === null) {
                $locationOnlyProviderResponse = $normalized;
            }
            continue;
        }
        if (is_array($locationOnlyProviderResponse['location'] ?? null) &&
            is_array($normalized['location'] ?? null)) {
            $normalized['location'] = mergeEarlierProviderLocation(
                $normalized['location'],
                $locationOnlyProviderResponse['location']
            );
            $coordinates = normalizeIpInfoCoordinates($normalized['location']);
            if (is_array($coordinates)) {
                $normalized['rawIspInfo']['loc'] =
                    $coordinates[0] . ',' . $coordinates[1];
            }
        } elseif (is_array($locationOnlyProviderResponse['location'] ?? null) &&
            !is_array($normalized['location'] ?? null)) {
            $normalized['location'] = $locationOnlyProviderResponse['location'];
            $coordinates = normalizeIpInfoCoordinates($normalized['location']);
            if (is_array($coordinates)) {
                $normalized['rawIspInfo']['loc'] =
                    $coordinates[0] . ',' . $coordinates[1];
            }
        }
        $providerResponse = $normalized;
        break;
    }

    $selectedProviderResponse = $providerResponse ?: $locationOnlyProviderResponse;
    $response = mergeProviderResponseWithLocal($localJson, $selectedProviderResponse);
    if (is_array($response)) {
        if (isset($_GET['distance']) && in_array($_GET['distance'], ['mi', 'km'], true) &&
            is_array($response['location'] ?? null)) {
            $coordinates = normalizeIpInfoCoordinates($response['location']);
            if (is_array($coordinates)) {
                $serverLoc = getIpInfoServerLoc(getIpInfoApiKey());
                if (is_string($serverLoc)) {
                    $distance = calculateIpInfoDistance(
                        $coordinates[0] . ',' . $coordinates[1],
                        $serverLoc,
                        $_GET['distance']
                    );
                    if (is_string($distance)) {
                        $response['processedString'] .= ' (' . $distance . ')';
                    }
                }
            }
        }
        setPositiveProviderCache($ip, $response);
        return json_encode($response);
    }
    setNegativeProviderCache($ip);
    return null;
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
    if (is_array($location) &&
        (!array_key_exists('country', $location) || !array_key_exists('countryCode', $location))) {
        if (is_array($countryData)) {
            $countrySource = (isset($countryData['location']) || isset($countryData['subdivisions']) || isset($countryData['country']['names']))
                ? 'maxmind_country_mmdb'
                : 'ipinfo_country_mmdb';
            $countryLocation = normalizeApproximateLocation($countryData, $countrySource);
            $location = supplementLocationCountry($location, $countryLocation);
        }
        if (is_array($asnData) &&
            (!array_key_exists('country', $location) || !array_key_exists('countryCode', $location))) {
            $asnLocation = normalizeApproximateLocation($asnData, 'ipinfo_mmdb');
            $location = supplementLocationCountry($location, $asnLocation);
        }
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
        'rawIspInfo' => sanitizeIpInfoRawIspInfo($rawData, 'offline_mmdb'),
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
                $r = getIspInfoProviderFallback($ip);
                if (!is_null($r)) {
                    echo $r;
                } else {
                    echo formatResponse_simple($ip);
                }
            }
        }
    } else {
        echo formatResponse_simple($ip);
    }
}
