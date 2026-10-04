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
}
