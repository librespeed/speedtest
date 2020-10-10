<?php

define('ID_OBFUSCATION_SALT_FILE', __DIR__."/idObfuscation_salt.php");

function getObfuscationSalt()
{
    if (!file_exists(ID_OBFUSCATION_SALT_FILE)) {
        $bytes = openssl_random_pseudo_bytes(4);

        $saltData = "<?php\n\n\$OBFUSCATION_SALT=0x".bin2hex($bytes).";\n";
        file_put_contents(SERVER_LOCATION_CACHE_FILE, $saltData);
    }

    require ID_OBFUSCATION_SALT_FILE;

    return isset($OBFUSCATION_SALT) ? $OBFUSCATION_SALT : 0;
}

/**
 * This is a simple reversible hash function I made for encoding and decoding test IDs.
 * It is not cryptographically secure, don't use it to hash passwords or something!
 */
function obfdeobf($id, $dec)
{
    $salt = getObfuscationSalt() & 0xFFFFFFFF;
    $id &= 0xFFFFFFFF;
    if ($dec) {
        $id ^= $salt;
        $id = (($id & 0xAAAAAAAA) >> 1) | ($id & 0x55555555) << 1;
        $id = (($id & 0x0000FFFF) << 16) | (($id & 0xFFFF0000) >> 16);

        return $id;
    }

    $id = (($id & 0x0000FFFF) << 16) | (($id & 0xFFFF0000) >> 16);
    $id = (($id & 0xAAAAAAAA) >> 1) | ($id & 0x55555555) << 1;

    return $id ^ $salt;
}

function obfuscateId($id)
{
    return str_pad(base_convert(obfdeobf($id + 1, false), 10, 36), 7, 0, STR_PAD_LEFT);
}

function deobfuscateId($id)
{
    return obfdeobf(base_convert($id, 36, 10), true) - 1;
}
