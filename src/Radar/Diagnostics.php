<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Settings;
use Ozoto\Support\Http;

/**
 * Kamu ihale kaynaklarına sunucudan erişim testi. Kamu siteleri yurt dışı trafiği kısıtlayabildiği için
 * toplayıcılar sitenin barındığı (Türkiye'deki) sunucuda çalışacak; bu test oradan neyin göründüğünü kaydeder.
 */
final class Diagnostics
{
    /** @return list<array{name: string, url: string, status: int, ms: int, note: string, sample: string}> */
    public static function run(): array
    {
        $checks = [
            ['ilan.gov.tr ilan arama (icra ilanları dahil)', 'POST', 'https://www.ilan.gov.tr/api/api/services/app/Ad/AdsByFilter', '{"keys":{},"skipCount":0,"maxResultCount":5}'],
            ['ilan.gov.tr ana sayfa', 'GET', 'https://www.ilan.gov.tr/', null],
            ['e-ihale (Ticaret Bakanlığı)', 'GET', 'https://www.eihale.gov.tr/', null],
            ['UYAP e-Satış', 'GET', 'https://esatis.uyap.gov.tr/', null],
            ['TSB kasko listesi', 'GET', 'https://www.tsb.org.tr/InsuranceData/GetVehicleYearList', null],
        ];

        $results = [];
        foreach ($checks as [$name, $method, $url, $body]) {
            $started = microtime(true);
            try {
                $response = $method === 'POST'
                    ? Http::post($url, (string) $body, ['Content-Type' => 'application/json-patch+json', 'Accept' => 'text/plain'], 20)
                    : Http::get($url, [], 20);
                $note = $response['status'] >= 200 && $response['status'] < 300 ? 'Erişilebilir' : 'Sunucu HTTP ' . $response['status'] . ' döndürdü';
                $sample = mb_substr($response['body'], 0, 3000);
                $status = $response['status'];
            } catch (\Throwable $e) {
                $status = 0;
                $note = 'Bağlantı kurulamadı: ' . $e->getMessage();
                $sample = '';
            }
            $results[] = [
                'name' => $name,
                'url' => $url,
                'status' => $status,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'note' => $note,
                'sample' => $sample,
            ];
        }

        Settings::set('diagnostics_last', json_encode(['at' => now(), 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        return $results;
    }

    /** @return array{at: string, results: list<array<string, mixed>>}|null */
    public static function last(): ?array
    {
        $data = json_decode((string) Settings::get('diagnostics_last', ''), true);
        return is_array($data) ? $data : null;
    }

    /** Sayfa incelemesinin istek atabileceği alan adları (yalnızca kamu ihale kaynakları). */
    public const PROBE_HOSTS = ['www.eihale.gov.tr', 'eihale.gov.tr', 'www.ilan.gov.tr', 'ilan.gov.tr', 'esatis.uyap.gov.tr'];

    /**
     * Kamu ihale sitesinin herkese açık bir sayfasını veya veri adresini sunucudan indirip yapısını özetler:
     * yüklediği betikler ve kodun içinde geçen veri (API) adresleri. Toplayıcıyı doğru adreslere göre yazmak için.
     *
     * @return array<string, mixed>
     */
    public static function probe(string $url, string $method = 'GET', string $body = ''): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, self::PROBE_HOSTS, true)) {
            throw new \InvalidArgumentException('Yalnızca şu adresler incelenebilir: ' . implode(', ', self::PROBE_HOSTS));
        }

        $started = microtime(true);
        $response = $method === 'POST'
            ? Http::post($url, $body, ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/plain, */*'], 30)
            : Http::get($url, ['Accept' => '*/*'], 30);
        $text = $response['body'];
        $origin = 'https://' . $host;

        $scripts = [];
        if (preg_match_all('#<script[^>]+src=["\']([^"\']+)["\']#i', $text, $m) > 0) {
            foreach ($m[1] as $src) {
                $scripts[] = str_starts_with($src, 'http') ? $src : $origin . '/' . ltrim($src, '/');
            }
        }

        // Kodda geçen adresler: tam URL'ler, "/api/..." yolları ve apiUrl/baseUrl gibi ortam değişkenleri.
        $patterns = [
            '#["\'`](https?://[a-z0-9.-]+\.(?:gov|com|org|net)\.tr[^"\'`\s<>]*)["\'`]#i',
            '#["\'`]([^"\'`\s<>]{0,80}/api/[^"\'`\s<>]{1,160})["\'`]#i',
            '#((?:api|base|service|backend)Url\s*:\s*["\'`][^"\'`]{1,200}["\'`])#i',
        ];
        $endpoints = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $found, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($found[1] as [$match, $offset]) {
                    if (isset($endpoints[$match]) || count($endpoints) >= 120) {
                        continue;
                    }
                    $from = max(0, $offset - 160);
                    $endpoints[$match] = substr($text, $from, 160 + strlen($match) + 200);
                }
            }
        }

        $result = [
            'at' => now(),
            'url' => $url,
            'method' => $method,
            'status' => $response['status'],
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'length' => strlen($text),
            'scripts' => array_values(array_unique($scripts)),
            'endpoints' => array_map(
                static fn (string $endpoint, string $context): array => ['value' => $endpoint, 'context' => mb_convert_encoding($context, 'UTF-8', 'UTF-8')],
                array_keys($endpoints),
                array_values($endpoints),
            ),
            'sample' => mb_substr($text, 0, 12000),
        ];

        // settings.value MySQL'de TEXT (64 KB); sığmazsa önce örnek metni, sonra bağlamları kısalt.
        $json = (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 60000) {
            $result['sample'] = mb_substr($text, 0, 2000);
            $json = (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        }
        if (strlen($json) > 60000) {
            $result['endpoints'] = array_map(static fn (array $e): array => ['value' => $e['value'], 'context' => mb_substr($e['context'], 0, 220)], $result['endpoints']);
            $json = (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        }
        Settings::set('probe_last', $json);
        return $result;
    }

    /** @return array<string, mixed>|null */
    public static function lastProbe(): ?array
    {
        $data = json_decode((string) Settings::get('probe_last', ''), true);
        return is_array($data) ? $data : null;
    }
}
