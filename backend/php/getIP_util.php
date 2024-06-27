<?php

/**
 * @return string
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $ip = preg_replace('/,.*/', '', $ip); # hosts are comma-separated, client is first
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    return preg_replace('/^::ffff:/', '', $ip);
}

