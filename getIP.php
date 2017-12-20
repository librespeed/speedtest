<?php
    header('Content-Type: text/plain; charset=utf-8');
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        echo $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['X-Real-IP'])) {
        echo $_SERVER['X-Real-IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        echo $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        echo $_SERVER['REMOTE_ADDR'];
    }
?>
