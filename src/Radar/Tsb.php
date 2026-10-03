<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Database;
use Ozoto\Support\Http;
use Ozoto\Support\Text;

/**
 * Türkiye Sigorta Birliği kasko değer listesi istemcisi.
 * TSB'nin sitesindeki formun kullandığı herkese açık uç noktalar (kimlik doğrulama yok); sonuçlar veritabanında önbelleklenir.
 */
final class Tsb
{
    private const BASE = 'https://www.tsb.org.tr';
    private const TTL = 10 * 86400;

    /** Katalogdaki marka → TSB'deki marka adında aranacak kelime(ler). TSB yerli üretimi ayrı listeler: "RENAULT (OYAK)", "TOFAS-FIAT". */
    private const BRAND_KEYS = [
        'Mercedes-Benz' => ['mercedes'],
        'Citroën' => ['citroen'],
        'Tofaş' => ['tofas'],
        'Land Rover' => ['land rover', 'landrover'],
        'Alfa Romeo' => ['alfa romeo'],
    ];

    /** @return list<int> */
    public static function years(): array
    {
        return array_map('intval', (array) self::cached('years', '/InsuranceData/GetVehicleYearList'));
    }

    /** @return list<int> Markanın o yıldaki tüm TSB kayıtları (ör. Renault: RENAULT + RENAULT (OYAK)). */
    public static function brandIds(int $year, string $brand): array
    {
        $keys = self::BRAND_KEYS[$brand] ?? [Text::fold($brand)];
        $ids = [];
        foreach ((array) self::cached('brands:' . $year, '/InsuranceData/GetVehicleBrandList', ['VehicleYear' => $year]) as $row) {
            $name = ' ' . trim((string) preg_replace('/[^a-z0-9]+/', ' ', Text::fold((string) $row['Name']))) . ' ';
            foreach ($keys as $key) {
                if (str_contains($name, ' ' . $key . ' ')) {
                    $ids[] = (int) $row['VehicleBrandId'];
                    break;
                }
            }
        }
        return $ids;
    }

    /** @return list<array{id: int, name: string}> */
    public static function models(int $year, int $brandId): array
    {
        $rows = (array) self::cached('models:' . $year . ':' . $brandId, '/InsuranceData/GetVehicleModelList', [
            'vehicleYear' => $year,
            'VehicleBrandId' => $brandId,
        ]);
        return array_map(static fn (array $r): array => ['id' => (int) $r['VehicleModelId'], 'name' => (string) $r['Name']], $rows);
    }

    public static function value(int $year, int $modelId): ?int
    {
        $result = self::cached('value:' . $year . ':' . $modelId, '/InsuranceData/GetInsuranceDatas', [
            'VehicleYear' => $year,
            'VehicleModelId' => $modelId,
        ]);
        $amount = is_array($result) ? (int) ($result['Amount'] ?? 0) : 0;
        return $amount > 0 ? $amount : null;
    }

    /**
     * İlandaki model metnine en çok benzeyen TSB modellerini bulur ve kasko değerini döndürür.
     * Tek açık eşleşme yoksa en iyi adayların medyanı alınır.
     *
     * @return array{value: int, label: string, matches: int}|null
     */
    public static function estimate(string $brand, ?string $model, int $year): ?array
    {
        if ($model === null || $model === '' || !in_array($year, self::years(), true)) {
            return null;
        }
        $candidates = [];
        foreach (self::brandIds($year, $brand) as $brandId) {
            array_push($candidates, ...self::models($year, $brandId));
        }
        if ($candidates === []) {
            return null;
        }

        $wanted = Text::tokens($model);
        $family = $wanted[0] ?? '';
        $scored = [];
        foreach ($candidates as $candidate) {
            $tokens = Text::tokens($candidate['name']);
            if (($tokens[0] ?? '') !== $family) {
                continue;
            }
            $score = 1;
            foreach (array_slice($wanted, 1) as $token) {
                if (in_array($token, $tokens, true)) {
                    $score += preg_match('/^\d\.\d$/', $token) === 1 ? 3 : 2;
                }
            }
            // Fazladan donanım kelimesi olan adayı hafifçe geride bırak.
            $scored[] = ['score' => $score - 0.1 * max(0, count($tokens) - count($wanted)), 'model' => $candidate];
        }
        if ($scored === []) {
            return null;
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $best = $scored[0]['score'];
        $top = array_values(array_filter($scored, static fn (array $s): bool => $s['score'] >= $best - 0.5));
        // Çok sayıda eşit aday varsa (ör. sadece "Corolla" yazılmışsa) yayılmış 6 örnek yeter.
        if (count($top) > 6) {
            $step = count($top) / 6;
            $top = array_map(static fn (int $i): array => $top[(int) floor($i * $step)], range(0, 5));
        }

        $values = [];
        $labels = [];
        foreach ($top as $item) {
            $value = self::value($year, $item['model']['id']);
            if ($value !== null) {
                $values[] = $value;
                $labels[] = $item['model']['name'];
            }
        }
        if ($values === []) {
            return null;
        }
        sort($values);
        $median = $values[intdiv(count($values), 2)];
        if (count($values) % 2 === 0) {
            $median = intdiv($values[count($values) / 2 - 1] + $values[count($values) / 2], 2);
        }
        $label = count($labels) === 1 ? $labels[0] : $labels[0] . ' (+' . (count($labels) - 1) . ' benzer donanım)';
        return ['value' => $median, 'label' => $label, 'matches' => count($values)];
    }

    /**
     * @param array<string, int|string> $query
     */
    private static function cached(string $key, string $path, array $query = []): mixed
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT payload, fetched_at FROM tsb_cache WHERE cache_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row && strtotime((string) $row['fetched_at']) > time() - self::TTL) {
            return json_decode((string) $row['payload'], true);
        }

        $url = self::BASE . $path . ($query === [] ? '' : '?' . http_build_query($query));
        $payload = Http::json($url, ['Referer' => self::BASE . '/tr/kasko-deger-listesi'], 20);
        if (is_array($payload) && array_key_exists('HasError', $payload)) {
            if ($payload['HasError']) {
                throw new \RuntimeException('TSB hatası: ' . ($payload['Message'] ?? ''));
            }
            $payload = $payload['Result'];
        }

        $pdo->prepare('DELETE FROM tsb_cache WHERE cache_key = ?')->execute([$key]);
        $pdo->prepare('INSERT INTO tsb_cache (cache_key, payload, fetched_at) VALUES (?, ?, ?)')
            ->execute([$key, json_encode($payload, JSON_UNESCAPED_UNICODE), now()]);
        return $payload;
    }
}
