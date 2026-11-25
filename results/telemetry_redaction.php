<?php

function redactTelemetryIspInfo($ispinfo)
{
    $decoded = json_decode($ispinfo, true);
    if (!is_array($decoded)) {
        return $ispinfo;
    }

    foreach (
        [
            'location',
            'city',
            'region',
            'country',
            'countryCode',
            'country_name',
            'country_code',
            'loc',
            'latitude',
            'longitude',
            'lat',
            'lon',
            'postal',
            'postal_code',
            'timezone',
            'time_zone',
            'metro',
        ] as $key
    ) {
        unset($decoded[$key]);
    }

    if (isset($decoded['rawIspInfo']) && is_array($decoded['rawIspInfo'])) {
        $raw = $decoded['rawIspInfo'];
        $kept = [];

        foreach (
            [
                'org',
                'as_name',
                'organization',
                'isp',
                'network_name',
                'organization_name',
                'autonomous_system_organization',
                'as_number',
                'asn_name',
                'domain',
                'network',
                'route',
                'type',
            ] as $key
        ) {
            if (array_key_exists($key, $raw) && is_string($raw[$key])) {
                $kept[$key] = $raw[$key];
            } elseif (array_key_exists($key, $raw) && is_numeric($raw[$key])) {
                $kept[$key] = $raw[$key];
            }
        }

        if (isset($raw['asn']) && is_array($raw['asn']) &&
            isset($raw['asn']['name']) && is_string($raw['asn']['name'])) {
            $kept['asn'] = ['name' => $raw['asn']['name']];
        } elseif (isset($raw['asn']) && is_string($raw['asn'])) {
            $kept['asn'] = $raw['asn'];
        }

        $decoded['rawIspInfo'] = count($kept) > 0 ? $kept : '';
    } elseif (isset($decoded['rawIspInfo']) && !is_string($decoded['rawIspInfo'])) {
        $decoded['rawIspInfo'] = '';
    }

    $ispName = '';
    if (isset($decoded['rawIspInfo']) && is_array($decoded['rawIspInfo'])) {
        $raw = $decoded['rawIspInfo'];
        foreach (
            ['org', 'organization', 'isp', 'as_name', 'network_name', 'organization_name',
             'autonomous_system_organization', 'autonomous_system_number'] as $key
        ) {
            if (isset($raw[$key]) && is_string($raw[$key])) {
                $ispName = trim(preg_replace('/^AS\d+\s*/i', '', $raw[$key]));
                break;
            }
        }
        if ($ispName === '' && isset($raw['asn']['name']) && is_string($raw['asn']['name'])) {
            $ispName = trim($raw['asn']['name']);
        }
    }
    $decoded['processedString'] = '0.0.0.0';
    if ($ispName !== '') {
        $decoded['processedString'] .= ' - ' . $ispName;
    }

    return json_encode($decoded, JSON_UNESCAPED_SLASHES);
}
