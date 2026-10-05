<?php

declare(strict_types=1);

namespace Ozoto\Radar\Sources;

use Ozoto\Radar\ListingParser;
use Ozoto\Radar\Listings;
use Ozoto\Settings;
use Ozoto\Support\Http;

/**
 * Arama motoru kaynağı (Brave Search API).
 * İlan sitelerinin kendisine istek atmaz: arama motorunun dizinindeki ilan linklerini, başlıklarını ve
 * özetlerini resmî API ile alır. Kişisel veri (satıcı adı, telefon) işlenmez.
 */
final class SearchEngine
{
    public const NAME = 'arama';
    public const ENDPOINT = 'https://api.search.brave.com/res/v1/web/search';

    public const DEFAULT_QUERIES = <<<TXT
        site:sahibinden.com/ilan otomobil acil satılık
        site:sahibinden.com/ilan otomobil ihtiyaçtan satılık
        site:arabam.com/ilan acil satılık
        site:arabam.com/ilan sahibinden ihtiyaçtan
        site:letgo.com araba acil satılık
        site:facebook.com/marketplace/item araba acil satılık
        TXT;

    /** Türkiye ikinci el pazarında en çok alınıp satılan modeller: otomatik sorgular bunlar için üretilir. */
    public const MODELS = [
        'Fiat Egea', 'Fiat Linea', 'Fiat Doblo', 'Renault Clio', 'Renault Megane', 'Renault Symbol', 'Renault Fluence', 'Renault Taliant',
        'Dacia Duster', 'Dacia Sandero', 'Toyota Corolla', 'Toyota Auris', 'Toyota C-HR', 'Volkswagen Golf', 'Volkswagen Passat',
        'Volkswagen Polo', 'Volkswagen Jetta', 'Volkswagen Caddy', 'Ford Focus', 'Ford Fiesta', 'Ford Courier', 'Ford Connect',
        'Hyundai i20', 'Hyundai Accent Blue', 'Hyundai Tucson', 'Opel Astra', 'Opel Corsa', 'Peugeot 301', 'Peugeot 308', 'Peugeot 3008',
        'Citroen C-Elysee', 'Citroen C3', 'Honda Civic', 'Honda City', 'Skoda Octavia', 'Skoda Superb', 'Seat Leon', 'Nissan Qashqai',
        'Kia Ceed', 'Kia Sportage', 'BMW 3 Serisi', 'BMW 5 Serisi', 'Mercedes C Serisi', 'Mercedes E Serisi', 'Audi A3', 'Audi A4',
    ];

    /** Kelepir ihtimali yüksek ilan ifadeleri. */
    public const INTENTS = ['acil satılık', 'ihtiyaçtan satılık', 'hasarlı', 'sahibinden acil', 'nakit ihtiyacından'];

    public const SITES = ['site:sahibinden.com/ilan', 'site:arabam.com/ilan', 'site:letgo.com'];

    /** Panelden elle yazılan sorgular. @return list<string> */
    public static function customQueries(): array
    {
        $text = Settings::get('search_queries') ?? self::DEFAULT_QUERIES;
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), static fn (string $q): bool => $q !== ''));
    }

    /** Elle yazılanlar + (açıksa) otomatik üretilenler. @return list<string> */
    public static function queries(): array
    {
        $queries = self::customQueries();
        if (self::autoEnabled()) {
            $queries = [...$queries, ...self::generatedQueries()];
        }
        return array_values(array_unique($queries));
    }

    public static function autoEnabled(): bool
    {
        return Settings::get('search_auto') !== '0';
    }

    /**
     * Model × ifade × site sorguları. Sıra, art arda gelen sorgularda model ve site değişecek şekilde karıştırılır;
     * böylece bütçe az olsa bile her gün farklı modeller ve siteler taranır.
     *
     * @return list<string>
     */
    public static function generatedQueries(): array
    {
        $queries = [];
        foreach (self::INTENTS as $k => $intent) {
            foreach (self::MODELS as $m => $model) {
                $queries[] = self::SITES[($m + $k) % count(self::SITES)] . ' ' . $model . ' ' . $intent;
            }
        }
        return $queries;
    }

    /** Aylık sorgu bütçesi (API kredisi). */
    public static function budget(): int
    {
        $value = Settings::get('search_budget');
        return $value === null || $value === '' ? 1000 : max(0, (int) $value);
    }

    /** Bu ay kullanılan sorgu sayısı. */
    public static function used(): int
    {
        return Settings::get('search_month') === date('Y-m') ? (int) Settings::get('search_used', '0') : 0;
    }

    /** Bütçeyi aya eşit yayacak şekilde bir taramada çalışacak sorgu sayısı. */
    public static function perRun(): int
    {
        $interval = max(15, (int) (Settings::get('search_interval') ?: 60));
        $runsPerDay = max(1, intdiv(1440, $interval));
        $remaining = max(0, self::budget() - self::used());
        $daysLeft = max(1, (int) date('t') - (int) date('j') + 1);
        return max(0, min(50, (int) ceil($remaining / $daysLeft / $runsPerDay)));
    }

    public static function configured(): bool
    {
        return Settings::secret('search_api_key') !== '';
    }

    /**
     * Sıradaki sorguları çalıştırır (her çağrıda kaldığı yerden devam eder).
     *
     * @return array{queries: int, found: int, added: int, errors: list<string>}
     */
    public static function run(float $deadline, int $maxQueries = 10): array
    {
        $result = ['queries' => 0, 'found' => 0, 'added' => 0, 'errors' => []];
        $queries = self::queries();
        if (!self::configured() || $queries === []) {
            $result['errors'][] = 'Arama motoru API anahtarı girilmemiş.';
            return $result;
        }
        if ($maxQueries <= 0) {
            $result['errors'][] = 'Bu ayın sorgu bütçesi doldu (' . self::used() . '/' . self::budget() . '); ay başında devam eder.';
            return $result;
        }

        $cursor = (int) (Settings::get('search_cursor') ?? 0);
        $endpoint = Settings::get('search_endpoint') ?: self::ENDPOINT;
        $headers = ['X-Subscription-Token' => Settings::secret('search_api_key')];

        for ($i = 0; $i < min($maxQueries, count($queries)); $i++) {
            if (microtime(true) > $deadline) {
                break;
            }
            $query = $queries[$cursor % count($queries)];
            $cursor++;
            $url = $endpoint . '?' . http_build_query([
                'q' => $query,
                'country' => Settings::get('search_country') ?: 'TR',
                'search_lang' => 'tr',
                'count' => 20,
                'freshness' => Settings::get('search_freshness') ?: 'pd',
            ]);
            try {
                $payload = Http::json($url, $headers, 20);
            } catch (\Throwable $e) {
                $result['errors'][] = $query . ': ' . $e->getMessage();
                continue;
            }
            $result['queries']++;
            $month = date('Y-m');
            Settings::set('search_used', (string) ((Settings::get('search_month') === $month ? (int) Settings::get('search_used', '0') : 0) + 1));
            Settings::set('search_month', $month);
            foreach ((array) ($payload['web']['results'] ?? []) as $item) {
                $link = (string) ($item['url'] ?? '');
                if ($link === '' || !ListingParser::isListingUrl($link)) {
                    continue;
                }
                $result['found']++;
                $saved = Listings::upsert(self::NAME, self::toListing($item));
                if ($saved['created']) {
                    $result['added']++;
                }
            }
        }
        Settings::set('search_cursor', (string) ($cursor % max(1, count($queries))));
        return $result;
    }

    /**
     * @param array<string, mixed> $item Brave sonucu
     * @return array<string, mixed>
     */
    public static function toListing(array $item): array
    {
        $snippets = [(string) ($item['description'] ?? '')];
        foreach ((array) ($item['extra_snippets'] ?? []) as $extra) {
            $snippets[] = (string) $extra;
        }
        return ListingParser::toListing((string) $item['url'], (string) ($item['title'] ?? ''), implode(' ', $snippets));
    }
}
