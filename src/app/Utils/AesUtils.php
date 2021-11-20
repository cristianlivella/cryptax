<?php

namespace CrypTax\Utils;

class AesUtils
{
    const METHOD = 'aes-256-cbc';

    public static function encrypt($plain, $key) {
        $key = hex2bin($key);
        $iv = hex2bin(md5(microtime() . random_int(PHP_INT_MIN, PHP_INT_MAX)));
        $data = openssl_encrypt($plain, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $data);
    }

    public static function decrypt($encrypted, $key) {
        $key = hex2bin($key);
        $decoded = base64_decode($encrypted);
        $iv = substr($decoded, 0, 16);
        $data = substr($decoded, 16);
        return openssl_decrypt($data, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
    }

    public static function generateKey() {
        return bin2hex(random_bytes(32));
    }
}
