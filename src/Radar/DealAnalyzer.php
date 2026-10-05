<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Database;
use Ozoto\Settings;

/**
 * Alım kararı: ilan detayından okunan araç/hasar bilgileri, hasarsız piyasa değeri ve şirketin masraf/kâr ayarlarıyla
 * "AL / PAZARLIK / GEÇ" kararı, en fazla verilecek fiyat, riskler, ekspertiz listesi ve yorum üretir.
 * Çıktı, eklenti ve panelin aynı şekilde çizdiği bloklardan oluşur; kurallar sunucuda değişince eklentiyi güncellemek gerekmez.
 */
final class DealAnalyzer
{
    /** Panelden değiştirilebilen kârlılık ayarları ve varsayılanları. */
    public const SETTINGS = [
        'deal_cost_noter' => ['Noter ve satış işlemleri (TL)', 3000],
        'deal_cost_ekspertiz' => ['Ekspertiz (TL)', 3500],
        'deal_cost_prep' => ['Satışa hazırlık: temizlik, küçük bakım (TL)', 5000],
        'deal_cost_sale' => ['İlan ve satış masrafı (TL)', 2000],
        'deal_target_pct' => ['Hedef kâr (alış fiyatının %)', 8],
        'deal_target_min' => ['En az hedef kâr (TL)', 20000],
        'deal_negotiation_pct' => ['Beklenen pazarlık (%)', 5],
        'deal_sale_discount_pct' => ['Hızlı satış için piyasa altına satış (%)', 2],
    ];

    private const SCAM = ['Kapora', 'Satıcı uzakta', 'Yurt dışı', 'Haciz', 'Su basmış'];

    /** Ayar değişince mevcut analizleri yeniden hesaplar (en yeniden eskiye, süre dolana kadar). */
    public static function refreshAll(float $deadline): int
    {
        $done = 0;
        $ids = Database::connection()->query('SELECT listing_id FROM radar_details ORDER BY updated_at DESC LIMIT 500')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            if (microtime(true) > $deadline || Tsb::throttled()) {
                break;
            }
            $listing = Listings::find((int) $id);
            $data = Details::data((int) $id);
            if ($listing !== null && $data !== null) {
                Details::saveAnalysis((int) $id, self::analyze($listing, $data));
                $done++;
            }
        }
        return $done;
    }

    /** @return array<string, int> */
    public static function settings(): array
    {
        $values = [];
        foreach (self::SETTINGS as $key => [, $default]) {
            $raw = Settings::get($key);
            $values[$key] = $raw === null || $raw === '' ? $default : max(0, (int) $raw);
        }
        return $values;
    }

    /**
     * @param array<string, mixed> $l radar_listings satırı (değerlenmiş)
     * @param array<string, mixed> $d DetailParser çıktısı
     * @return array<string, mixed>
     */
    public static function analyze(array $l, array $d): array
    {
        $s = self::settings();
        $appraisal = Valuator::appraise($l);
        $clean = $appraisal['clean'];
        $damage = DamageModel::assess($d, $clean);
        $price = $l['price'] !== null ? (int) $l['price'] : (isset($d['price']) ? (int) $d['price'] : null);
        $year = $l['model_year'] !== null ? (int) $l['model_year'] : ($d['year'] ?? null);
        $km = $l['km'] !== null ? (int) $l['km'] : ($d['km'] ?? null);
        $flags = (array) ($d['flags'] ?? []);
        $red = array_values(array_filter($flags, static fn (array $f): bool => $f['level'] === 'red'));
        $amber = array_values(array_filter($flags, static fn (array $f): bool => $f['level'] === 'amber'));
        $green = array_values(array_filter($flags, static fn (array $f): bool => $f['level'] === 'green'));
        $info = array_values(array_filter($flags, static fn (array $f): bool => $f['level'] === 'info'));
        $has = static fn (array $list, string $needle): bool => array_filter($list, static fn (array $f): bool => str_contains($f['text'], $needle)) !== [];
        $scam = array_values(array_filter($red, static function (array $f): bool {
            foreach (self::SCAM as $word) {
                if (str_contains($f['text'], $word)) {
                    return true;
                }
            }
            return false;
        }));

        // Km değerlendirmesi.
        $kmNotes = [];
        $perYear = null;
        if ($km !== null && $year !== null) {
            $age = max(0.5, (int) date('Y') - (int) $year + 0.5);
            $perYear = (int) round($km / $age);
            if ($age >= 3 && $perYear < 6000) {
                $kmNotes[] = ['level' => 'amber', 'text' => sprintf('Km yaşına göre çok düşük (yılda ≈ %s km): km düşürülmüş olabilir, muayene ve servis kayıtlarındaki km\'lerle karşılaştır', format_number($perYear))];
            } elseif ($perYear > 35000) {
                $kmNotes[] = ['level' => 'amber', 'text' => sprintf('Yüksek kullanım (yılda ≈ %s km): taksi/filo geçmişini ve ağır bakım kalemlerini sor', format_number($perYear))];
            }
            if ($km >= 250000) {
                $kmNotes[] = ['level' => 'amber', 'text' => '250 bin km üstü: motor, şanzıman ve turbo masrafı riski yüksek'];
            }
        }

        // Ekonomi.
        $costs = $s['deal_cost_noter'] + $s['deal_cost_ekspertiz'] + $s['deal_cost_prep'] + $s['deal_cost_sale'];
        $negotiation = $s['deal_negotiation_pct'];
        if ((int) ($l['urgent'] ?? 0) === 1 || $has($green, 'Acil')) {
            $negotiation += 3;
        }
        if (($d['seller_type'] ?? null) === 'galeriden') {
            $negotiation -= 2;
        }
        if ($has($info, 'pazarlığa kapalı')) {
            $negotiation -= 3;
        }
        $negotiation = max(0, min(15, $negotiation));

        $fair = $clean !== null ? (int) round($clean * (1 - $damage['pct'])) : null;
        $resale = $fair !== null ? (int) round($fair * (1 - $s['deal_sale_discount_pct'] / 100)) : null;
        $buy = $price !== null ? (int) round($price * (1 - $negotiation / 100)) : null;
        $profitAsk = $resale !== null && $price !== null ? $resale - $price - $costs : null;
        $profitBuy = $resale !== null && $buy !== null ? $resale - $buy - $costs : null;
        $target = $buy !== null ? max($s['deal_target_min'], (int) round($buy * $s['deal_target_pct'] / 100)) : $s['deal_target_min'];
        $maxOffer = $resale !== null ? (int) (floor(($resale - $costs - $target) / 5000) * 5000) : null;
        $marginBuy = $profitBuy !== null && $buy ? round($profitBuy / $buy * 100, 1) : null;

        $confidence = 'düşük';
        if ($appraisal['tsb'] !== null && $appraisal['comps'] >= 5) {
            $confidence = 'yüksek';
        } elseif ($appraisal['tsb'] !== null || $appraisal['comps'] >= 3) {
            $confidence = 'orta';
        }
        if (!$damage['known'] && $confidence === 'yüksek') {
            $confidence = 'orta';
        }

        // Karar. Dolandırıcılık kalıbı her şeyden önce gelir.
        if ($scam !== []) {
            $verdict = 'gec';
            $headline = 'Risk: ' . $scam[0]['text'] . '.';
        } elseif ($price === null || $clean === null) {
            $verdict = 'eksik';
            $headline = $price === null ? 'Fiyat okunamadı; analiz için fiyat gerekli.' : 'Piyasa değeri hesaplanamadı (model TSB listesinde yok ve yeterli benzer ilan yok).';
        } elseif ($profitAsk >= $target) {
            $verdict = 'al';
            $headline = sprintf('İlan fiyatından bile kârlı: ≈ %s kâr. Hemen ara.', self::tl($profitAsk));
        } elseif ($maxOffer !== null && $maxOffer > 0 && $maxOffer >= $price * 0.85) {
            $verdict = 'pazarlik';
            $headline = sprintf('Pazarlıkla alınır: en fazla %s ver (ilan %s, %s indirim).', self::tl($maxOffer), self::tl($price), DamageModel::pct(max(0, ($price - $maxOffer) / $price)));
        } else {
            $verdict = 'gec';
            $headline = $maxOffer !== null && $maxOffer > 0
                ? sprintf('Kâr için fiyatın %s\'ye inmesi gerekir (ilan %s).', self::tl($maxOffer), self::tl($price))
                : 'Bu fiyattan kâr çıkmıyor.';
        }
        $riskNote = '';
        $risky = $verdict !== 'gec' && $verdict !== 'eksik' && ($red !== [] || $damage['structural'] !== [] || $damage['heavy'] || $damage['roof'] !== null);
        if ($risky) {
            $riskNote = ' Riskli: taşıyıcı aksam ekspertizi temiz çıkmadan kapora verme.';
        } elseif (($verdict === 'al' || $verdict === 'pazarlik') && !$damage['known']) {
            $riskNote = ' Boya/değişen bilgisi yok; hesap hasarsız kabulüyle.';
        }
        $headline .= $riskNote;

        // Bloklar.
        $blocks = [];
        $numbers = [
            ['İlan fiyatı', self::tl($price)],
            ['Hasarsız piyasa değeri', $clean !== null ? '≈ ' . self::tl($clean) : 'hesaplanamadı'],
        ];
        if ($fair !== null) {
            $numbers[] = ['Hasara göre değeri', '≈ ' . self::tl($fair) . ($damage['pct'] > 0 ? ' (−' . DamageModel::pct($damage['pct']) . ')' : '')];
            $numbers[] = ['Hızlı satış fiyatı', '≈ ' . self::tl($resale)];
            $numbers[] = ['Masraflar', '≈ ' . self::tl($costs)];
            $numbers[] = ['İlan fiyatından kâr', self::signed($profitAsk)];
            $numbers[] = [sprintf('%%%d pazarlıkla (%s) kâr', $negotiation, self::tl($buy)), self::signed($profitBuy) . ($marginBuy !== null ? ' (%' . number_format($marginBuy, 1, ',', '.') . ')' : '')];
            $numbers[] = ['En fazla teklif (hedef kâr ' . self::tl($target) . ')', $maxOffer !== null && $maxOffer > 0 ? self::tl($maxOffer) : '—'];
        }
        $numbers[] = ['Güven', $confidence . ' (' . ($appraisal['tsb'] !== null ? 'TSB' : 'TSB yok') . ', ' . $appraisal['comps'] . ' benzer ilan)'];
        $blocks[] = ['type' => 'kv', 'title' => 'Rakamlar', 'rows' => $numbers];

        $damageItems = [];
        foreach ($damage['lines'] as $line) {
            $damageItems[] = ['level' => str_contains($line, 'Değişen') || str_contains($line, 'Tramer') ? 'amber' : 'info', 'text' => $line];
        }
        if ($damage['heavy']) {
            array_unshift($damageItems, ['level' => 'red', 'text' => 'Ağır hasar kayıtlı: alıcı kitlesi dar, satış süresi uzun; kasko ve kredi zorlaşır']);
        }
        if ($damage['roof'] !== null) {
            $damageItems[] = ['level' => 'red', 'text' => 'Tavan ' . DetailParser::STATUS_LABELS[$damage['roof']] . ': takla/ağır kaza belirtisi olabilir'];
        }
        if ($damage['structural'] !== []) {
            $damageItems[] = ['level' => 'red', 'text' => 'Taşıyıcı aksam: ' . implode(', ', $damage['structural'])];
        }
        if (!$damage['known']) {
            $damageItems[] = ['level' => 'amber', 'text' => 'İlanda boya/değişen ve tramer bilgisi yok: satıcıdan parça parça iste'];
        } elseif (!empty($d['clean_claim']) && $damage['pct'] === 0.0) {
            $damageItems[] = ['level' => 'info', 'text' => 'Satıcı hatasız/boyasız diyor: ekspertizde boya kalınlığıyla doğrula'];
        }
        if (($d['tramer_source'] ?? null) === 'aciklama') {
            $damageItems[] = ['level' => 'info', 'text' => 'Tramer tutarı açıklamadan okundu; SMS sorgusuyla doğrula'];
        }
        if (($d['parts_source'] ?? null) === 'aciklama') {
            $damageItems[] = ['level' => 'info', 'text' => 'Parça bilgileri açıklamadan okundu; ilan ekranındaki boya-değişen şemasıyla karşılaştır'];
        }
        $blocks[] = ['type' => 'list', 'title' => 'Hasar: ' . $damage['summary'], 'items' => $damageItems];

        $warnings = [...$red, ...$amber, ...$kmNotes];
        if ($warnings !== []) {
            $blocks[] = ['type' => 'list', 'title' => 'Dikkat', 'items' => $warnings];
        }
        if ($green !== [] || $info !== []) {
            $blocks[] = ['type' => 'list', 'title' => 'Artılar ve notlar', 'items' => [...$green, ...$info]];
        }

        $checks = [
            'Tramer sorgusu: 5664\'e "TRAMER plaka" yazıp SMS at (ücretli); ilandaki tutarla karşılaştır.',
            'Ekspertiz: parça parça boya kalınlığı ölçümü, kaporta ve şase kontrolü, motor/şanzıman testi.',
        ];
        if ($damage['replaced'] !== []) {
            $checks[] = 'Değişen parçalar (' . implode(', ', $damage['replaced']) . ') orijinal mi yan sanayi mi? Faturası var mı?';
        }
        if ($damage['structural'] !== [] || $damage['roof'] !== null || $damage['heavy'] || $has($red, 'Şase')) {
            $checks[] = 'Taşıyıcı aksam: şase ölçümü, podye/direk kaynak izi ve hava yastığı kontrolü şart.';
        }
        if ($has($red, 'Airbag')) {
            $checks[] = 'Airbag: uyarı lambası, torpido ve direksiyon değişim izleri, kemer gergileri.';
        }
        if ($has($red, 'Motor') || $has($amber, 'Motor') || ($km !== null && $km >= 200000)) {
            $checks[] = 'Motor: kompresyon testi, yağ eksiltme, soğuk motorla ilk çalıştırma ve egzoz dumanı.';
        }
        if ($has($red, 'Şanzıman')) {
            $checks[] = 'Şanzıman: yol testinde vites geçişleri, kayma ve vuruntu; otomatikse şanzıman bakım geçmişi.';
        }
        if ($kmNotes !== []) {
            $checks[] = 'Km geçmişi: servis kayıtları ve muayene raporlarındaki km\'leri iste.';
        }
        if (($d['seller_type'] ?? null) === 'galeriden') {
            $checks[] = 'Galeri: faturalı satış mı, garanti veriyor mu, aracın galeriye nasıl geldiğini sor.';
        }
        if (($d['swap'] ?? null) === true || $has($info, 'Takas')) {
            $checks[] = 'Takasa açık: elindeki araçlardan biriyle takas teklifi düşünülebilir.';
        }
        if ($negotiation >= 8) {
            $checks[] = sprintf('Acil satış: nakit ve hemen alım teklifiyle %%%d–%d pazarlık dene.', $negotiation, $negotiation + 3);
        }
        $checks[] = 'Satıştan önce noterde haciz/rehin sorgusu yapılır; aracı görmeden kapora gönderme.';
        $blocks[] = ['type' => 'list', 'title' => 'Ekspertiz ve satıcıya sorulacaklar', 'items' => array_map(static fn (string $t): array => ['level' => 'info', 'text' => $t], $checks)];

        $blocks[] = ['type' => 'text', 'title' => 'Yorum', 'paragraphs' => self::comment($l, $d, compact(
            'price', 'clean', 'fair', 'resale', 'costs', 'buy', 'profitAsk', 'profitBuy', 'maxOffer', 'target', 'negotiation', 'perYear', 'verdict', 'confidence'
        ) + ['risky' => $risky, 'scam' => $scam !== [], 'damage' => $damage, 'appraisal' => $appraisal, 'red' => $red, 'amber' => [...$amber, ...$kmNotes], 'green' => $green, 'km' => $km, 'year' => $year])];

        $vehicle = array_values(array_filter([
            ['Araç', trim(implode(' ', array_filter([$l['brand'] ?? $d['brand'] ?? null, $l['model'] ?? null])))],
            ['Yıl', $year !== null ? (string) $year : null],
            ['Km', $km !== null ? format_number($km) . ($perYear !== null ? ' (yılda ≈ ' . format_number($perYear) . ')' : '') : null],
            ['Yakıt / vites', trim(implode(' / ', array_filter([$d['fuel'] ?? $l['fuel'] ?? null, $d['gearbox'] ?? $l['gearbox'] ?? null])))],
            ['Kasa / renk', trim(implode(' / ', array_filter([$d['body'] ?? null, $d['color'] ?? null])))],
            ['Motor', trim(implode(' / ', array_filter([isset($d['engine_hp']) && $d['engine_hp'] ? $d['engine_hp'] . ' hp' : null, isset($d['engine_cc']) && $d['engine_cc'] ? $d['engine_cc'] . ' cc' : null])))],
            ['Kimden', $d['seller_type'] ?? null],
            ['Şehir', $l['city'] ?? null],
        ], static fn (array $row): bool => $row[1] !== null && $row[1] !== ''));
        $blocks[] = ['type' => 'kv', 'title' => 'Araç', 'rows' => $vehicle];

        return [
            'version' => 1,
            'at' => now(),
            'verdict' => $verdict,
            'verdict_label' => ['al' => 'AL', 'pazarlik' => 'PAZARLIK', 'gec' => 'GEÇ', 'eksik' => 'BİLGİ EKSİK'][$verdict] . ($risky ? ' · RİSKLİ' : ''),
            'risky' => $risky,
            'headline' => $headline,
            'score' => $l['score'] !== null ? (int) $l['score'] : null,
            'numbers' => compact('price', 'clean', 'fair', 'resale', 'costs', 'buy', 'profitAsk', 'profitBuy', 'maxOffer', 'target', 'negotiation') + ['damage_pct' => $damage['pct']],
            'damage_summary' => $damage['summary'],
            'confidence' => $confidence,
            'blocks' => $blocks,
        ];
    }

    /**
     * @param array<string, mixed> $l
     * @param array<string, mixed> $d
     * @param array<string, mixed> $x
     * @return list<string>
     */
    private static function comment(array $l, array $d, array $x): array
    {
        $p = [];
        $name = trim(((string) ($l['brand'] ?? '')) . ' ' . ((string) ($l['model'] ?? ''))) ?: (string) ($d['title'] ?? 'Araç');
        $intro = $name . ($x['year'] ? ', ' . $x['year'] : '') . ($x['km'] !== null ? ', ' . format_number((int) $x['km']) . ' km' : '') . '.';
        if ($x['price'] !== null) {
            $intro .= ' İlan fiyatı ' . self::tl($x['price']) . '.';
        }
        if ($x['clean'] !== null) {
            $basis = [];
            if ($x['appraisal']['tsb'] !== null) {
                $basis[] = 'TSB kasko değeri ' . self::tl((int) $x['appraisal']['tsb']['value']);
            }
            if ($x['appraisal']['comps'] >= 5) {
                $basis[] = $x['appraisal']['comps'] . ' benzer ilanın medyanı ' . self::tl((int) $x['appraisal']['median']);
            }
            $intro .= ' Hasarsız haliyle piyasa değeri ≈ ' . self::tl($x['clean']) . ($basis !== [] ? ' (' . implode(', ', $basis) . '; km\'ye göre düzeltilmiş)' : '') . '.';
        }
        $p[] = $intro;

        $damage = $x['damage'];
        if ($damage['heavy']) {
            $p[] = 'Ağır hasar kayıtlı. Piyasa bu araçlara en az %30 iskonto uygular, kredi ve kasko zorlaşır, satışı uzun sürer. '
                . 'Ucuza alınıp hasarı iyi onarılmışsa kâr bırakabilir ama mutlaka taşıyıcı aksam ekspertizi yaptır.';
        } elseif ($damage['known'] && $damage['pct'] > 0) {
            $p[] = 'Hasar durumu: ' . $damage['summary'] . '. Bu profil piyasada yaklaşık −' . DamageModel::pct($damage['pct'])
                . ' değer kaybı demek; aracın hasarına göre değeri ≈ ' . self::tl($x['fair']) . '.'
                . ($damage['replaced'] !== [] ? ' Değişen parça alıcıyı en çok soğutan kalemdir; değişenlerin orijinal olup olmadığı fiyatı ciddi etkiler.' : '')
                . ($damage['replaced'] === [] && $damage['painted'] !== [] ? ' Sadece boya olması iyi; değişen yok.' : '');
        } elseif ($damage['known']) {
            $p[] = 'Hasar: ' . $damage['summary'] . '. Değer kaybı hesaplanmadı; ekspertizde doğrulanırsa fiyat avantajı korunur.';
        } else {
            $p[] = 'İlanda boya/değişen ve tramer bilgisi yok; hesap aracın hasarsız olduğu varsayımıyla yapıldı. Satıcıdan parça parça bilgi ve tramer tutarını iste; çıkan hasar kadar teklifini düşür.';
        }

        if ($x['resale'] !== null && $x['price'] !== null) {
            $p[] = sprintf(
                'İlan fiyatından alırsan, ≈ %s masrafla ve hızlı satış fiyatı ≈ %s üzerinden elinde %s kalır. %%%d pazarlıkla (%s) bu %s olur. Hedef kârın (%s) için en fazla %s teklif et.',
                self::tl($x['costs']),
                self::tl($x['resale']),
                self::signed($x['profitAsk']),
                $x['negotiation'],
                self::tl($x['buy']),
                self::signed($x['profitBuy']),
                self::tl($x['target']),
                $x['maxOffer'] !== null && $x['maxOffer'] > 0 ? self::tl($x['maxOffer']) : '—',
            );
        }

        $risks = array_map(static fn (array $f): string => $f['text'], [...$x['red'], ...$x['amber']]);
        if ($risks !== []) {
            $p[] = 'Dikkat edilecekler: ' . implode('; ', array_slice($risks, 0, 6)) . '.';
        }
        $pluses = array_map(static fn (array $f): string => $f['text'], $x['green']);
        if ($pluses !== []) {
            $p[] = 'Artılar: ' . implode('; ', array_slice($pluses, 0, 5)) . '.';
        }

        $p[] = match ($x['verdict']) {
            'al' => $x['risky']
                ? 'Sonuç: AL ama RİSKLİ. Rakamlar kârlı; fakat yukarıdaki ağır riskler yüzünden ekspertiz (özellikle taşıyıcı aksam) temiz çıkmadan para verme.'
                : 'Sonuç: AL. Fiyat zaten kârlı bölgede; ekspertiz temiz çıkarsa vakit kaybetmeden ara.',
            'pazarlik' => 'Sonuç: PAZARLIK' . ($x['risky'] ? ' (RİSKLİ)' : '') . '. ' . self::tl($x['maxOffer']) . ' üstüne çıkma; ekspertizde yeni hasar çıkarsa teklifini düşür.',
            'gec' => $x['scam'] ? 'Sonuç: GEÇ. İlanda dolandırıcılık kalıbı var; aracı yerinde görmeden hiçbir ödeme yapma.' : 'Sonuç: GEÇ. Bu fiyattan hedef kâr çıkmıyor.',
            default => 'Sonuç: bilgi eksik; fiyat ve model bilgisi tamamlanınca yeniden değerlenir.',
        } . ' (Güven: ' . $x['confidence'] . '.)';

        return $p;
    }

    private static function tl(?int $amount): string
    {
        return $amount === null ? '—' : format_tl((int) (round($amount / 1000) * 1000));
    }

    private static function signed(?int $amount): string
    {
        if ($amount === null) {
            return '—';
        }
        return ($amount < 0 ? '−' : '+') . self::tl(abs($amount));
    }
}
