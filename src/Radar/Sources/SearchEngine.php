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

    /** @return list<string> */
    public static function queries(): array
    {
        $text = Settings::get('search_queries') ?? self::DEFAULT_QUERIES;
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), static fn (string $q): bool => $q !== ''));
    }

    public static function configured(): bool
    {
        return (string) Settings::get('search_api_key', '') !== '';
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

        $cursor = (int) (Settings::get('search_cursor') ?? 0);
        $endpoint = Settings::get('search_endpoint') ?: self::ENDPOINT;
        $headers = ['X-Subscription-Token' => (string) Settings::get('search_api_key')];

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
        $url = (string) $item['url'];
        $title = trim(strip_tags((string) ($item['title'] ?? '')));
        $snippets = [(string) ($item['description'] ?? '')];
        foreach ((array) ($item['extra_snippets'] ?? []) as $extra) {
            $snippets[] = (string) $extra;
        }
        $description = trim(html_entity_decode(strip_tags(implode(' ', $snippets)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $info = ListingParser::parseUrl($url);
        $fromText = ListingParser::parseText($title . ' ' . $description);
        $fromSlug = ListingParser::parseText($info['slug_text']);
        $pick = static fn (string $key) => $fromText[$key] ?? $fromSlug[$key] ?? null;

        return [
            'url' => $url,
            'title' => mb_substr($title !== '' ? $title : $info['slug_text'], 0, 300),
            'description' => mb_substr($description, 0, 2000),
            'brand' => $pick('brand'),
            'model' => $fromText['brand'] !== null ? $fromText['model'] : ($fromSlug['model'] ?? $fromText['model']),
            'model_year' => $pick('model_year'),
            'km' => $pick('km'),
            'price' => $fromText['price'],
            'city' => $pick('city'),
            'fuel' => $pick('fuel'),
            'gearbox' => $pick('gearbox'),
            'damage' => $pick('damage'),
        ];
    }
}
