<?php

/**
 * Normalize and validate an IP address candidate from a request header.
 *
 * Trims whitespace, takes the first comma-separated token (for XFF-like
 * headers that may contain a chain of addresses), and validates the result
 * with filter_var().
 *
 * @param string $raw       Raw header value.
 * @param int    $extraFlags Additional FILTER_FLAG_* flags (e.g. FILTER_FLAG_IPV6).
 *
 * @return string|false The validated IP string, or false on failure.
 */
function normalizeCandidateIp($raw, $extraFlags = 0)
{
    $ip = trim($raw);
    // For XFF-like values, take the first address before a comma.
    if (($pos = strpos($ip, ',')) !== false) {
        $ip = trim(substr($ip, 0, $pos));
    }
    if ($ip === '') {
        return false;
    }
    $validated = filter_var($ip, FILTER_VALIDATE_IP, $extraFlags);
    if ($validated === false) {
        return false;
    }
    $packed = @inet_pton($validated);
    if ($packed === false) {
        return false;
    }
    if (strlen($packed) === 16 &&
        substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
        return @inet_ntop(substr($packed, 12));
    }
    $canonical = @inet_ntop($packed);
    return $canonical === false ? $validated : $canonical;
}

/**
 * Match an IP against an exact address or CIDR network.
 *
 * @param string $ip
 * @param string $network
 * @return bool
 */
function isIpInCidr($ip, $network)
{
    $network = trim($network);
    if ($network === '') {
        return false;
    }
    if (strpos($network, '/') === false) {
        $normalizedIp = normalizeCandidateIp($ip);
        $normalizedNetwork = normalizeCandidateIp($network);
        if ($normalizedIp === false || $normalizedNetwork === false) {
            return false;
        }
        $ipPacked = @inet_pton($normalizedIp);
        $networkPacked = @inet_pton($normalizedNetwork);
        return $ipPacked !== false && $networkPacked !== false &&
            strlen($ipPacked) === strlen($networkPacked) &&
            hash_equals($ipPacked, $networkPacked);
    }
    list($networkAddress, $prefixText) = explode('/', $network, 2);
    $networkAddress = normalizeCandidateIp($networkAddress);
    $prefix = filter_var($prefixText, FILTER_VALIDATE_INT);
    $ipPacked = @inet_pton($ip);
    $networkPacked = @inet_pton($networkAddress);
    if ($ipPacked === false || $networkPacked === false || $prefix === false ||
        strlen($ipPacked) !== strlen($networkPacked)) {
        return false;
    }
    $prefixMax = strlen($ipPacked) * 8;
    if ($prefix < 0 || $prefix > $prefixMax) {
        return false;
    }
    $fullBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;
    if ($fullBytes > 0 &&
        substr($ipPacked, 0, $fullBytes) !== substr($networkPacked, 0, $fullBytes)) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    $ipByte = ord($ipPacked[$fullBytes]);
    $networkByte = ord($networkPacked[$fullBytes]);
    return ($ipByte & $mask) === ($networkByte & $mask);
}

/**
 * Return true only for globally routable/unicast public IP addresses.
 */
function isPublicIpAddress($ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    $ipv4Reserved = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];
    $ipv6Reserved = [
        '::/128',
        '::1/128',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/32',
        '2001:2::/48',
        '2001:db8::/32',
        '2001:10::/28',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];
    $reserved = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ? $ipv4Reserved
        : $ipv6Reserved;
    foreach ($reserved as $network) {
        if (isIpInCidr($ip, $network)) {
            return false;
        }
    }
    return (bool) filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

function getTrustedProxyNetworks()
{
    $value = getenv('TRUSTED_PROXIES');
    if (!is_string($value) || trim($value) === '') {
        $value = getenv('TRUSTED_PROXY_IPS');
    }
    if (!is_string($value) || trim($value) === '') {
        if (isset($_ENV['TRUSTED_PROXIES']) && is_string($_ENV['TRUSTED_PROXIES'])) {
            $value = $_ENV['TRUSTED_PROXIES'];
        } elseif (isset($_ENV['TRUSTED_PROXY_IPS']) && is_string($_ENV['TRUSTED_PROXY_IPS'])) {
            $value = $_ENV['TRUSTED_PROXY_IPS'];
        }
    }
    if ((!is_string($value) || trim($value) === '') && defined('TRUSTED_PROXIES')) {
        $value = constant('TRUSTED_PROXIES');
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $value))));
}

function isTrustedProxyIp($ip)
{
    foreach (getTrustedProxyNetworks() as $network) {
        if (isIpInCidr($ip, $network)) {
            return true;
        }
    }
    return false;
}

/**
 * @return string
 */
function getClientIp()
{
    $remote = normalizeCandidateIp($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === false) {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    $remote = preg_replace('/^::ffff:/', '', $remote);

    // Forwarding headers are accepted only when the immediate peer is an
    // explicitly trusted proxy. This prevents clients from selecting provider
    // lookup targets or consuming provider quota with arbitrary X-Forwarded-*.
    if (!isTrustedProxyIp($remote)) {
        return preg_replace('/^::ffff:/', '', $remote);
    }

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IPV6'])) {
        $ip = normalizeCandidateIp($_SERVER['HTTP_CF_CONNECTING_IPV6'], FILTER_FLAG_IPV6);
        if ($ip !== false) {
            return preg_replace('/^::ffff:/', '', $ip);
        }
    }
    // Other forwarding / proxy headers — accept any valid IP.
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = normalizeCandidateIp($_SERVER[$header]);
            if ($ip !== false) {
                return preg_replace('/^::ffff:/', '', $ip);
            }
        }
    }
    // Fallback: REMOTE_ADDR is set by the web server and is always a single IP.
    $ip = normalizeCandidateIp($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip !== false) {
        return preg_replace('/^::ffff:/', '', $ip);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}
