<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Database;

/**
 * Piyasa değeri ve kelepir puanı.
 * Taban: TSB kasko değeri + km/hasar düzeltmesi. Yeterli benzer ilan varsa onların medyanıyla harmanlanır.
 */
final class Valuator
{
    public const KM_PER_YEAR = 17_000;

    private const DAMAGE_FACTORS = [
        'Hatasız / boyasız' => 1.0,
        'Boyalı' => 0.96,
        'Değişenli' => 0.92,
        'Boyalı ve değişenli' => 0.90,
        'Ağır hasar kayıtlı' => 0.70,
    ];

    /**
     * Hasarsız piyasa değeri: TSB kasko + km düzeltmesi, yeterli benzer ilan varsa onların medyanıyla harmanlanır.
     *
     * @param array<string, mixed> $l radar_listings satırı
     * @return array{clean: ?int, tsb: ?array{value: int, label: string}, comps: int, median: ?int, notes: list<string>, retry: bool}
     */
    public static function appraise(array $l): array
    {
        $notes = [];
        $retry = false;
        $year = $l['model_year'] !== null ? (int) $l['model_year'] : null;
        $km = $l['km'] !== null ? (int) $l['km'] : null;

        $tsb = null;
        if ($l['brand'] && $l['model'] && $year !== null) {
            try {
                $tsb = Tsb::estimate((string) $l['brand'], (string) $l['model'], $year);
                if ($tsb === null) {
                    $notes[] = 'TSB listesinde eşleşen model bulunamadı (TSB 15 yaşa kadar araçları kapsar).';
                }
            } catch (\Throwable $e) {
                error_log('TSB değerlemesi başarısız: ' . $e->getMessage());
                $notes[] = 'TSB\'ye şu an ulaşılamadı; değerleme sonra tekrar denenecek.';
                $retry = true;
            }
        } else {
            $notes[] = 'Marka, model veya yıl eksik; kayda bu bilgiler eklenince değerleme yapılır.';
        }

        $base = null;
        if ($tsb !== null) {
            $base = (float) $tsb['value'];
            $notes[] = 'TSB kasko değeri: ' . format_tl($tsb['value']) . ' — ' . $tsb['label'];
            if ($km !== null && $year !== null) {
                $age = max(0.5, (int) date('Y') - $year + 0.5);
                $diff = ($km - $age * self::KM_PER_YEAR) / 10_000;
                $factor = $diff > 0 ? max(0.80, 1 - 0.025 * $diff) : min(1.08, 1 - 0.015 * $diff);
                $base *= $factor;
                if (abs($factor - 1) >= 0.005) {
                    $notes[] = sprintf('Km düzeltmesi: %+d%% (yaşına göre beklenen ≈ %s km)', (int) round(($factor - 1) * 100), format_number((int) round($age * self::KM_PER_YEAR)));
                }
            }
        }

        $comps = self::comparables($l);
        $clean = $base !== null ? (int) round($base) : null;
        $median = null;
        if (count($comps) >= 5) {
            $median = self::median($comps);
            $notes[] = sprintf('Benzer %d ilanın medyan fiyatı: %s', count($comps), format_tl($median));
            $clean = $clean !== null ? (int) round(0.5 * $clean + 0.5 * $median) : $median;
        } elseif (count($comps) >= 3 && $clean === null) {
            $median = self::median($comps);
            $clean = $median;
            $notes[] = sprintf('Benzer %d ilanın medyan fiyatı: %s (TSB değeri yok, güven düşük)', count($comps), format_tl($median));
        }

        return ['clean' => $clean, 'tsb' => $tsb, 'comps' => count($comps), 'median' => $median, 'notes' => $notes, 'retry' => $retry];
    }

    /**
     * @param array<string, mixed> $l radar_listings satırı
     * @return array{tsb_value: ?int, tsb_label: ?string, market_value: ?int, discount_pct: ?float, score: ?int,
     *               suspicious: int, score_notes: string, needs_valuation: int, valued_at: string}
     */
    public static function valuate(array $l): array
    {
        $price = $l['price'] !== null ? (int) $l['price'] : null;
        $auction = (int) $l['is_auction'] === 1;
        $appraisal = self::appraise($l);
        $notes = $appraisal['notes'];
        $retry = $appraisal['retry'];
        $tsb = $appraisal['tsb'];
        $market = $appraisal['clean'];

        // Hasar: ilan detay sayfası okunduysa parça parça hesap, yoksa ilandaki genel hasar bilgisi.
        $details = isset($l['id']) ? Details::data((int) $l['id']) : null;
        if ($market !== null && $details !== null && DamageModel::hasInfo($details)) {
            $damage = DamageModel::assess($details, $market);
            if ($damage['pct'] > 0) {
                $market = (int) round($market * (1 - $damage['pct']));
                $notes[] = 'Hasar düzeltmesi (ilan detayından): −' . DamageModel::pct($damage['pct']) . ' — ' . $damage['summary'];
            } else {
                $notes[] = 'Hasar: ' . $damage['summary'];
            }
        } elseif ($market !== null) {
            $damage = (string) ($l['damage'] ?? '');
            if (isset(self::DAMAGE_FACTORS[$damage]) && self::DAMAGE_FACTORS[$damage] < 1) {
                $market = (int) round($market * self::DAMAGE_FACTORS[$damage]);
                $notes[] = sprintf('Hasar düzeltmesi (%s): %d%%', $damage, (int) round((self::DAMAGE_FACTORS[$damage] - 1) * 100));
            }
        }

        $discount = null;
        $score = null;
        $suspicious = 0;
        if ($market !== null && $market > 0 && $price !== null) {
            $discount = round(($market - $price) / $market * 100, 2);
            $points = max(-20.0, min(70.0, $discount * 2.5));
            if ((int) $l['urgent'] === 1) {
                $points += 10;
                $notes[] = 'Acil satış ifadesi var: +10';
            }
            if ($tsb !== null) {
                $points += 5;
            }
            if ($appraisal['comps'] >= 5) {
                $points += 5;
            }
            if (self::priceDropped((int) $l['id'])) {
                $points += 5;
                $notes[] = 'Fiyatı düştü: +5';
            }
            if ($auction) {
                $notes[] = 'İhale başlangıç bedeli üzerinden hesaplandı; son fiyat artırmayla yükselir.';
            } elseif ($discount > 40) {
                $suspicious = 1;
                $points = min($points, 55);
                $notes[] = 'Fiyat piyasanın %40\'tan fazla altında: sahte ilan olabilir, dikkatli olun.';
            }
            $score = (int) max(0, min(100, round($points)));
        } elseif ($price === null) {
            $notes[] = 'Fiyat bilinmiyor; puan için fiyat girilmeli.';
        }

        return [
            'tsb_value' => $tsb['value'] ?? null,
            'tsb_label' => isset($tsb['label']) ? mb_substr($tsb['label'], 0, 160) : null,
            'market_value' => $market,
            'discount_pct' => $discount,
            'score' => $score,
            'suspicious' => $suspicious,
            'score_notes' => json_encode($notes, JSON_UNESCAPED_UNICODE),
            'needs_valuation' => $retry ? 1 : 0,
            'valued_at' => now(),
        ];
    }

    /**
     * @param array<string, mixed> $l
     * @return list<int>
     */
    private static function comparables(array $l): array
    {
        if (!$l['brand'] || !$l['model_key'] || $l['model_year'] === null) {
            return [];
        }
        $sql = 'SELECT price FROM radar_listings
                WHERE id <> ? AND brand = ? AND model_key = ? AND model_year BETWEEN ? AND ?
                  AND price IS NOT NULL AND is_auction = 0 AND suspicious = 0 AND last_seen_at >= ?';
        $params = [(int) $l['id'], $l['brand'], $l['model_key'], (int) $l['model_year'] - 1, (int) $l['model_year'] + 1, date('Y-m-d H:i:s', time() - 90 * 86400)];
        if ($l['km'] !== null) {
            $sql .= ' AND (km IS NULL OR km BETWEEN ? AND ?)';
            array_push($params, (int) ((int) $l['km'] * 0.6), (int) ((int) $l['km'] * 1.5) + 20_000);
        }
        $stmt = Database::connection()->prepare($sql . ' LIMIT 300');
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private static function priceDropped(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT price FROM radar_price_history WHERE listing_id = ? ORDER BY seen_at DESC, id DESC LIMIT 2');
        $stmt->execute([$id]);
        $prices = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        return count($prices) === 2 && $prices[0] < $prices[1];
    }

    /** @param list<int> $values */
    private static function median(array $values): int
    {
        sort($values);
        $n = count($values);
        return $n % 2 === 1 ? $values[intdiv($n, 2)] : intdiv($values[$n / 2 - 1] + $values[$n / 2], 2);
    }
}
