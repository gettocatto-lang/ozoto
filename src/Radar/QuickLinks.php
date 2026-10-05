<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Radar\Sources\SearchEngine;
use Ozoto\Support\Text;

/**
 * "Kelepir linkleri": popüler modeller için sahibinden/arabam arama sayfalarına hazır linkler (en ucuzdan sırala, son 24 saat,
 * sayfada 50 ilan). Ekip linke tıklar, eklenti sayfadaki ilanları puanlar; aynı linkler sitede "Aramayı kaydet" ile
 * e-posta bildirimine de bağlanır. Adres yapıları Ekim 2026'da arama motoru dizininden doğrulandı:
 * sahibinden.com/<marka>-<model>[/<şehir>], .../ikinci-el/sahibinden; arabam.com/ikinci-el/otomobil/<marka>-<model>-sahibinden.
 */
final class QuickLinks
{
    /** Ticari araç kategorisinde listelenen modeller (otomobil adresi farklı olabilir). */
    private const COMMERCIAL = ['Fiat Doblo', 'Volkswagen Caddy', 'Ford Courier', 'Ford Connect'];

    /** Sitelerin marka adresi. */
    private const BRAND_SLUGS = ['mercedes' => 'mercedes-benz'];

    /** @return list<string> */
    public static function models(): array
    {
        return array_values(array_diff(SearchEngine::MODELS, self::COMMERCIAL));
    }

    /** "Mercedes C Serisi" → "mercedes-benz-c-serisi", "Toyota C-HR" → "toyota-c-hr". */
    public static function slug(string $model): string
    {
        $parts = explode(' ', Text::fold($model), 2);
        $brand = self::BRAND_SLUGS[$parts[0]] ?? $parts[0];
        $rest = trim((string) preg_replace('/[^a-z0-9]+/', '-', $parts[1] ?? ''), '-');
        return $brand . ($rest !== '' ? '-' . $rest : '');
    }

    public static function citySlug(string $city): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', Text::fold($city)), '-');
    }

    /**
     * @return list<array{model: string, links: list<array{label: string, url: string, site: string}>}>
     */
    public static function all(string $city = ''): array
    {
        $citySlug = $city !== '' ? '/' . self::citySlug($city) : '';
        $cheapest = '?sorting=price_asc&pagingSize=50';
        $rows = [[
            'model' => 'Tüm otomobiller',
            'links' => [
                ['label' => 'Son 24 saat, en ucuz', 'site' => 'sahibinden', 'url' => 'https://www.sahibinden.com/otomobil' . $citySlug . $cheapest . '&date=1day'],
                ['label' => 'Sahibinden ilanları, en ucuz', 'site' => 'sahibinden', 'url' => 'https://www.sahibinden.com/otomobil/ikinci-el/sahibinden' . $cheapest],
                ['label' => 'Tüm ilanlar', 'site' => 'arabam', 'url' => 'https://www.arabam.com/ikinci-el/otomobil'],
            ],
        ]];
        foreach (self::models() as $model) {
            $slug = self::slug($model);
            $rows[] = [
                'model' => $model,
                'links' => [
                    ['label' => 'Son 24 saat, en ucuz', 'site' => 'sahibinden', 'url' => 'https://www.sahibinden.com/' . $slug . $citySlug . $cheapest . '&date=1day'],
                    ['label' => 'Sahibinden ilanları, en ucuz', 'site' => 'sahibinden', 'url' => 'https://www.sahibinden.com/' . $slug . '/ikinci-el/sahibinden' . $cheapest],
                    ['label' => 'Sahibinden ilanları', 'site' => 'arabam', 'url' => 'https://www.arabam.com/ikinci-el/otomobil/' . $slug . '-sahibinden'],
                ],
            ];
        }
        return $rows;
    }
}
