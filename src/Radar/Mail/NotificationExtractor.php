<?php

declare(strict_types=1);

namespace Ozoto\Radar\Mail;

use Ozoto\Radar\ListingParser;
use Ozoto\Support\Http;

/**
 * Sitelerin "kayıtlı arama / yeni ilan" bildirim e-postalarından ilan linklerini ve her ilanın kart metnini çıkarır.
 * E-posta biçimleri değişebildiği için şablona bağlı değildir: her ilan linki için yalnızca o ilanı içeren
 * en geniş HTML bloğunu (ilan kartı) bulur ve metnini (başlık, fiyat, km, yıl, şehir) kullanır.
 */
final class NotificationExtractor
{
    private const LISTING_HOSTS = ['sahibinden.com', 'arabam.com', 'letgo.com', 'facebook.com', 'otoplus.com', 'otobid.com'];
    /** Bu linkler asla açılmaz (aboneliği iptal edebilir). */
    private const SKIP_WORDS = '/(unsubscribe|abonelik|aboneli|iptal|vazgec|vazgeç|bildirim ayar|e-posta ayar|settings|preferences|optout|opt-out)/iu';
    private const LOOKS_LIKE_LISTING = '/(\d{1,3}(?:[.\s]\d{3})+\s*(?:tl|₺)|\b(19[89]\d|20[0-4]\d)\b|\bkm\b)/iu';

    /** @var array<string, ?string> */
    private array $resolved = [];

    public function __construct(private readonly bool $followRedirects = true)
    {
    }

    /** @return list<array{url: string, title: string, text: string}> */
    public function extract(MimeMessage $message): array
    {
        $html = $message->html();
        if ($html !== null && class_exists(\DOMDocument::class)) {
            $items = $this->fromHtml($html);
            if ($items !== []) {
                return $items;
            }
        }
        return $this->fromText($message->text() ?? strip_tags((string) $html));
    }

    /** @return list<array{url: string, title: string, text: string}> */
    public function fromHtml(string $html): array
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // 1) Her <a> için hedef ilan linkini bul.
        $keys = [];
        $anchors = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));
            $label = trim($a->textContent . ' ' . $href);
            if ($href === '' || preg_match(self::SKIP_WORDS, $label) === 1) {
                continue;
            }
            $context = $a->parentNode instanceof \DOMNode ? (string) $a->parentNode->parentNode?->textContent : '';
            $url = $this->resolve($href, preg_match(self::LOOKS_LIKE_LISTING, $a->textContent . ' ' . $context) === 1);
            if ($url !== null) {
                $key = ListingParser::urlHash($url);
                $keys[spl_object_id($a)] = $key;
                $anchors[] = [$a, $url, $key];
            }
        }

        // 2) Her ilan için kartı bul: yukarı çıkarken içinde başka ilan linki olmayan en geniş blok.
        $items = [];
        foreach ($anchors as [$a, $url, $key]) {
            $card = $a;
            $node = $a->parentNode;
            for ($depth = 0; $depth < 10 && $node instanceof \DOMElement; $depth++, $node = $node->parentNode) {
                $others = false;
                foreach ($node->getElementsByTagName('a') as $inner) {
                    $innerKey = $keys[spl_object_id($inner)] ?? null;
                    if ($innerKey !== null && $innerKey !== $key) {
                        $others = true;
                        break;
                    }
                }
                if ($others || in_array($node->nodeName, ['body', 'html'], true)) {
                    break;
                }
                $card = $node;
            }

            $text = $this->text($card);
            $title = trim(preg_replace('/\s+/u', ' ', $a->textContent) ?? '');
            if ($title === '') {
                foreach ($a->getElementsByTagName('img') as $img) {
                    $title = trim($img->getAttribute('alt'));
                    break;
                }
            }
            $existing = $items[$key] ?? ['url' => $url, 'title' => '', 'text' => ''];
            // Aynı ilana giden birden fazla link olabilir (resim + başlık): en uzun başlık ve en geniş kart metni kalır.
            if (mb_strlen($title) > mb_strlen($existing['title'])) {
                $existing['title'] = $title;
            }
            if (mb_strlen($text) > mb_strlen($existing['text'])) {
                $existing['text'] = mb_substr($text, 0, 800);
            }
            $items[$key] = $existing;
        }
        return array_values($items);
    }

    /** @return list<array{url: string, title: string, text: string}> */
    public function fromText(string $text): array
    {
        $items = [];
        if (preg_match_all('#https?://[^\s<>"\')\]]+#u', $text, $m, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }
        $matches = $m[0];
        foreach ($matches as $i => [$href, $offset]) {
            $start = $i > 0 ? $matches[$i - 1][1] + strlen($matches[$i - 1][0]) : max(0, $offset - 300);
            $context = substr($text, $start, $offset - $start);
            $url = $this->resolve($href, preg_match(self::LOOKS_LIKE_LISTING, $context) === 1);
            if ($url === null) {
                continue;
            }
            $key = ListingParser::urlHash($url);
            $clean = trim((string) preg_replace('/\s+/u', ' ', $context));
            $items[$key] ??= ['url' => $url, 'title' => '', 'text' => mb_substr($clean, -800)];
        }
        return array_values($items);
    }

    /**
     * Linki ilan adresine çevirir: doğrudan ilan linki, parametre içinde gömülü ilan linki veya
     * (yalnızca ilan kartındaki) üçüncü taraf tıklama takip linki. İlan sitelerine istek atılmaz.
     */
    public function resolve(string $href, bool $mayFollow, int $hops = 3): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cacheKey = $href . '|' . ($mayFollow ? 1 : 0);
        if (array_key_exists($cacheKey, $this->resolved)) {
            return $this->resolved[$cacheKey];
        }
        $result = null;
        if (ListingParser::isWebUrl($href)) {
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            if (self::isListingHost($host)) {
                if (ListingParser::isListingUrl($href)) {
                    $result = self::stripTracking($href);
                } elseif (preg_match('#marketplace(?:/|%2F)item(?:/|%2F)(\d{6,20})#i', $href, $m) === 1) {
                    // Facebook bildirimleri: facebook.com/n/?marketplace%2Fitem%2F123...
                    $result = 'https://www.facebook.com/marketplace/item/' . $m[1];
                }
            } else {
                parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
                array_walk_recursive($query, function ($value) use (&$result, $hops): void {
                    if ($result === null && is_string($value) && $hops > 0) {
                        $candidate = rawurldecode($value);
                        if (ListingParser::isWebUrl($candidate)) {
                            $result = $this->resolve($candidate, false, $hops - 1);
                        }
                    }
                });
                if ($result === null && $mayFollow && $this->followRedirects && $hops > 0) {
                    $target = Http::location($href);
                    if ($target !== null) {
                        $result = $this->resolve($target, false, $hops - 1);
                    }
                }
            }
        }
        return $this->resolved[$cacheKey] = $result;
    }

    private static function isListingHost(string $host): bool
    {
        foreach (self::LISTING_HOSTS as $listingHost) {
            if ($host === $listingHost || str_ends_with($host, '.' . $listingHost)) {
                return true;
            }
        }
        return false;
    }

    /** utm_* gibi takip parametrelerini atar; ilan linkinin kendisi kalır. */
    private static function stripTracking(string $url): string
    {
        $parts = parse_url($url);
        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '');
        return $base;
    }

    private function text(\DOMNode $node): string
    {
        $alts = [];
        if ($node instanceof \DOMElement) {
            foreach ($node->getElementsByTagName('img') as $img) {
                $alt = trim($img->getAttribute('alt'));
                if ($alt !== '') {
                    $alts[] = $alt;
                }
            }
        }
        // Hücreler/satırlar arasında boşluk kalsın diye metni düğüm düğüm topla.
        $chunks = [];
        $walker = static function (\DOMNode $n) use (&$walker, &$chunks): void {
            foreach ($n->childNodes as $child) {
                if ($child instanceof \DOMText) {
                    $chunks[] = $child->wholeText;
                } elseif ($child instanceof \DOMElement && !in_array($child->nodeName, ['style', 'script', 'head'], true)) {
                    $walker($child);
                    $chunks[] = ' ';
                }
            }
        };
        $walker($node);
        return trim((string) preg_replace('/\s+/u', ' ', implode('', $chunks) . ' ' . implode(' ', array_unique($alts))));
    }
}
