<?php

require_once dirname(__DIR__, 2) . '/backend/getIP.php';
require_once dirname(__DIR__, 2) . '/results/telemetry_redaction.php';

$failures = [];

function testGetIpInfo($name, $condition)
{
    global $failures;
    if (!$condition) {
        $failures[] = $name;
    }
}

$keyFile = dirname(__DIR__, 2) . '/backend/getIP_ipInfo_apikey.php';
$originalKeyFile = file_exists($keyFile) ? file_get_contents($keyFile) : null;
$providerCacheFile = IP_PROVIDER_CACHE_FILE;
$originalProviderCache = file_exists($providerCacheFile)
    ? file_get_contents($providerCacheFile)
    : null;

testGetIpInfo(
    'unauthenticated IPinfo URL omits token',
    buildIpInfoApiUrl('128.94.2.134') === 'https://ipinfo.io/128.94.2.134/json'
);
testGetIpInfo(
    'authenticated IPinfo URL includes token',
    buildIpInfoApiUrl('128.94.2.134', 'test-secret') === 'https://ipinfo.io/128.94.2.134/json?token=test-secret'
);

testGetIpInfo(
    'malformed loc has no coordinates',
    normalizeIpInfoCoordinates(['loc' => 'not-a-location']) === null
);

$apiShapeLocation = normalizeApproximateLocation([
    'city' => 'Indianapolis',
    'region' => 'Indiana',
    'country' => 'US',
    'loc' => '39.7684,-86.1580',
], 'ipinfo_api');
testGetIpInfo(
    'IPinfo API shape normalizes city region country coordinates',
    $apiShapeLocation !== null
    && $apiShapeLocation['city'] === 'Indianapolis'
    && $apiShapeLocation['region'] === 'Indiana'
    && $apiShapeLocation['country'] === 'US'
    && $apiShapeLocation['countryCode'] === 'US'
    && $apiShapeLocation['latitude'] === 39.768
    && $apiShapeLocation['longitude'] === -86.1580
);

$ipInfoMmdbLocation = normalizeApproximateLocation([
    'city' => 'Indianapolis',
    'region' => 'Indiana',
    'country_code' => 'us',
    'loc' => '39.7684,-86.1580',
], 'ipinfo_mmdb');
testGetIpInfo(
    'flat IPinfo MMDB city fields normalize',
    $ipInfoMmdbLocation !== null
    && $ipInfoMmdbLocation['city'] === 'Indianapolis'
    && $ipInfoMmdbLocation['region'] === 'Indiana'
    && $ipInfoMmdbLocation['countryCode'] === 'US'
);

$maxMindLocation = normalizeApproximateLocation([
    'city' => ['names' => ['en' => 'Indianapolis']],
    'subdivisions' => [['iso_code' => 'IN', 'names' => ['en' => 'Indiana']]],
    'country' => ['iso_code' => 'US', 'names' => ['en' => 'United States']],
    'location' => ['latitude' => 39.7684, 'longitude' => -86.1580],
], 'maxmind_mmdb');
testGetIpInfo(
    'nested MaxMind MMDB city fields normalize',
    $maxMindLocation !== null
    && $maxMindLocation['city'] === 'Indianapolis'
    && $maxMindLocation['region'] === 'Indiana'
    && $maxMindLocation['country'] === 'United States'
    && $maxMindLocation['countryCode'] === 'US'
);

$mergedLocation = mergeApproximateLocations(
    [
        'country' => 'US',
        'countryCode' => 'US',
        'approximate' => true,
        'source' => 'ipinfo_api',
    ],
    [
        'city' => 'London',
        'region' => 'England',
        'country' => 'United Kingdom',
        'countryCode' => 'GB',
        'latitude' => 51.5074,
        'longitude' => -0.1278,
        'approximate' => true,
        'source' => 'maxmind_mmdb',
    ]
);
testGetIpInfo(
    'city MMDB country wins over conflicting ASN/API country',
    $mergedLocation['city'] === 'London'
    && $mergedLocation['country'] === 'United Kingdom'
    && $mergedLocation['countryCode'] === 'GB'
);

$mergedCountryLocation = mergeApproximateLocations(
    [
        'country' => 'US',
        'countryCode' => 'US',
        'approximate' => true,
        'source' => 'ipinfo_api',
    ],
    [
        'country' => 'United Kingdom',
        'countryCode' => 'GB',
        'approximate' => true,
        'source' => 'ipinfo_country_mmdb',
    ]
);
testGetIpInfo(
    'configured country MMDB replaces conflicting API country',
    $mergedCountryLocation['country'] === 'United Kingdom'
    && $mergedCountryLocation['countryCode'] === 'GB'
);

$detailedApiLocation = mergeApproximateLocations(
    [
        'city' => 'London',
        'region' => 'England',
        'country' => 'United Kingdom',
        'countryCode' => 'GB',
        'latitude' => 51.5074,
        'longitude' => -0.1278,
        'approximate' => true,
        'source' => 'ipinfo_api',
    ],
    [
        'country' => 'United States',
        'countryCode' => 'US',
        'approximate' => true,
        'source' => 'ipinfo_country_mmdb',
    ]
);
testGetIpInfo(
    'country-only MMDB does not replace detailed API location country',
    $detailedApiLocation['city'] === 'London'
    && $detailedApiLocation['country'] === 'United Kingdom'
    && $detailedApiLocation['countryCode'] === 'GB'
);

$partialCityLocation = supplementLocationCountry(
    [
        'city' => 'London',
        'latitude' => 51.5074,
        'longitude' => -0.1278,
        'approximate' => true,
        'source' => 'maxmind_mmdb',
    ],
    [
        'country' => 'United Kingdom',
        'countryCode' => 'GB',
    ]
);
testGetIpInfo(
    'city MMDB keeps coordinates and receives country database name/code',
    $partialCityLocation['city'] === 'London'
    && $partialCityLocation['latitude'] === 51.5074
    && $partialCityLocation['country'] === 'United Kingdom'
    && $partialCityLocation['countryCode'] === 'GB'
);

$partialCityWithAsnCountry = supplementLocationCountry(
    [
        'city' => 'London',
        'latitude' => 51.5074,
        'longitude' => -0.1278,
        'approximate' => true,
        'source' => 'maxmind_mmdb',
    ],
    null
);
$partialCityWithAsnCountry = supplementLocationCountry(
    $partialCityWithAsnCountry,
    [
        'country' => 'United Kingdom',
        'countryCode' => 'GB',
    ]
);
testGetIpInfo(
    'city MMDB can receive paired ASN country fallback',
    $partialCityWithAsnCountry['country'] === 'United Kingdom'
    && $partialCityWithAsnCountry['countryCode'] === 'GB'
);

$existingUkLocation = supplementLocationCountry(
    [
        'city' => 'London',
        'country' => 'United Kingdom',
        'approximate' => true,
        'source' => 'maxmind_mmdb',
    ],
    [
        'country' => 'United States',
        'countryCode' => 'US',
    ]
);
testGetIpInfo(
    'partial country name is not paired with unrelated US fallback code',
    $existingUkLocation['country'] === 'United Kingdom'
    && !isset($existingUkLocation['countryCode'])
);

$existingUkLocation = supplementLocationCountry(
    $existingUkLocation,
    [
        'country' => 'United Kingdom',
        'countryCode' => 'GB',
    ]
);
testGetIpInfo(
    'matching partial country name receives matching fallback code',
    $existingUkLocation['country'] === 'United Kingdom'
    && $existingUkLocation['countryCode'] === 'GB'
);

$countryFallback = [
    'as_name' => 'Example Networks',
    'country_name' => 'United States',
    'country_code' => 'US',
];
testGetIpInfo(
    'offline country ASN fallback retains ISP and country',
    normalizeIspName($countryFallback) === 'Example Networks'
    && normalizeApproximateLocation($countryFallback, 'ipinfo_mmdb')['country'] === 'United States'
);

if (class_exists('MaxMind\\Db\\Reader') && is_readable(dirname(__DIR__, 2) . '/backend/country_asn.mmdb')) {
    $offlineFallback = json_decode(
        getIspInfo_ipinfoOfflineDb('128.94.2.134'),
        true
    );
    testGetIpInfo(
        'actual offline country ASN database fallback returns structured metadata',
        is_array($offlineFallback)
        && isset($offlineFallback['processedString'])
        && isset($offlineFallback['rawIspInfo'])
        && isset($offlineFallback['location'])
    );
}

testGetIpInfo(
    'out-of-range loc is rejected',
    normalizeIpInfoCoordinates(['loc' => '91.0,-86.1580']) === null
);

testGetIpInfo(
    'CGNAT and deprecated site-local IPv6 bypass providers',
    !isPublicIpAddress('100.64.0.1')
    && !isPublicIpAddress('fec0::1')
);

testGetIpInfo(
    'public IP remains provider eligible',
    isPublicIpAddress('128.94.2.134')
);

$trustedProxyBackup = [
    'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
    'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
];
putenv('TRUSTED_PROXIES=10.0.0.0/8');
$_SERVER['REMOTE_ADDR'] = '10.1.2.3';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10';
testGetIpInfo(
    'trusted proxy forwarding is honored',
    getClientIp() === '203.0.113.10'
);
putenv('TRUSTED_PROXIES=');
$_SERVER['REMOTE_ADDR'] = '10.1.2.3';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.11';
testGetIpInfo(
    'untrusted forwarding header is ignored',
    getClientIp() === '10.1.2.3'
);
foreach ($trustedProxyBackup as $key => $value) {
    if ($value === null) {
        unset($_SERVER[$key]);
    } else {
        $_SERVER[$key] = $value;
    }
}

$providerResult = normalizeProviderResult('ipwhois', [
    'success' => true,
    'city' => 'Boydton',
    'region' => 'Virginia',
    'country' => 'United States',
    'country_code' => 'US',
    'latitude' => 36.66764,
    'longitude' => -78.38754,
    'connection' => ['org' => 'Microsoft Corporation'],
], '128.94.2.134');
testGetIpInfo(
    'provider result is normalized and coordinates are rounded',
    is_array($providerResult)
    && $providerResult['location']['city'] === 'Boydton'
    && $providerResult['location']['latitude'] === 36.668
    && $providerResult['location']['longitude'] === -78.388
    && $providerResult['location']['source'] === 'ipwhois'
);
testGetIpInfo(
    'provider result does not retain raw provider payload',
    is_array($providerResult)
    && !isset($providerResult['rawIspInfo']['success'])
    && isset($providerResult['rawIspInfo']['source'])
);

@unlink(IP_PROVIDER_CACHE_FILE);

setPositiveProviderCache('203.0.113.7', $providerResult);
$providerCachedResult = getPositiveProviderCache('203.0.113.7');
testGetIpInfo(
    'provider positive cache retains normalized response',
    is_array($providerCachedResult)
    && isset($providerCachedResult['rawIspInfo']['source'])
    && isset($providerCachedResult['location']['city'])
    && !isset($providerCachedResult['rawIspInfo']['ip'])
    && !isset($providerCachedResult['rawIspInfo']['loc'])
);
setNegativeProviderCache('198.51.100.7');
testGetIpInfo(
    'provider negative cache is enforced',
    getNegativeProviderCache('198.51.100.7')
);
$GLOBALS['getIP_provider_http_get'] = function ($url, $timeoutSeconds) {
    if (strpos($url, 'ipinfo.io') !== false) {
        return json_encode(['error' => 'unavailable']);
    }
    return json_encode([
        'success' => true,
        'city' => 'Boydton',
        'region' => 'Virginia',
        'country' => 'United States',
        'country_code' => 'US',
        'latitude' => 36.66764,
        'longitude' => -78.38754,
        'connection' => ['org' => 'Microsoft Corporation'],
    ]);
};
$providerFallbackResponse = json_decode(
    getIspInfoProviderFallback('128.94.2.134'),
    true
);
testGetIpInfo(
    'ordered provider fallback adds normalized location',
    is_array($providerFallbackResponse)
    && isset($providerFallbackResponse['location']['city'])
    && $providerFallbackResponse['location']['country'] === 'United States'
    && isset($providerFallbackResponse['location']['latitude'])
);

setProviderCooldown('ipwhois');
testGetIpInfo(
    'provider cooldown is enforced',
    isProviderCoolingDown('ipwhois')
);
unset($GLOBALS['getIP_provider_http_get']);

$GLOBALS['getIP_provider_http_get'] = function () {
    throw new Exception('reserved IP provider call must not occur');
};
testGetIpInfo(
    'reserved IP bypasses provider fallback',
    getIspInfoProviderFallback('100.64.0.1') === null
);
unset($GLOBALS['getIP_provider_http_get']);

putenv('DISABLE_IPINFO=true');
testGetIpInfo('DISABLE_IPINFO disables metadata', isIpInfoDisabled());
$GLOBALS['getIP_http_get'] = function ($url) {
    throw new Exception('IPinfo API should be disabled');
};
testGetIpInfo(
    'DISABLE_IPINFO prevents direct API calls',
    getIspInfo_ipinfoApi('128.94.2.134') === null
);
unset($GLOBALS['getIP_http_get']);
putenv('DISABLE_IPINFO=');

$GLOBALS['getIP_http_get'] = function ($url) {
    $GLOBALS['getIP_last_url'] = $url;
    return json_encode([
        'ip' => '128.94.2.134',
        'hostname' => '128-94-2-134.example',
        'city' => 'Indianapolis',
        'region' => 'Indiana',
        'country' => 'US',
        'loc' => '39.7684,-86.1580',
        'org' => 'AS00001 Example Networks',
    ]);
};

file_put_contents($keyFile, "<?php\n\n\$IPINFO_APIKEY = '';\n");
$noKeyResponse = json_decode(getIspInfo_ipinfoApi('128.94.2.134'), true);
testGetIpInfo(
    'API-key absent response is backward-compatible and structured',
    is_array($noKeyResponse)
    && isset($noKeyResponse['processedString'])
    && isset($noKeyResponse['rawIspInfo'])
    && isset($noKeyResponse['location'])
);
testGetIpInfo(
    'API-key absent call uses unauthenticated endpoint',
    strpos($GLOBALS['getIP_last_url'], '?token=') === false
);

file_put_contents($keyFile, "<?php\n\n\$IPINFO_APIKEY = 'test-secret';\n");
$keyResponse = json_decode(getIspInfo_ipinfoApi('128.94.2.134'), true);
testGetIpInfo(
    'API-key present response retains processed and raw fields',
    is_array($keyResponse)
    && isset($keyResponse['processedString'])
    && isset($keyResponse['rawIspInfo'])
    && isset($keyResponse['location'])
);
testGetIpInfo(
    'API-key present call includes token',
    strpos($GLOBALS['getIP_last_url'], '?token=test-secret') !== false
);

$GLOBALS['getIP_http_get'] = function ($url) {
    return json_encode([
        'city' => 'Indianapolis',
        'region' => 'Indiana',
        'country' => 'US',
        'loc' => '39.7684,-86.1580',
    ]);
};
$partialApiResponse = json_decode(getIspInfo_ipinfoApi('128.94.2.134'), true);
testGetIpInfo(
    'location-only API response is supplemented with offline ISP metadata',
    is_array($partialApiResponse)
    && isset($partialApiResponse['processedString'])
    && strpos($partialApiResponse['processedString'], ' - ') !== false
    && isset($partialApiResponse['location']['city'])
);

$GLOBALS['getIP_http_get'] = function ($url) {
    return json_encode([
        'city' => 'Indianapolis',
        'region' => 'Indiana',
        'country' => 'US',
        'loc' => '39.7684,-86.1580',
        'org' => 'AS8075',
    ]);
};
$asnOnlyApiResponse = json_decode(getIspInfo_ipinfoApi('128.94.2.134'), true);
testGetIpInfo(
    'ASN-only API org is replaced by offline ISP name',
    is_array($asnOnlyApiResponse)
    && isset($asnOnlyApiResponse['rawIspInfo']['as_name'])
    && $asnOnlyApiResponse['rawIspInfo']['org'] !== 'AS8075'
);

$GLOBALS['getIP_http_get'] = function ($url) {
    return json_encode([
        'city' => 'Indianapolis',
        'region' => 'Indiana',
        'country' => 'US',
        'loc' => 'not-a-location',
        'org' => 'AS00001 Example Networks',
    ]);
};
$malformedLocationResponse = json_decode(getIspInfo_ipinfoApi('128.94.2.134'), true);
testGetIpInfo(
    'malformed loc is omitted without discarding valid city metadata',
    is_array($malformedLocationResponse)
    && isset($malformedLocationResponse['location'])
    && !isset($malformedLocationResponse['location']['latitude'])
    && $malformedLocationResponse['location']['city'] === 'Indianapolis'
);

$redactedIspInfo = redactTelemetryIspInfo(json_encode([
    'processedString' => '128.94.2.134 - Example Networks, US',
    'rawIspInfo' => [
        'ip' => '128.94.2.134',
        'hostname' => 'example.test',
        'city' => 'Indianapolis',
        'region' => 'Indiana',
        'country' => 'US',
        'loc' => '39.7684,-86.1580',
        'postal' => '46204',
        'org' => 'AS00001 Example Networks',
    ],
    'location' => [
        'city' => 'Indianapolis',
        'region' => 'Indiana',
        'country' => 'US',
        'latitude' => 39.7684,
        'longitude' => -86.1580,
    ],
]));
$redactedIspInfoData = json_decode($redactedIspInfo, true);
testGetIpInfo(
    'telemetry redaction removes location and sensitive raw fields',
    is_array($redactedIspInfoData)
    && !isset($redactedIspInfoData['location'])
    && isset($redactedIspInfoData['rawIspInfo']['org'])
    && !isset($redactedIspInfoData['rawIspInfo']['ip'])
    && !isset($redactedIspInfoData['rawIspInfo']['city'])
    && !isset($redactedIspInfoData['rawIspInfo']['loc'])
    && $redactedIspInfoData['processedString'] === '0.0.0.0 - Example Networks'
);

$nestedRedactedIspInfo = redactTelemetryIspInfo(json_encode([
    'processedString' => '128.94.2.134 - Example Networks, US',
    'rawIspInfo' => [
        'asn' => [
            'name' => 'Example Networks',
            'country' => 'US',
            'city' => 'Indianapolis',
            'loc' => '39.7684,-86.1580',
        ],
        'city' => 'Indianapolis',
        'loc' => '39.7684,-86.1580',
    ],
]));
$nestedRedactedIspInfoData = json_decode($nestedRedactedIspInfo, true);
testGetIpInfo(
    'nested ASN redaction retains only ASN name',
    is_array($nestedRedactedIspInfoData)
    && isset($nestedRedactedIspInfoData['rawIspInfo']['asn']['name'])
    && !isset($nestedRedactedIspInfoData['rawIspInfo']['asn']['country'])
    && !isset($nestedRedactedIspInfoData['rawIspInfo']['city'])
    && !isset($nestedRedactedIspInfoData['rawIspInfo']['loc'])
);

if ($originalKeyFile !== null) {
    file_put_contents($keyFile, $originalKeyFile);
} else {
    @unlink($keyFile);
}
if ($originalProviderCache !== null) {
    file_put_contents($providerCacheFile, $originalProviderCache);
} else {
    @unlink($providerCacheFile);
}
unset($GLOBALS['getIP_http_get']);

if (count($failures) > 0) {
    fwrite(STDERR, "getIP PHP tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "getIP PHP tests passed\n";
