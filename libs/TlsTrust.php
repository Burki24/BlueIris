<?php

declare(strict_types=1);

namespace BlueIris;

final class TlsTrust
{
    public static function isValid(string $pem): bool
    {
        if ($pem === '') {
            return true;
        }
        if (!defined('CURLOPT_CAINFO_BLOB') || !function_exists('openssl_x509_read')) {
            return false;
        }
        if (!preg_match_all('~-----BEGIN CERTIFICATE-----[\sA-Za-z0-9+/=]+-----END CERTIFICATE-----~', $pem, $matches)
            || trim(str_replace($matches[0], '', $pem)) !== '') {
            return false;
        }
        foreach ($matches[0] as $certificate) {
            if (@openssl_x509_read($certificate) === false) {
                return false;
            }
        }
        return true;
    }

    public static function configure($curl, string $pem): bool
    {
        return $pem === '' || (self::isValid($pem) && curl_setopt($curl, CURLOPT_CAINFO_BLOB, $pem . "\n"));
    }
}
