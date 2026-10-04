<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\App;
use Ozoto\RateLimiter;
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
