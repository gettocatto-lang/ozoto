<?php

declare(strict_types=1);

namespace Ozoto\Support;

/** Ayarlardaki şifre ve API anahtarlarını veritabanında AES-256-GCM ile şifreli tutar (anahtar: config app_key). */
final class Secret
{
    private const PREFIX = 'enc:v1:';

    public static function encrypt(string $plain): string
    {
        if ($plain === '' || !function_exists('openssl_encrypt')) {
            return $plain;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $cipher === false ? $plain : self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $stored): string
    {
        if ($stored === null || !str_starts_with($stored, self::PREFIX)) {
            return (string) $stored;
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29 || !function_exists('openssl_decrypt')) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }

    private static function key(): string
    {
        return hash('sha256', 'ozoto-settings|' . (string) config('app_key', 'ozoto'), true);
    }
}
