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
    return filter_var($ip, FILTER_VALIDATE_IP, $extraFlags);
}

/**
 * @return string
 */
function getClientIp()
{
    // Cloudflare IPv6 header — must be a valid IPv6 address.
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
