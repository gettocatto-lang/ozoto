<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Support\Catalog;
use Ozoto\Support\Text;

/**
 * İlan başlığı, arama sonucu özeti veya ilan linkinden araç bilgisini çıkarır.
 * Sadece araçla ilgili alanlar alınır; satıcı adı, telefon gibi kişisel veriler alınmaz.
 */
final class ListingParser
{
    private const BRAND_ALIASES = [
        'vw' => 'Volkswagen',
        'mercedes benz' => 'Mercedes-Benz',
        'mercedes' => 'Mercedes-Benz',
        'citroen' => 'Citroën',
        'tofas' => 'Tofaş',
        'landrover' => 'Land Rover',
        'range rover' => 'Land Rover',
        'alfa' => 'Alfa Romeo',
        'ds automobiles' => 'DS',
    ];

    private const STOP_WORDS = [
        'model', 'modeli', 'sahibinden', 'galeriden', 'satilik', 'acil', 'temiz', 'hatasiz', 'boyasiz', 'degisensiz',
        'km', 'tl', 'beyaz', 'siyah', 'gri', 'kirmizi', 'mavi', 'lacivert', 'fume', 'gumus', 'bej', 'kahverengi',
        'otomatik', 'manuel', 'dizel', 'benzin', 'benzinli', 'lpg', 'hibrit', 'elektrik', 'ilan', 'ilani', 'fiyat',
        'en', 'ucuz', 'kelepir', 'ihtiyactan', 'borctan', 'takas', 'uygun', 'ozel', 'orjinal', 'orijinal', 'de', 'da',
        'ile', 've', 'arac', 'araba', 'otomobil', 'vasita', 'oto', 'ikinci', 'el', '2.el', 'sifir', 'detay', 'ilk',
        'sahibi', 'elden', 'kazasiz', 'tramersiz', 'boyali', 'degisenli', 'hasarli', 'hasar', 'kayitli', '-',
    ];

    private const URGENT_PATTERNS = [
        'acil', 'ihtiyactan', 'borctan', 'nakit ihtiyac', 'acele', 'kelepir', 'cok ucuz', 'yurt disina', 'yurtdisina',
        'tasinma', 'son fiyat', 'fiyat dustu', 'fiyati dustu', 'zararina', 'bugune ozel', 'hemen satilik',
    ];

    /** @var array<string, string>|null */
    private static ?array $brandIndex = null;

    /**
     * @return array{site: string, source_ref: ?string, slug_text: string, normalized: string}
     */
    public static function parseUrl(string $url): array
    {
        $parts = parse_url(trim($url));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = (string) preg_replace('/^(www|m|tr)\./', '', $host);
        $path = (string) ($parts['path'] ?? '');
        $ref = null;
        $slug = '';

        if (str_ends_with($host, 'sahibinden.com') && preg_match('#/ilan/(.+?)-(\d{8,12})(?:/|$)#', $path, $m) === 1) {
            [$slug, $ref] = [$m[1], $m[2]];
        } elseif (str_ends_with($host, 'arabam.com') && preg_match('#/ilan/(.+)/(\d{6,12})/?$#', $path, $m) === 1) {
            [$slug, $ref] = [$m[1], $m[2]];
        } elseif (str_ends_with($host, 'letgo.com') && preg_match('#/(?:item/)?(.*?)-iid-(\d+)#', $path, $m) === 1) {
            [$slug, $ref] = [$m[1], $m[2]];
        } elseif (str_ends_with($host, 'facebook.com') && preg_match('#/marketplace/item/(\d+)#', $path, $m) === 1) {
            $ref = $m[1];
        } elseif (preg_match_all('#\d{6,12}#', $path, $m) > 0) {
            $ref = (string) end($m[0]);
            $slug = $path;
        } else {
            $slug = $path;
        }

        $normalized = $host . rtrim($path, '/');
        if (str_ends_with($host, 'facebook.com') && $ref !== null) {
            $normalized = 'facebook.com/marketplace/item/' . $ref;
        }

        return [
            'site' => $host,
            'source_ref' => $ref,
            // arabam/letgo linklerinde "1-6" → "1.6" (motor hacmi)
            'slug_text' => trim((string) preg_replace('/\b(\d) (\d)\b/', '$1.$2', str_replace(['-', '/', '_'], ' ', $slug))),
            'normalized' => $normalized,
        ];
    }

    /** Arama sonuçlarından sadece tekil ilan sayfalarını kabul eder (liste/kategori sayfalarını değil). */
    /** Sadece http/https linkleri kabul edilir (javascript: vb. panelde tıklanabilir olmasın). */
    public static function isWebUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url(trim($url), PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) && (string) parse_url(trim($url), PHP_URL_HOST) !== '';
    }

    public static function isListingUrl(string $url): bool
    {
        if (!self::isWebUrl($url)) {
            return false;
        }
        $info = self::parseUrl($url);
        $site = $info['site'];
        $path = (string) parse_url($url, PHP_URL_PATH);
        return match (true) {
            str_ends_with($site, 'sahibinden.com') => str_contains($path, '/ilan/') && $info['source_ref'] !== null,
            str_ends_with($site, 'arabam.com') => str_contains($path, '/ilan/') && $info['source_ref'] !== null,
            str_ends_with($site, 'letgo.com') => str_contains($path, '-iid-'),
            str_ends_with($site, 'facebook.com') => str_contains($path, '/marketplace/item/'),
            default => $info['source_ref'] !== null,
        };
    }

    /**
     * Link + başlık + çevre metinden Radar kaydı alanlarını üretir (arama sonucu, e-posta bildirimi, eklenti).
     * Metinde olmayan bilgi linkteki kelimelerden tamamlanır.
     *
     * @return array<string, mixed>
     */
    public static function toListing(string $url, string $title, string $text): array
    {
        $clean = static fn (string $s): string => self::scrubPhones(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        $title = $clean($title);
        $text = $clean($text);
        $info = self::parseUrl($url);
        $fromText = self::parseText($title . ' ' . $text);
        $fromSlug = self::parseText($info['slug_text']);
        $pick = static fn (string $key) => $fromText[$key] ?? $fromSlug[$key] ?? null;
        // sahibinden/arabam/letgo linkleri düzenli: marka-model-yıl oradan daha güvenilir okunur.
        $vehicle = $fromSlug['brand'] !== null ? $fromSlug : $fromText;

        return [
            'url' => $url,
            'title' => mb_substr($title !== '' ? $title : $info['slug_text'], 0, 300),
            'description' => mb_substr($text, 0, 2000) ?: null,
            'brand' => $vehicle['brand'] ?? $fromSlug['brand'],
            'model' => $vehicle['model'] ?? $fromText['model'],
            'model_year' => $vehicle['model_year'] ?? $pick('model_year'),
            'km' => $pick('km'),
            'price' => $fromText['price'],
            'city' => $pick('city'),
            'fuel' => $pick('fuel'),
            'gearbox' => $pick('gearbox'),
            'damage' => $pick('damage'),
        ];
    }

    /** Metindeki telefon numaralarını siler (kişisel veri saklanmaz). */
    public static function scrubPhones(string $text): string
    {
        return (string) preg_replace('/(?<![\d.,])(?:\+?90[\s.\-]?)?\(?0?[2-5]\d{2}\)?[\s.\-]?\d{3}[\s.\-]?\d{2}[\s.\-]?\d{2}(?![\d.,])/u', '[tel]', $text);
    }

    public static function urlHash(string $url): string
    {
        return hash('sha256', self::parseUrl($url)['normalized']);
    }

    /**
     * @return array{brand: ?string, model: ?string, model_key: ?string, model_year: ?int, km: ?int, price: ?int,
     *               city: ?string, fuel: ?string, gearbox: ?string, damage: ?string, urgent: bool, urgent_words: list<string>}
     */
    public static function parseText(string $text): array
    {
        $folded = Text::fold(str_replace(['_', '|', '/', '–', '—'], ' ', $text));
        $currentYear = (int) date('Y');

        $year = null;
        if (preg_match('/\b(19[89]\d|20[0-4]\d)\s*model/u', $folded, $m) === 1) {
            $year = (int) $m[1];
        } elseif (preg_match_all('/(?<![\d.,])(19[89]\d|20[0-4]\d)(?![\d.,])/u', $folded, $m) > 0) {
            foreach ($m[1] as $candidate) {
                if ((int) $candidate <= $currentYear + 1) {
                    $year = (int) $candidate;
                    break;
                }
            }
        }

        $price = null;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*milyon/u', $folded, $m) === 1) {
            $price = (int) round((float) str_replace(',', '.', $m[1]) * 1_000_000);
        } elseif (preg_match('/(?<![\d.,])(\d{1,3}(?:\.\d{3})+|\d{1,3}(?:\s\d{3})+|\d{4,9})\s*(?:tl|try|₺)(?![a-z])/u', $folded, $m) === 1) {
            $price = Text::amount($m[1]);
        }
        if ($price !== null && ($price < 10_000 || $price > 500_000_000)) {
            $price = null;
        }

        $km = null;
        if (preg_match('/(\d{1,3}(?:[.\s]\d{3})+|\d{1,7})\s*(?:km|kilometre)(?![a-z])/u', $folded, $m) === 1) {
            $km = Text::amount($m[1]);
            if ($km !== null && $km > 2_000_000) {
                $km = null;
            }
        }

        [$brand, $brandEnd] = self::findBrand($folded);
        [$model, $modelKey] = $brand !== null ? self::findModel(substr($folded, $brandEnd)) : [null, null];
        // Model adı metinde birkaç kez geçebilir (resim etiketi + başlık): en ayrıntılı geçişi al.
        if ($modelKey !== null) {
            $offset = 0;
            while (preg_match('/(?<![a-z0-9])' . preg_quote($modelKey, '/') . '(?![a-z0-9])/u', $folded, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
                [$candidate] = self::findModel(substr($folded, $m[0][1]));
                if ($candidate !== null && substr_count($candidate, ' ') > substr_count((string) $model, ' ')) {
                    $model = $candidate;
                }
                $offset = $m[0][1] + strlen($m[0][0]);
            }
        }

        $city = null;
        foreach (Catalog::cities() as $candidate) {
            if (preg_match('/(?<![a-z])' . preg_quote(Text::fold($candidate), '/') . '(?![a-z])/u', $folded) === 1) {
                $city = $candidate;
                break;
            }
        }

        $urgentWords = [];
        foreach (self::URGENT_PATTERNS as $pattern) {
            if (preg_match('/(?<![a-z])' . preg_quote($pattern, '/') . '/u', $folded) === 1) {
                $urgentWords[] = $pattern;
            }
        }

        return [
            'brand' => $brand,
            'model' => $model,
            'model_key' => $modelKey,
            'model_year' => $year,
            'km' => $km,
            'price' => $price,
            'city' => $city,
            'fuel' => self::detectFuel($folded),
            'gearbox' => self::detectGearbox($folded),
            'damage' => self::detectDamage($folded),
            'urgent' => $urgentWords !== [],
            'urgent_words' => $urgentWords,
        ];
    }

    public static function canonicalBrand(string $name): ?string
    {
        $key = Text::fold(str_replace('-', ' ', $name));
        return self::brandIndex()[$key] ?? null;
    }

    public static function modelKey(?string $model): ?string
    {
        $tokens = Text::tokens((string) $model);
        return $tokens[0] ?? null;
    }

    /** @return array<string, string> folded ad → katalogdaki ad */
    private static function brandIndex(): array
    {
        if (self::$brandIndex === null) {
            $index = [];
            foreach (Catalog::brands() as $brand) {
                if ($brand !== 'Diğer') {
                    $index[Text::fold(str_replace('-', ' ', $brand))] = $brand;
                }
            }
            foreach (self::BRAND_ALIASES as $alias => $brand) {
                $index[$alias] = $brand;
            }
            // Uzun adlar önce denensin ("mercedes benz" > "mercedes", "alfa romeo" > "alfa").
            uksort($index, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            self::$brandIndex = $index;
        }
        return self::$brandIndex;
    }

    /** @return array{0: ?string, 1: int} marka ve metinde bittiği konum */
    private static function findBrand(string $folded): array
    {
        $haystack = str_replace('-', ' ', $folded);
        $best = null;
        $bestPos = PHP_INT_MAX;
        $bestEnd = 0;
        foreach (self::brandIndex() as $key => $brand) {
            if (preg_match('/(?<![a-z0-9])' . preg_quote($key, '/') . '(?![a-z0-9])/u', $haystack, $m, PREG_OFFSET_CAPTURE) === 1
                && $m[0][1] < $bestPos) {
                $best = $brand;
                $bestPos = $m[0][1];
                $bestEnd = $m[0][1] + strlen($m[0][0]);
            }
        }
        return [$best, $bestEnd];
    }

    /** @return array{0: ?string, 1: ?string} model metni ve model ailesi anahtarı */
    private static function findModel(string $rest): array
    {
        $words = [];
        foreach (preg_split('/\s+/u', trim(str_replace(['-', ',', '(', ')'], ' ', $rest))) ?: [] as $word) {
            $word = trim($word, '.:;!?"\'');
            if ($word === '') {
                continue;
            }
            $isYear = preg_match('/^(19[89]\d|20[0-4]\d)$/', $word) === 1;
            $isNumber = preg_match('/^\d{1,3}(\.\d{3})+$|^\d{4,}$/', $word) === 1;
            // Metin tekrar ediyorsa (resim etiketi + başlık) ya da başka bir marka adı geldiyse model bitti.
            if ($words !== [] && (in_array($word, $words, true) || isset(self::brandIndex()[$word]))) {
                break;
            }
            if ($isYear || $isNumber || in_array($word, self::STOP_WORDS, true) || self::isCity($word)) {
                if ($words !== []) {
                    break;
                }
                continue;
            }
            $words[] = $word;
            if (count($words) >= 4) {
                break;
            }
        }
        if ($words === []) {
            return [null, null];
        }
        $model = implode(' ', array_map(static fn (string $w): string => preg_match('/\d/', $w) === 1 ? strtoupper($w) : mb_convert_case($w, MB_CASE_TITLE, 'UTF-8'), $words));
        return [$model, $words[0]];
    }

    private static function isCity(string $word): bool
    {
        static $cities = null;
        $cities ??= array_map([Text::class, 'fold'], Catalog::cities());
        return in_array($word, $cities, true);
    }

    private static function detectFuel(string $folded): ?string
    {
        return match (true) {
            preg_match('/\b(hibrit|hybrid)\b/u', $folded) === 1 => 'Hibrit',
            preg_match('/\b(elektrikli|elektrik|electric)\b/u', $folded) === 1 => 'Elektrik',
            preg_match('/\blpg\b/u', $folded) === 1 => 'Benzin & LPG',
            preg_match('/\b(dizel|diesel|tdi|dci|crdi|hdi|cdti|multijet|d 4d|d4d|bluehdi|tdci)\b/u', $folded) === 1 => 'Dizel',
            preg_match('/\b(benzin|benzinli|tsi|tfsi|tce|vtec|ecoboost)\b/u', $folded) === 1 => 'Benzin',
            default => null,
        };
    }

    private static function detectGearbox(string $folded): ?string
    {
        return match (true) {
            preg_match('/\b(otomatik|dsg|cvt|edc|tiptronic|multidrive|s tronic|steptronic|eat8|powershift)\b/u', $folded) === 1 => 'Otomatik',
            preg_match('/\b(yari otomatik)\b/u', $folded) === 1 => 'Yarı otomatik',
            preg_match('/\b(manuel|duz vites)\b/u', $folded) === 1 => 'Manuel',
            default => null,
        };
    }

    /** "ağır hasar kayıtlı" geçiyor ve ardından "hayır/yok/değil" gelmiyor. */
    private static function heavyDamage(string $folded): bool
    {
        if (preg_match_all('/agir hasar\w*((?:\s+\S+){0,2})/u', $folded, $mm) < 1) {
            return false;
        }
        foreach ($mm[1] as $after) {
            if (preg_match('/\b(hayir|yok|yoktur|degil|degildir|bulunmamaktadir)\b/u', $after) !== 1) {
                return true;
            }
        }
        return false;
    }

    private static function detectDamage(string $folded): ?string
    {
        return match (true) {
            self::heavyDamage($folded) => 'Ağır hasar kayıtlı',
            preg_match('/\b(hatasiz|boyasiz ve degisensiz|boyasiz degisensiz)\b/u', $folded) === 1 => 'Hatasız / boyasız',
            preg_match('/\bdegisen(li)?\b/u', $folded) === 1 && preg_match('/\bboyali\b/u', $folded) === 1 => 'Boyalı ve değişenli',
            preg_match('/\bdegisen(li)?\b/u', $folded) === 1 => 'Değişenli',
            preg_match('/\bboyali\b/u', $folded) === 1 => 'Boyalı',
            default => null,
        };
    }
}
