<?php

declare(strict_types=1);

namespace Ozoto\Support;

final class Text
{
    /** Karşılaştırma için sadeleştirir: küçük harf, Türkçe karakterler ASCII, fazla boşluk yok. */
    public static function fold(string $text): string
    {
        $text = strtr($text, ['İ' => 'i', 'I' => 'ı', 'Ç' => 'ç', 'Ğ' => 'ğ', 'Ö' => 'ö', 'Ş' => 'ş', 'Ü' => 'ü']);
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'â' => 'a', 'î' => 'i', 'û' => 'u', 'ë' => 'e', 'é' => 'e',
        ]);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** "1.6", "d-4d" gibi parçaları koruyarak kelimelere böler. */
    public static function tokens(string $text): array
    {
        $folded = self::fold(str_replace(['-', '_', '/', '(', ')', ',', '|'], ' ', $text));
        return array_values(array_filter(explode(' ', $folded), static fn (string $t): bool => $t !== '' && $t !== '.'));
    }

    /** "850.000", "850 000", "850000" → 850000; sayı yoksa null. */
    public static function amount(string $text): ?int
    {
        $digits = digits($text);
        if ($digits === '' || strlen($digits) > 10) {
            return null;
        }
        return (int) $digits;
    }
}
