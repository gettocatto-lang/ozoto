<?php

declare(strict_types=1);

namespace Ozoto\Support;

final class Phone
{
    /** Türkiye numarasını 05321234567 biçimine getirir; geçersizse null. */
    public static function normalize(string $raw): ?string
    {
        $d = digits($raw);
        if (strlen($d) === 12 && str_starts_with($d, '90')) {
            $d = substr($d, 2);
        } elseif (strlen($d) === 11 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }
        if (strlen($d) !== 10 || !in_array($d[0], ['2', '3', '4', '5'], true)) {
            return null;
        }
        return '0' . $d;
    }

    /** 05321234567 → 0532 123 45 67 */
    public static function pretty(string $phone): string
    {
        $d = digits($phone);
        if (strlen($d) !== 11) {
            return $phone;
        }
        return sprintf('%s %s %s %s', substr($d, 0, 4), substr($d, 4, 3), substr($d, 7, 2), substr($d, 9, 2));
    }

    /** wa.me bağlantısı için ülke kodlu numara: 05321234567 → 905321234567 */
    public static function international(string $phone): string
    {
        $normalized = self::normalize($phone);
        return $normalized === null ? digits($phone) : '90' . substr($normalized, 1);
    }
}
