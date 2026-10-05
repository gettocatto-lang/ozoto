<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\App;
use Ozoto\RateLimiter;
use Ozoto\Radar\DamageModel;
use Ozoto\Radar\DetailParser;
use Ozoto\Radar\Details;
use Ozoto\Radar\ListingParser;
use Ozoto\Radar\Listings;
use Ozoto\Settings;

/**
 * Tarayıcı eklentisinin API'si. Eklenti, kullanıcının ekranında açık olan liste/ilan sayfalarındaki
 * ilan kartlarını gönderir; Radar kaydeder, puanlar ve rozet için puanı geri döner.
 * Kimlik doğrulama: panelde üretilen eklenti anahtarı (Authorization: Bearer ...).
 */
final class ExtensionController
{
    public const NAME = 'eklenti';
    private const MAX_ITEMS = 100;

    public function status(): void
    {
        $this->authorize();
        $this->json(['ok' => true, 'site' => site_url('/')]);
    }

    public function ingest(): void
    {
        $tokenHash = $this->authorize();
        if (!RateLimiter::attempt('eklenti', $tokenHash, 240, 600)) {
            $this->json(['ok' => false, 'error' => 'Çok fazla istek; biraz bekleyin.'], 429);
        }

        $payload = json_decode((string) file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024), true);
        $items = is_array($payload) && is_array($payload['items'] ?? null) ? array_slice($payload['items'], 0, self::MAX_ITEMS) : [];

        $ids = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = trim((string) ($item['url'] ?? ''));
            if (!ListingParser::isListingUrl($url)) {
                continue;
            }
            // İlan sayfası açılıp ayrıntılı okunmuş kayıtlar, liste kartındaki daha kaba bilgiyle ezilmez.
            $existing = Listings::findByUrl($url);
            if ($existing !== null && Details::find((int) $existing['id']) !== null) {
                Listings::update((int) $existing['id'], ['last_seen_at' => now()]);
                $ids[$url] = (int) $existing['id'];
                continue;
            }
            $data = ListingParser::toListing($url, mb_substr((string) ($item['title'] ?? ''), 0, 300), mb_substr((string) ($item['text'] ?? ''), 0, 3000));
            $saved = Listings::upsert(self::NAME, $data);
            $ids[$url] = $saved['id'];
        }

        // Yanıtı geciktirmemek için en fazla birkaç saniye değerle; kalanlar sonraki istekte/zamanlanmış görevde puanlanır.
        Listings::valuatePending(40, microtime(true) + 6);

        $results = [];
        foreach ($ids as $url => $id) {
            $l = Listings::find($id);
            if ($l === null) {
                continue;
            }
            $results[] = [
                'url' => $url,
                'id' => $id,
                'score' => $l['score'] !== null ? (int) $l['score'] : null,
                'discount' => $l['discount_pct'] !== null ? (float) $l['discount_pct'] : null,
                'market' => $l['market_value'] !== null ? (int) $l['market_value'] : null,
                'suspicious' => (int) $l['suspicious'] === 1,
                'pending' => (int) $l['needs_valuation'] === 1,
                'link' => site_url('/yonetim/radar/' . $id),
            ];
        }
        $this->json(['ok' => true, 'results' => $results]);
    }

    /**
     * Kullanıcının açtığı ilan detay sayfası: araç bilgileri, parça parça boya/değişen, tramer, açıklama.
     * Kaydı günceller, değerler ve alım analizini (AL / PAZARLIK / GEÇ) döner.
     */
    public function detail(): void
    {
        $tokenHash = $this->authorize();
        if (!RateLimiter::attempt('eklenti', $tokenHash, 240, 600)) {
            $this->json(['ok' => false, 'error' => 'Çok fazla istek; biraz bekleyin.'], 429);
        }
        $payload = json_decode((string) file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024), true);
        if (!is_array($payload)) {
            $this->json(['ok' => false, 'error' => 'Geçersiz istek.'], 400);
        }
        $url = trim((string) ($payload['url'] ?? ''));
        if (!ListingParser::isListingUrl($url)) {
            $this->json(['ok' => false, 'error' => 'Bu sayfa tanınan bir ilan sayfası değil.'], 422);
        }
        @set_time_limit(60);

        $d = DetailParser::parse($payload);
        // Sitenin alanlarında olmayanları başlık, açıklama ve linkten tamamla.
        $guess = ListingParser::toListing($url, (string) $d['title'], $d['description'] . ' ' . mb_substr((string) ($payload['text'] ?? ''), 0, 4000));
        // "3 Serisi" + "320i ED" → "320i ED"; "C" + "C 200 d" → "C 200 d"; "Clio" + "1.5 dCi" → "Clio 1.5 dCi".
        $series = trim((string) ($d['series'] ?? ''));
        $modelName = trim((string) ($d['model'] ?? ''));
        if (preg_match('/serisi$/iu', $series) === 1 || ($series !== '' && str_starts_with(mb_strtolower($modelName), mb_strtolower($series)))) {
            $series = '';
        }
        $model = trim($series . ' ' . $modelName);
        $fields = [
            'url' => $url,
            'title' => $d['title'] !== '' ? $d['title'] : $guess['title'],
            'description' => $d['description'] !== '' ? mb_substr($d['description'], 0, 2000) : $guess['description'],
            'brand' => $d['brand'] !== null ? (ListingParser::canonicalBrand($d['brand']) ?? $d['brand']) : $guess['brand'],
            'model' => $model !== '' ? $model : $guess['model'],
            'model_year' => $d['year'] ?? $guess['model_year'],
            'km' => $d['km'] ?? $guess['km'],
            'price' => $d['price'] ?? $guess['price'],
            'fuel' => $d['fuel'] ?? $guess['fuel'],
            'gearbox' => $d['gearbox'] ?? $guess['gearbox'],
            'damage' => DamageModel::assess($d, null)['coarse'] ?? $guess['damage'],
            'city' => $guess['city'],
        ];
        $saved = Listings::upsert(self::NAME, $fields);
        $id = $saved['id'];
        $urgent = array_filter($d['flags'], static fn (array $f): bool => str_starts_with($f['text'], 'Acil')) !== [];
        Listings::override($id, $fields + ['urgent' => $urgent]);

        Details::save($id, $d, [
            'pairs' => array_slice(is_array($payload['pairs'] ?? null) ? $payload['pairs'] : [], 0, 300),
            'sections' => array_slice(is_array($payload['sections'] ?? null) ? $payload['sections'] : [], 0, 12),
            'text' => mb_substr(ListingParser::scrubPhones((string) ($payload['text'] ?? '')), 0, 6000),
        ]);
        Listings::valuate($id);

        $listing = Listings::find($id);
        $details = Details::find($id);
        $this->json([
            'ok' => true,
            'id' => $id,
            'link' => site_url('/yonetim/radar/' . $id),
            'score' => $listing !== null && $listing['score'] !== null ? (int) $listing['score'] : null,
            'pending' => $listing !== null && (int) $listing['needs_valuation'] === 1,
            'analysis' => $details['analysis'] ?? null,
        ]);
    }

    /** @return string anahtarın özeti (hız sınırı için) */
    private function authorize(): string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        $token = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : (string) ($_SERVER['HTTP_X_RADAR_TOKEN'] ?? '');
        $expected = (string) (App::installed() ? Settings::get('extension_token_hash', '') : '');
        $hash = hash('sha256', $token);
        if ($token === '' || $expected === '' || !hash_equals($expected, $hash)) {
            usleep(200_000);
            $this->json(['ok' => false, 'error' => 'Eklenti anahtarı geçersiz.'], 401);
        }
        return $hash;
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
