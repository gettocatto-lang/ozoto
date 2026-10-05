<?php

declare(strict_types=1);

namespace Ozoto\Radar;

/**
 * Parça parça boya/değişen, tramer ve ağır hasar kaydının piyasa değerine etkisi (hasarsız değerin oranı olarak).
 * Oranlar ikinci el piyasasında alıcıların uyguladığı ortalama iskontolara dayanan başlangıç değerleridir;
 * gerçek işlemlerle karşılaştırılarak ayarlanmalıdır.
 */
final class DamageModel
{
    /** Kapı, çamurluk, kaput, bagaj kapağı gibi dış panel başına. */
    private const PANEL = [DetailParser::LOCAL => 0.005, DetailParser::PAINTED => 0.015, DetailParser::REPLACED => 0.03];

    /** Tampon plastik ve sık değişir: etkisi panelin bir kısmı kadar. */
    private const BUMPER = [DetailParser::LOCAL => 0.002, DetailParser::PAINTED => 0.005, DetailParser::REPLACED => 0.01];

    /** Tavan işlemi takla/ciddi kaza belirtisidir. */
    private const ROOF = [DetailParser::LOCAL => 0.02, DetailParser::PAINTED => 0.05, DetailParser::REPLACED => 0.12];

    /** Taşıyıcı parça (şase, podye, direk, marşpiyel, panel, bagaj havuzu) işlem görmüşse. */
    private const STRUCTURAL = 0.08;

    /** Ağır hasar kayıtlı araçlarda en az iskonto. */
    private const HEAVY_MIN = 0.30;

    /** Airbag açılmış: ciddi çarpışma ve pahalı onarım. */
    private const AIRBAG = 0.05;

    /** @param array<string, mixed> $d DetailParser çıktısı */
    public static function hasInfo(array $d): bool
    {
        return ($d['parts'] ?? []) !== [] || ($d['counts'] ?? []) !== [] || ($d['tramer'] ?? null) !== null
            || ($d['heavy_damage'] ?? null) !== null || !empty($d['clean_claim']);
    }

    /**
     * @param array<string, mixed> $d DetailParser çıktısı
     * @return array{pct: float, lines: list<string>, summary: string, replaced: list<string>, painted: list<string>, local: list<string>,
     *               structural: list<string>, roof: ?string, tramer: ?int, heavy: bool, known: bool, coarse: ?string}
     */
    public static function assess(array $d, ?int $clean): array
    {
        $groups = [DetailParser::REPLACED => [], DetailParser::PAINTED => [], DetailParser::LOCAL => []];
        $structural = [];
        $roof = null;
        $partsPct = 0.0;
        $lines = [];

        foreach ((array) ($d['parts'] ?? []) as $part => $status) {
            if (!isset($groups[$status])) {
                continue;
            }
            $groups[$status][] = (string) $part;
            if (in_array($part, DetailParser::STRUCTURAL, true)) {
                $structural[] = $part;
                $partsPct += self::STRUCTURAL;
                continue;
            }
            if ($part === 'Tavan') {
                $roof = $status;
                $partsPct += self::ROOF[$status];
                continue;
            }
            $partsPct += str_contains((string) $part, 'Tampon') ? self::BUMPER[$status] : self::PANEL[$status];
        }
        foreach ($groups as $status => $parts) {
            if ($parts !== []) {
                $lines[] = sprintf('%s (%d): %s', mb_convert_case(DetailParser::STATUS_LABELS[$status], MB_CASE_TITLE, 'UTF-8'), count($parts), implode(', ', $parts));
            }
        }

        // Parça adları yoksa "3 boyalı, 1 değişen" sayıları dış panel kabul edilir.
        $counts = (array) ($d['counts'] ?? []);
        if (($d['parts'] ?? []) === [] && $counts !== []) {
            foreach ($counts as $status => $count) {
                if (isset(self::PANEL[$status])) {
                    $partsPct += self::PANEL[$status] * (int) $count;
                    $lines[] = sprintf('%d parça %s (hangi parçalar olduğu yazmıyor)', (int) $count, DetailParser::STATUS_LABELS[$status]);
                }
            }
        }

        // Açıklamada taşıyıcı hasar ifadesi geçip parça listesinde yoksa yine de hesaba kat.
        foreach ((array) ($d['flags'] ?? []) as $flag) {
            if ($structural === [] && str_contains((string) ($flag['text'] ?? ''), 'Şase/podye/direk')) {
                $structural[] = 'açıklamada belirtilen taşıyıcı aksam';
                $partsPct += self::STRUCTURAL;
            }
        }

        $tramer = isset($d['tramer']) && $d['tramer'] !== null ? (int) $d['tramer'] : null;
        $tramerPct = 0.0;
        if ($tramer !== null && $tramer > 0 && $clean !== null && $clean > 0) {
            $tramerPct = min(0.30, 0.6 * $tramer / $clean);
        }

        $pct = max($partsPct, $tramerPct) + 0.3 * min($partsPct, $tramerPct);
        $heavy = ($d['heavy_damage'] ?? null) === true;
        $airbag = false;
        foreach ((array) ($d['flags'] ?? []) as $flag) {
            $airbag = $airbag || str_starts_with((string) ($flag['text'] ?? ''), 'Airbag');
        }
        if ($heavy) {
            // Ağır hasar kaydı tabandır; kayıttaki hasarın büyüklüğü (tavan, değişenler) iskontoyu artırır.
            $pct = max($pct, self::HEAVY_MIN + 0.5 * $partsPct);
        }
        if ($airbag) {
            $pct += self::AIRBAG;
        }
        $pct = min(0.60, $pct);

        $impactLines = [];
        if ($partsPct > 0) {
            $impactLines[] = 'Boya/değişen etkisi: −' . self::pct($partsPct);
        }
        if ($tramerPct > 0) {
            $impactLines[] = 'Tramer ' . format_tl($tramer) . ' etkisi: −' . self::pct($tramerPct);
        }
        if ($heavy) {
            $impactLines[] = 'Ağır hasar kayıtlı: en az −' . self::pct(self::HEAVY_MIN);
        }
        if ($airbag) {
            $impactLines[] = 'Airbag açılmış: −' . self::pct(self::AIRBAG);
        }

        $summaryParts = [];
        if ($heavy) {
            $summaryParts[] = 'AĞIR HASAR KAYITLI';
        }
        if (($d['parts'] ?? []) !== [] && $groups[DetailParser::REPLACED] === [] && $groups[DetailParser::PAINTED] === [] && $groups[DetailParser::LOCAL] === []) {
            $summaryParts[] = 'listelenen tüm parçalar orijinal';
        }
        foreach ($groups as $status => $parts) {
            if ($parts !== []) {
                $summaryParts[] = count($parts) . ' ' . DetailParser::STATUS_LABELS[$status] . ' (' . implode(', ', $parts) . ')';
            }
        }
        if (($d['parts'] ?? []) === [] && $counts !== []) {
            foreach ($counts as $status => $count) {
                $summaryParts[] = $count . ' ' . (DetailParser::STATUS_LABELS[$status] ?? $status);
            }
        }
        if ($tramer !== null) {
            $summaryParts[] = $tramer > 0 ? 'tramer ' . format_tl($tramer) : 'tramer yok';
        }
        $known = self::hasInfo($d);
        if ($summaryParts === []) {
            $summary = !empty($d['clean_claim']) || ($d['parts'] ?? []) !== []
                ? 'hatasız/boyasız olduğu yazıyor (ekspertizle doğrula)'
                : 'boya/değişen bilgisi yok';
        } else {
            $summary = implode(', ', $summaryParts);
        }

        $coarse = null;
        if ($heavy) {
            $coarse = 'Ağır hasar kayıtlı';
        } elseif ($groups[DetailParser::REPLACED] !== [] && ($groups[DetailParser::PAINTED] !== [] || $groups[DetailParser::LOCAL] !== [])) {
            $coarse = 'Boyalı ve değişenli';
        } elseif ($groups[DetailParser::REPLACED] !== [] || ($counts[DetailParser::REPLACED] ?? 0) > 0) {
            $coarse = ($counts[DetailParser::PAINTED] ?? 0) > 0 ? 'Boyalı ve değişenli' : 'Değişenli';
        } elseif ($groups[DetailParser::PAINTED] !== [] || $groups[DetailParser::LOCAL] !== [] || ($counts[DetailParser::PAINTED] ?? 0) > 0) {
            $coarse = 'Boyalı';
        } elseif (!empty($d['clean_claim']) || $tramer === 0 || (($d['parts'] ?? []) !== [] && $structural === [])) {
            $coarse = 'Hatasız / boyasız';
        }

        return [
            'pct' => round($pct, 4),
            'lines' => [...$lines, ...$impactLines],
            'summary' => $summary,
            'replaced' => $groups[DetailParser::REPLACED],
            'painted' => $groups[DetailParser::PAINTED],
            'local' => $groups[DetailParser::LOCAL],
            'structural' => $structural,
            'roof' => $roof,
            'tramer' => $tramer,
            'heavy' => $heavy,
            'known' => $known,
            'coarse' => $coarse,
        ];
    }

    /** 0.056 → "%5,6" */
    public static function pct(float $fraction): string
    {
        $value = round($fraction * 100, 1);
        return '%' . rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}
