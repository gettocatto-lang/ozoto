<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Support\Text;

/**
 * İlan detay sayfasından (eklentinin gönderdiği etiket/değer çiftleri, "boya-değişen" bölümleri, açıklama ve sayfa metni)
 * araç bilgilerini, parça parça boya/değişen durumunu, tramer tutarını ve açıklamadaki risk/fırsat ifadelerini çıkarır.
 * Site şablonuna bağlı değildir; sahibinden, arabam, letgo ve Facebook sayfalarının ortak kalıplarıyla çalışır.
 */
final class DetailParser
{
    public const ORIGINAL = 'orijinal';
    public const LOCAL = 'lokal';
    public const PAINTED = 'boyali';
    public const REPLACED = 'degisen';

    public const STATUS_LABELS = [
        self::ORIGINAL => 'orijinal',
        self::LOCAL => 'lokal boyalı',
        self::PAINTED => 'boyalı',
        self::REPLACED => 'değişen',
    ];

    /** Kaporta parçaları: ad => katlanmış (fold) eş anlamlılar. Uzun eşleşmeler önce denenir. */
    public const PARTS = [
        'Ön Tampon' => ['on tampon'],
        'Arka Tampon' => ['arka tampon'],
        'Motor Kaputu' => ['motor kaputu', 'motor kaput', 'kaput', 'kaputu'],
        'Bagaj Havuzu' => ['bagaj havuzu'],
        'Bagaj Kapağı' => ['bagaj kapagi', 'bagaj kapak', 'arka bagaj', 'bagaj'],
        'Tavan' => ['tavan'],
        'Sol Ön Çamurluk' => ['sol on camurluk'],
        'Sağ Ön Çamurluk' => ['sag on camurluk'],
        'Sol Arka Çamurluk' => ['sol arka camurluk'],
        'Sağ Arka Çamurluk' => ['sag arka camurluk'],
        'Sol Ön Kapı' => ['sol on kapi'],
        'Sağ Ön Kapı' => ['sag on kapi'],
        'Sol Arka Kapı' => ['sol arka kapi'],
        'Sağ Arka Kapı' => ['sag arka kapi'],
        'Sol Marşpiyel' => ['sol marspiyel'],
        'Sağ Marşpiyel' => ['sag marspiyel'],
        'Ön Panel' => ['on panel'],
        'Arka Panel' => ['arka panel'],
        'Podye' => ['podye'],
        'Şase' => ['sase', 'sasi'],
        'Direk' => ['a direk', 'b direk', 'c direk', 'direk', 'direkler'],
    ];

    /** Parça adından sonra gelince kaporta parçası olmadığını gösteren kelimeler. */
    private const NOT_BODY = '/^\s*(dosem\w*|isig\w*|lamba\w*|kol\w*|cam\w*|fitil\w*|kilit\w*|kilid\w*|sensor\w*|izgara\w*|ayna\w*|amortisor\w*|bagaj\w*|perde\w*|kaplama\w*|braket\w*|menteşe\w*|mentese\w*)/';

    /** Taşıyıcı (yapısal) parçalar: işlem görmüşse ağır hasar belirtisi. */
    public const STRUCTURAL = ['Sol Marşpiyel', 'Sağ Marşpiyel', 'Ön Panel', 'Arka Panel', 'Podye', 'Şase', 'Direk', 'Bagaj Havuzu'];

    /**
     * Açıklamadaki ifadeler: [düzenli ifade (katlanmış metinde), seviye, mesaj, olumsuzlukla iptal edilir mi].
     * Seviye: red (ciddi risk), amber (kontrol et), green (olumlu), info.
     */
    private const FLAG_RULES = [
        ['/\b(pert|cekme belge\w*|hurda belge\w*|agir hasarli)\b/', 'red', 'Ağır hasar / çekme belgeli ifadesi geçiyor', true],
        ['/\bairbag\w*\b.{0,25}\b(acti|acmis|acik|patla\w*|degis\w*)\b|\b(acmis|acilmis|patlamis)\b.{0,15}\bairbag/', 'red', 'Airbag açılmış/değişmiş olabilir', true],
        ['/\b(sase\w*|sasi\w*|podye\w*|direk(?!si)\w*)\b.{0,30}\b(islem\w*|kaynak\w*|hasar(?!siz)\w*|duzelt\w*|cekil\w*|degis\w*|boya\w*)\b/', 'red', 'Şase/podye/direk işlemi geçiyor (taşıyıcı hasar)', true],
        ['/\btavan\b(?! doseme).{0,20}\bdegis\w*/', 'red', 'Tavan değişmiş (ciddi kaza belirtisi)', true],
        ['/\b(kapora|kaparo|on odeme|onden odeme)\b/', 'red', 'Kapora/ön ödeme isteniyor: dolandırıcılık riski, aracı görmeden para gönderme', true],
        ['/\b(yurt ?disindayim|yurtdisindayim|sehir disindayim|askerdeyim|kargo ile (gonder|teslim)\w*|nakliye(ci)? ile (gonder|teslim)\w*|ambar ile\w*|kargoya veri\w*)\b/', 'red', 'Satıcı uzakta / kargoyla teslim diyor: klasik dolandırıcılık kalıbı', true],
        ['/\b(yurt ?disi(ndan)?|yurtdisi(ndan)?|kktc|plakasiz|gumrukten|mavi plaka)\b/', 'red', 'Yurt dışı/plakasız/gümrük aracı', true],
        ['/\b(hacizli|haciz|rehinli|rehin|bankaya borclu|yediemin)\b/', 'red', 'Haciz/rehin ifadesi var', true],
        ['/\b(su basmis|sel(den|e)? (kalmis|gelmis)|suya girmis)\b/', 'red', 'Su basmış/sel aracı olabilir', true],
        ['/\bmotor\w*\b.{0,30}\b(ariza(?!siz)\w*|yag yak\w*|yag eksilt\w*|su eksilt\w*|conta\w*|kapak conta\w*|silindir\w*|krank\w*|rot kol\w*|vuruntu\w*|tekl\w*|sorun(?!suz)\w*|duman\w*)\b/', 'red', 'Motor arızası belirtisi', true],
        ['/\b(sanziman|vites kutusu|dsg|cvt|otomatik vites)\b.{0,30}\b(ariza(?!siz)\w*|vuruntu\w*|kayma\w*|kayiyor|sorun(?!suz)\w*|tamir\w*|sarsinti\w*)\b/', 'red', 'Şanzıman sorunu belirtisi', true],
        ['/\b(motor ?rotus\w*|rotuslu|revizyon\w*|motor (yapildi|yapilmis|indi|indirildi)|kapak (yapildi|indi))\b/', 'amber', 'Motor rötuş/revizyon görmüş', false],
        ['/\b(taksi|ticari taksi|filo|kiralik|rent a car|kurumsal kiralama)\b/', 'amber', 'Taksi/filo/kiralık geçmişi olabilir', true],
        ['/\b(dpf|egr|katalitik|katalizor|adblue)\b.{0,20}\b(iptal|sokul\w*|ariza(?!siz)\w*)\b/', 'amber', 'DPF/EGR/katalizör iptali: muayene ve emisyon sorunu çıkarabilir', true],
        ['/\b(ariza lamba\w*|motor lamba\w*|check engine|uyari lamba\w*)\b/', 'amber', 'Arıza lambası yanıyor olabilir', true],
        ['/\b(yuruyen aksam|on takim|arka takim|amortisor)\w*\b.{0,25}\b(ses(?!siz)\w*|sorun(?!suz)\w*|yapilacak|degis\w*)\b/', 'amber', 'Yürüyen aksam masrafı olabilir', true],
        ['/\b(klima)\b.{0,20}\b(calismiyor|ariza(?!siz)\w*|gaz\w*|sogutmuyor)\b/', 'amber', 'Klima arızası', true],
        ['/\b(senet\w*|senetle|vadeli|taksitle|kredi karti(na|yla)?)\b/', 'amber', 'Senet/taksitli satış: borç ve dolandırıcılık riskine dikkat', true],
        ['/\b(muhayyer|ekspertize? (gerek yok|izin yok|gostermem|getirmem)|ekspertiz yok)\b/', 'amber', 'Ekspertize kapalı/muhayyer satış', false],
        ['/\b(hasarli|kazali|carpma|darbe|gocuk\w*|ezik\w*|cizik\w*)\b/', 'amber', 'Mevcut hasar/göçük/çizik belirtiliyor: onarım maliyetini yerinde gör', true],
        ['/\b(acil|acilen|ihtiyactan|nakit ihtiyac\w*|bugun satilik|hemen satilik)\b/', 'green', 'Acil satış: pazarlık şansı yüksek', true],
        ['/\b(pazarlik payi var|pazarlikli|pazarlik olur|uygun teklif)\b/', 'green', 'Pazarlığa açık', true],
        ['/\b(son fiyat|pazarlik yok|pazarlik yapilmaz|net fiyat)\b/', 'info', 'Satıcı pazarlığa kapalı olduğunu yazıyor', false],
        ['/\b(takasa? (uygun|olur|dusunulur)|takas (yapilir|olur))\b/', 'info', 'Takasa açık', true],
        ['/\b(bakimlari (yapildi|yapilmis|zamaninda|tam)|servis bakim\w*|yetkili servis bakim\w*|bakimli)\b/', 'green', 'Bakımları yapılmış (fatura/kayıt iste)', true],
        ['/\b(triger\w*|eksantrik)\b.{0,20}\b(yeni|degisti|yapildi|yapilmis)\b/', 'green', 'Triger seti yeni', true],
        ['/\b(debriyaj|baski balata|volan)\b.{0,20}\b(yeni|degisti|yapildi|yapilmis)\b/', 'green', 'Debriyaj seti yeni', true],
        ['/\blastik\w*\b.{0,20}\b(yeni|sifir)\b/', 'green', 'Lastikler yeni', true],
        ['/\bmuayene\w*\b.{0,20}\b(yeni|var|\d{4}( yilina)? kadar)\b/', 'green', 'Muayenesi var', true],
        ['/\b(ilk sahibinden|tek sahibinden|ilk el|tek el|sifirdan beri)\b/', 'green', 'İlk/tek sahibinden', true],
        ['/\b(garaj araci|garajda)\b/', 'green', 'Garaj aracı', true],
    ];

    /**
     * @param array<string, mixed> $payload eklentiden: url, title, price_text, pairs, sections, description, text
     * @return array<string, mixed>
     */
    public static function parse(array $payload): array
    {
        $clean = static fn (mixed $s, int $max): string => mb_substr(ListingParser::scrubPhones(trim((string) preg_replace('/[ \t]+/u', ' ', (string) $s))), 0, $max);

        $pairs = [];
        foreach (array_slice(is_array($payload['pairs'] ?? null) ? $payload['pairs'] : [], 0, 300) as $pair) {
            if (!is_array($pair) || count($pair) < 2) {
                continue;
            }
            $label = $clean($pair[0], 60);
            $value = $clean($pair[1], 200);
            $key = Text::fold(rtrim($label, ': '));
            if ($key !== '' && $value !== '' && !isset($pairs[$key])) {
                $pairs[$key] = ['label' => $label, 'value' => $value];
            }
        }
        $sections = [];
        foreach (array_slice(is_array($payload['sections'] ?? null) ? $payload['sections'] : [], 0, 12) as $section) {
            if (is_array($section)) {
                $sections[] = ['title' => $clean($section['title'] ?? '', 120), 'text' => $clean($section['text'] ?? '', 4000)];
            }
        }
        $description = $clean($payload['description'] ?? '', 6000);
        $text = $clean($payload['text'] ?? '', 15000);
        $title = $clean($payload['title'] ?? '', 300);

        $get = static function (array $labels) use ($pairs): ?string {
            foreach ($labels as $label) {
                if (isset($pairs[$label])) {
                    return $pairs[$label]['value'];
                }
            }
            return null;
        };

        $data = [
            'title' => $title,
            'price' => null,
            'km' => null,
            'year' => null,
            'brand' => $get(['marka']),
            'series' => $get(['seri']),
            'model' => $get(['model']),
            'fuel' => $get(['yakit', 'yakit tipi']),
            'gearbox' => $get(['vites', 'vites tipi']),
            'body' => $get(['kasa tipi', 'kasa']),
            'color' => $get(['renk']),
            'engine_hp' => null,
            'engine_cc' => null,
            'drive' => $get(['cekis']),
            'condition' => $get(['arac durumu', 'durumu']),
            'heavy_damage' => null,
            'seller_type' => null,
            'swap' => null,
            'warranty' => null,
            'plate' => $get(['plaka / uyruk', 'plaka/uyruk', 'plaka', 'uyruk']),
            'listing_no' => $get(['ilan no', 'ilan numarasi']),
            'listing_date' => $get(['ilan tarihi']),
            'tramer' => null,
            'tramer_source' => null,
            'parts' => [],
            'parts_source' => null,
            'counts' => [],
            'clean_claim' => false,
            'flags' => [],
            'description' => $description,
        ];

        // Fiyat, km, yıl, motor.
        $priceText = $get(['fiyat', 'ilan fiyati']) ?? (string) ($payload['price_text'] ?? '');
        $data['price'] = self::money($priceText);
        $kmText = $get(['km', 'kilometre', 'kilometresi']);
        $data['km'] = $kmText !== null ? Text::amount($kmText) : null;
        $yearText = $get(['yil', 'model yili']);
        if ($yearText !== null && preg_match('/\b(19[89]\d|20[0-4]\d)\b/', $yearText, $m) === 1) {
            $data['year'] = (int) $m[1];
        }
        $hp = $get(['motor gucu']);
        if ($hp !== null && preg_match('/(\d{2,4})/', $hp, $m) === 1) {
            $data['engine_hp'] = (int) $m[1];
        }
        $cc = $get(['motor hacmi']);
        if ($cc !== null && preg_match('/(\d{3,4})/', str_replace('.', '', $cc), $m) === 1) {
            $data['engine_cc'] = (int) $m[1];
        }

        // Sayfa metninden ve açıklamadan yedek okuma (yapılandırılmış alan yoksa). Satır sonları korunur.
        $foldedText = implode("\n", array_map(static fn (string $line): string => Text::fold($line), preg_split('/\R/u', $title . "\n" . $text . "\n" . $description) ?: []));
        if ($data['km'] === null && preg_match('/\b(\d{1,3}(?:[.,]\d{3})+|\d{4,7})\s*(?:km|kilometre)\b/', $foldedText, $m) === 1) {
            $data['km'] = Text::amount($m[1]);
        }
        if ($data['km'] === null && preg_match('/(?:^|\n)\s*(?:km|kilometre)\s*:?\s*(\d{1,3}(?:[.,]\d{3})+|\d{4,7})\b/', $foldedText, $m) === 1) {
            $data['km'] = Text::amount($m[1]);
        }
        if ($data['price'] === null && preg_match('/(\d{1,3}(?:\.\d{3})+)\s*(?:tl|₺)/u', $foldedText, $m) === 1) {
            $data['price'] = Text::amount($m[1]);
        }
        if ($data['year'] === null && preg_match('/\b(?:yil|model yili)\b\s*:?\s*(19[89]\d|20[0-4]\d)\b|\b(19[89]\d|20[0-4]\d)\s*(?:model|modeli)\b|\bmodel\s*:?\s*(19[89]\d|20[0-4]\d)\b/', $foldedText, $m) === 1) {
            $data['year'] = (int) ($m[1] ?: ($m[2] ?? '') ?: ($m[3] ?? ''));
        }

        $heavy = $get(['agir hasar kayitli', 'agir hasarli', 'agir hasar kaydi']);
        if ($heavy !== null) {
            $data['heavy_damage'] = self::yes($heavy);
        }
        $seller = $get(['kimden', 'satici tipi', 'satici']);
        if ($seller !== null) {
            $f = Text::fold($seller);
            $data['seller_type'] = str_contains($f, 'galeri') ? 'galeriden' : (str_contains($f, 'yetkili') ? 'yetkili bayiden' : (str_contains($f, 'sahibinden') ? 'sahibinden' : $seller));
        }
        $swap = $get(['takas', 'takasa uygun']);
        if ($swap !== null) {
            $data['swap'] = self::yes($swap);
        }
        $warranty = $get(['garanti', 'garantisi']);
        if ($warranty !== null) {
            $data['warranty'] = self::yes($warranty);
        }
        if ($data['condition'] !== null && str_contains(Text::fold($data['condition']), 'hasar')) {
            $data['flags'][] = ['level' => 'amber', 'text' => 'İlan "hasarlı araç" olarak girilmiş: onarım maliyetini yerinde gör'];
        }

        // Parçalar: önce yapılandırılmış alanlar (parça => durum çiftleri, boya-değişen bölümleri), yoksa açıklama.
        foreach ($pairs as $key => $pair) {
            $parts = self::findParts($key);
            $status = self::status(Text::fold($pair['value']));
            if (count($parts) === 1 && $status !== null && mb_strlen($key) <= 30) {
                $data['parts'][$parts[0]] = $status;
            }
        }
        foreach ($sections as $section) {
            foreach (self::partsFromSection($section['title'] . "\n" . $section['text']) as $part => $status) {
                $data['parts'][$part] = $status;
            }
            self::cleanAndCounts(Text::fold($section['text']), $data);
        }
        $summary = $get(['boya-degisen', 'boya degisen', 'boya / degisen', 'boyali / degisen', 'boya degisen bilgisi']);
        if ($summary !== null) {
            self::cleanAndCounts(Text::fold($summary), $data);
        }
        if ($data['parts'] !== []) {
            $data['parts_source'] = 'ilan';
        }

        $foldedDescription = Text::fold($description !== '' ? $description : $text);
        if ($data['parts'] === []) {
            $fromDescription = self::partsFromProse($foldedDescription);
            if ($fromDescription !== []) {
                $data['parts'] = $fromDescription;
                $data['parts_source'] = 'aciklama';
            }
        }
        self::cleanAndCounts($foldedDescription, $data);

        // Tramer: önce alan, sonra açıklama.
        $tramerText = $get(['tramer', 'tramer kaydi', 'tramer tutari', 'hasar kaydi', 'hasar kaydi tutari', 'sigorta hasar kaydi']);
        if ($tramerText !== null) {
            $f = Text::fold($tramerText);
            $data['tramer'] = preg_match('/\b(yok|yoktur|hayir|temiz|0)\b/', $f) === 1 && self::money($tramerText) === null ? 0 : self::money($tramerText);
            $data['tramer_source'] = $data['tramer'] !== null ? 'ilan' : null;
        }
        if ($data['tramer'] === null) {
            $data['tramer'] = self::tramerFromProse($foldedDescription);
            $data['tramer_source'] = $data['tramer'] !== null ? 'aciklama' : null;
        }

        // Ağır hasar alanı yoksa açıklamadan (olumsuzluk ifadeleri hariç).
        if ($data['heavy_damage'] === null && preg_match('/\bagir hasar\w*\b(.{0,25})/', $foldedDescription, $m) === 1) {
            $data['heavy_damage'] = preg_match('/\b(yok\w*|degil\w*|bulunmamakta\w*|hayir|kaydi yok)\b/', $m[1]) === 1 ? false : true;
        }

        foreach (self::FLAG_RULES as [$pattern, $level, $message, $negatable]) {
            if (preg_match($pattern, $foldedDescription, $m, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            if ($negatable) {
                $after = substr($foldedDescription, $m[0][1] + strlen($m[0][0]), 25);
                if (preg_match('/^\W*(yok\w*|degil\w*|sorunsuz|bulunmamakta\w*|olmamistir|hic yok|alinmaz|istenmez|almiyorum|istemiyorum|almam|istemem)\b/', $after) === 1) {
                    continue;
                }
                $before = substr($foldedDescription, max(0, $m[0][1] - 12), min(12, $m[0][1]));
                if (preg_match('/\b(hic|hicbir|asla)\s*$/', $before) === 1) {
                    continue;
                }
            }
            $data['flags'][] = ['level' => $level, 'text' => $message];
        }
        if ($data['seller_type'] === 'galeriden') {
            $data['flags'][] = ['level' => 'info', 'text' => 'Galeriden: pazarlık payı genelde daha düşük, faturalı satış ve garanti sor'];
        }
        $data['flags'] = array_values(array_unique($data['flags'], SORT_REGULAR));

        return $data;
    }

    /** "545.000 TL", "1.250.000 ₺", "45 bin" → tam sayı TL. */
    public static function money(string $text): ?int
    {
        $f = Text::fold($text);
        if (preg_match('/(\d{1,3}(?:[.,]\d{3})+|\d+(?:[.,]\d+)?)\s*(milyon|bin|k)?\b/', $f, $m) !== 1) {
            return null;
        }
        $unit = $m[2] ?? '';
        if ($unit === 'milyon' || $unit === 'bin' || $unit === 'k') {
            $value = (float) str_replace(',', '.', str_replace('.', '', preg_match('/^\d+[.,]\d{1,2}$/', $m[1]) === 1 ? str_replace('.', ',', $m[1]) : $m[1]));
            return (int) round($value * ($unit === 'milyon' ? 1_000_000 : 1000));
        }
        return Text::amount($m[1]);
    }

    private static function yes(string $value): ?bool
    {
        $f = Text::fold($value);
        if (preg_match('/\b(evet|var|uygun|mevcut)\b/', $f) === 1 && preg_match('/\b(degil|yok)\b/', $f) !== 1) {
            return true;
        }
        if (preg_match('/\b(hayir|yok|degil)\b/', $f) === 1) {
            return false;
        }
        return null;
    }

    /** @return list<string> metinde geçen parçalar (uzun eş anlamlılar önce, çakışma yok) */
    public static function findParts(string $folded): array
    {
        static $synonyms = null;
        if ($synonyms === null) {
            $synonyms = [];
            foreach (self::PARTS as $part => $list) {
                foreach ($list as $s) {
                    $synonyms[$s] = $part;
                }
            }
            uksort($synonyms, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        }
        $found = [];
        $taken = [];
        foreach ($synonyms as $synonym => $part) {
            if (preg_match_all('/\b' . preg_quote($synonym, '/') . '\b/', $folded, $mm, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }
            foreach ($mm[0] as [$match, $offset]) {
                $end = $offset + strlen($match);
                // "tavan döşemesi", "kapı kolu", "kapı aynası" gibi kaporta olmayan parçalar.
                if (preg_match(self::NOT_BODY, substr($folded, $end, 20)) === 1) {
                    continue;
                }
                foreach ($taken as [$s, $e]) {
                    if ($offset < $e && $end > $s) {
                        continue 2;
                    }
                }
                $taken[] = [$offset, $end];
                $found[$offset] = $part;
            }
        }
        ksort($found);
        return array_values(array_unique($found));
    }

    /** Katlanmış metindeki durum kelimesi. */
    public static function status(string $folded): ?string
    {
        if (preg_match('/\blokal\w*/', $folded) === 1) {
            return self::LOCAL;
        }
        if (preg_match('/\b(degisen|degismis|degisti|degistirilmis|degisik|yenilenmis)\b/', $folded) === 1) {
            return self::REPLACED;
        }
        if (preg_match('/\b(boyali|boyanmis|boyandi|boya)\b/', $folded) === 1) {
            return self::PAINTED;
        }
        if (preg_match('/\b(orijinal|orjinal|original|boyasiz|hatasiz|islemsiz|temiz)\b/', $folded) === 1) {
            return self::ORIGINAL;
        }
        return null;
    }

    /**
     * Boya-değişen bölümü: "Boyanmış\nSağ Ön Çamurluk, Kaput\nDeğişmiş\nÖn Tampon" ya da "Sol Ön Kapı: Boyalı" satırları.
     *
     * @return array<string, string>
     */
    public static function partsFromSection(string $text): array
    {
        $parts = [];
        $current = null;
        foreach (preg_split('/\R|•|\|/u', $text) ?: [] as $line) {
            $f = Text::fold($line);
            if ($f === '') {
                continue;
            }
            $found = self::findParts($f);
            $status = self::status($f);
            if ($found === []) {
                // Yalnızca durum başlığı ("Boyanmış Parçalar", "Değişen") → sonraki satırlar bu durumda.
                if ($status !== null && mb_strlen($f) <= 40) {
                    $current = $status;
                }
                continue;
            }
            if (str_contains($f, ':')) {
                [$left, $right] = explode(':', $f, 2);
                $leftStatus = self::status($left);
                $rightStatus = self::status($right);
                if ($leftStatus !== null && self::findParts($left) === []) {
                    foreach (self::findParts($right) as $part) {
                        $parts[$part] = $leftStatus;
                    }
                    continue;
                }
                if ($rightStatus !== null && self::findParts($right) === []) {
                    foreach (self::findParts($left) as $part) {
                        $parts[$part] = $rightStatus;
                    }
                    continue;
                }
            }
            $applied = $status ?? $current;
            if ($applied !== null) {
                foreach ($found as $part) {
                    $parts[$part] = $applied;
                }
            }
        }
        return $parts;
    }

    /**
     * Serbest metin: "sağ ön kapı ve kaput boyalı, ön tampon değişen". Her cümlede durum kelimesi kendinden önceki
     * parçalara uygulanır; cümle sonunda kalan parçalar en son görülen duruma bağlanır.
     *
     * @return array<string, string>
     */
    public static function partsFromProse(string $folded): array
    {
        $parts = [];
        foreach (preg_split('/[.;!?\n]+/u', $folded) ?: [] as $sentence) {
            if (trim($sentence) === '') {
                continue;
            }
            $tokens = [];
            foreach (self::findParts($sentence) as $part) {
                foreach (self::PARTS[$part] as $synonym) {
                    $pos = strpos($sentence, $synonym);
                    if ($pos !== false) {
                        $tokens[$pos] = ['part', $part];
                        break;
                    }
                }
            }
            if ($tokens === []) {
                continue;
            }
            if (preg_match_all('/\b(lokal\w*|degisen|degismis|degisti|degistirilmis|boyali|boyanmis|boyandi|boya|orijinal|orjinal|boyasiz|islemsiz)\b/', $sentence, $mm, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($mm[0] as [$word, $offset]) {
                    // "boya yok", "boyasız" gibi olumsuzlar orijinal sayılır.
                    $after = substr($sentence, $offset + strlen($word), 12);
                    $status = preg_match('/^\s*(yok|yoktur|degil)\b/', $after) === 1 ? self::ORIGINAL : self::status($word);
                    if ($status !== null) {
                        $tokens[$offset] = ['status', $status];
                    }
                }
            }
            ksort($tokens);
            $pending = [];
            $last = null;
            foreach ($tokens as [$type, $value]) {
                if ($type === 'part') {
                    $pending[] = $value;
                    continue;
                }
                foreach ($pending as $part) {
                    $parts[$part] = $value;
                }
                $pending = [];
                $last = $value;
            }
            if ($last !== null) {
                foreach ($pending as $part) {
                    $parts[$part] ??= $last;
                }
            }
        }
        return $parts;
    }

    /**
     * "Tamamı orijinal", "hatasız", "boyasız değişensiz" iddiaları ve "3 parça boyalı, 1 değişen" sayıları.
     *
     * @param array<string, mixed> $data
     */
    private static function cleanAndCounts(string $folded, array &$data): void
    {
        if (preg_match('/\b(tamami orijinal|tamamen orijinal|hatasiz|boyasiz|degisensiz|boya ?yok|degisen ?yok|kazasiz|hasarsiz|islemsiz)\b/', $folded) === 1) {
            $data['clean_claim'] = true;
        }
        $map = ['lokal' => self::LOCAL, 'boya' => self::PAINTED, 'degis' => self::REPLACED];
        if (preg_match_all('/\b(\d{1,2})\s*(?:parca|adet|yerde|yeri|yerinde)?\s*(lokal\w*|boya\w*|degis\w*)/', $folded, $mm, PREG_SET_ORDER) > 0) {
            foreach ($mm as [, $count, $word]) {
                foreach ($map as $prefix => $status) {
                    if (str_starts_with($word, $prefix)) {
                        $data['counts'][$status] = max((int) ($data['counts'][$status] ?? 0), (int) $count);
                        break;
                    }
                }
            }
        }
    }

    /** Açıklamadaki tramer tutarı; "tramersiz/tramer yok" → 0, bulunamazsa null. */
    public static function tramerFromProse(string $folded): ?int
    {
        if (preg_match('/\b(tramersiz|tramer (kaydi )?(yok|yoktur|bulunmamaktadir)|hasar kaydi (yok|yoktur|bulunmamaktadir)|tramer 0\b)/', $folded) === 1) {
            return 0;
        }
        $number = '(\d{1,3}(?:[.,]\d{3})+|\d+(?:[.,]\d+)?)\s*(milyon|bin|k|tl)?';
        if (preg_match('/\b(?:tramer\w*|hasar kayd\w*|hasar tutar\w*|sigorta kayd\w*)\D{0,20}?' . $number . '/', $folded, $m) === 1
            || preg_match('/' . $number . '\s*(?:tl)?\s*(?:lik|luk)?\s*(?:tramer\w*|hasar kayd\w*)/', $folded, $m) === 1) {
            $amount = self::money($m[1] . ' ' . ($m[2] ?? ''));
            if ($amount === null) {
                return null;
            }
            // "tramer 15" gibi birimsiz küçük sayılar binlik yazılmıştır.
            if ($amount < 1000 && ($m[2] ?? '') === '') {
                $amount *= 1000;
            }
            return $amount > 0 && $amount < 10_000_000 ? $amount : null;
        }
        return null;
    }
}
