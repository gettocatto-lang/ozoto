<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Database;
use Ozoto\Settings;
use Ozoto\Support\Http;

/**
 * Kelepir bulunduğu anda telefona Telegram mesajı. Bot, Telegram'daki @BotFather ile kullanıcının kendi hesabından açılır;
 * sohbet, panelde gösterilen tek seferlik kodla (t.me/<bot>?start=<kod>) bağlanır.
 */
final class Telegram
{
    public const DEFAULT_MIN_SCORE = 60;

    /** Bir turda tek tek gönderilecek en fazla ilan; fazlası tek bir özet mesajında toplanır. */
    private const MAX_PER_RUN = 8;

    /** Telefona bildirim yalnızca kendiliğinden gelen ilanlar için (eklenti/elle eklenenleri ekip zaten görüyor). */
    private const SOURCES = ['eposta', 'arama'];

    public static function configured(): bool
    {
        return Settings::secret('tg_token') !== '' && (string) Settings::get('tg_chat') !== '';
    }

    public static function enabled(): bool
    {
        return Settings::get('tg_enabled') === '1' && self::configured();
    }

    public static function minScore(): int
    {
        $value = Settings::get('tg_min_score');
        return $value === null || $value === '' ? self::DEFAULT_MIN_SCORE : max(0, min(100, (int) $value));
    }

    /**
     * Yeni bot anahtarını doğrular ve saklar; sohbet bağlantısı için yeni bir kod üretir.
     *
     * @return string botun kullanıcı adı
     */
    public static function saveToken(string $token): string
    {
        if (preg_match('/^\d{5,15}:[A-Za-z0-9_-]{30,60}$/', $token) !== 1) {
            throw new \InvalidArgumentException('Bot anahtarı "123456789:ABC..." biçiminde olmalı (BotFather\'ın verdiği).');
        }
        $me = self::call($token, 'getMe');
        $username = (string) ($me['username'] ?? '');
        Settings::setSecret('tg_token', $token);
        Settings::set('tg_bot', $username);
        Settings::set('tg_chat', '');
        Settings::set('tg_chat_name', '');
        Settings::set('tg_code', bin2hex(random_bytes(6)));
        return $username;
    }

    /** Botun panel kodunu alan sohbeti bulur ve bildirim hedefi yapar. */
    public static function link(): string
    {
        $token = Settings::secret('tg_token');
        $code = (string) Settings::get('tg_code');
        if ($token === '' || $code === '') {
            throw new \RuntimeException('Önce bot anahtarını kaydedin.');
        }
        $updates = self::call($token, 'getUpdates', ['allowed_updates' => ['message'], 'timeout' => 0]);
        foreach (array_reverse(is_array($updates) ? $updates : []) as $update) {
            $message = $update['message'] ?? null;
            if (!is_array($message) || trim((string) ($message['text'] ?? '')) !== '/start ' . $code) {
                continue;
            }
            $chat = (array) ($message['chat'] ?? []);
            $name = (string) ($chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')));
            Settings::set('tg_chat', (string) ($chat['id'] ?? ''));
            Settings::set('tg_chat_name', $name);
            Settings::set('tg_code', bin2hex(random_bytes(6)));
            if ((string) Settings::get('tg_since', '') === '') {
                Settings::set('tg_since', now());
            }
            self::send('✅ Öz Oto Radar bağlandı. Puanı ' . self::minScore() . ' ve üstü olan yeni ilanlar buraya gelecek.');
            return $name !== '' ? $name : 'Telegram sohbeti';
        }
        throw new \RuntimeException('Bağlantı mesajı bulunamadı. "Telegram\'da botu aç" butonuna basıp botta BAŞLAT\'a dokunun, sonra tekrar deneyin.');
    }

    public static function send(string $html): void
    {
        self::call(Settings::secret('tg_token'), 'sendMessage', [
            'chat_id' => (string) Settings::get('tg_chat'),
            'text' => $html,
            'parse_mode' => 'HTML',
        ]);
    }

    /**
     * Puanı eşiğin üstünde olup henüz bildirilmemiş yeni ilanları gönderir.
     *
     * @return int gönderilen ilan sayısı (özet mesajındakiler dahil)
     */
    public static function dispatch(float $deadline): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count(self::SOURCES), '?'));
        $stmt = $pdo->prepare(
            'SELECT l.* FROM radar_listings l LEFT JOIN radar_alerts a ON a.listing_id = l.id '
            . 'WHERE a.listing_id IS NULL AND l.needs_valuation = 0 AND l.score >= ? AND l.suspicious = 0 '
            . "AND l.status NOT IN ('dismissed', 'bought') AND l.first_seen_at >= ? AND l.source IN ($placeholders) "
            . 'ORDER BY l.score DESC, l.id DESC LIMIT 200'
        );
        $stmt->execute([self::minScore(), (string) Settings::get('tg_since', now()), ...self::SOURCES]);
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return 0;
        }

        $mark = $pdo->prepare('INSERT INTO radar_alerts (listing_id, channel, sent_at) VALUES (?, ?, ?)');
        $sent = 0;
        foreach (array_slice($rows, 0, self::MAX_PER_RUN) as $row) {
            if (microtime(true) > $deadline) {
                return $sent;
            }
            self::send(self::format($row));
            $mark->execute([(int) $row['id'], 'telegram', now()]);
            $sent++;
        }

        $rest = array_slice($rows, self::MAX_PER_RUN);
        if ($rest !== []) {
            self::send(sprintf(
                '➕ Puanı %d ve üstü <b>%d ilan daha</b> var. Hepsi: <a href="%s">Radar</a>',
                self::minScore(),
                count($rest),
                e(site_url('/yonetim/radar?puan_min=' . self::minScore() . '&sirala=puan')),
            ));
            foreach ($rest as $row) {
                $mark->execute([(int) $row['id'], 'ozet', now()]);
            }
            $sent += count($rest);
        }
        return $sent;
    }

    /** @param array<string, mixed> $l */
    public static function format(array $l): string
    {
        $name = trim(((string) ($l['brand'] ?? '')) . ' ' . ((string) ($l['model'] ?? '')));
        if ($name === '') {
            $name = (string) ($l['title'] ?? 'İlan');
        }
        $facts = array_filter([
            (int) ($l['model_year'] ?? 0) > 0 ? (string) $l['model_year'] : null,
            ($l['km'] ?? null) !== null ? format_number((int) $l['km']) . ' km' : null,
            (string) ($l['damage'] ?? '') !== '' ? (string) $l['damage'] : null,
            (string) ($l['city'] ?? '') !== '' ? (string) $l['city'] : null,
        ]);
        $discount = ($l['discount_pct'] ?? null) !== null ? (float) $l['discount_pct'] : null;

        $lines = [];
        $lines[] = '<b>🔥 ' . (int) $l['score'] . ' puan'
            . ($discount !== null && $discount > 0 ? ' · piyasanın %' . number_format($discount, 0, ',', '.') . ' altında' : '') . '</b>';
        $lines[] = e($name) . ($facts !== [] ? ' · ' . e(implode(' · ', $facts)) : '');
        $price = 'Fiyat: <b>' . e(format_tl(($l['price'] ?? null) !== null ? (int) $l['price'] : null)) . '</b>';
        if (($l['market_value'] ?? null) !== null) {
            $price .= ' · Piyasa: ' . e(format_tl((int) $l['market_value']));
        }
        $lines[] = $price;
        if ((int) ($l['urgent'] ?? 0) === 1) {
            $lines[] = '⚡ Acil satış';
        }
        if ((int) ($l['is_auction'] ?? 0) === 1) {
            $lines[] = '🔨 İhale' . (($l['auction_ends_at'] ?? '') !== '' ? ' · bitiş ' . e(format_date((string) $l['auction_ends_at'])) : '');
        }
        $links = [];
        if (ListingParser::isWebUrl((string) ($l['url'] ?? ''))) {
            $links[] = '<a href="' . e((string) $l['url']) . '">İlana git</a>';
        }
        if ((int) ($l['id'] ?? 0) > 0) {
            $links[] = '<a href="' . e(site_url('/yonetim/radar/' . (int) $l['id'])) . '">Radar\'da aç</a>';
        }
        $lines[] = implode(' · ', $links);
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $params
     * @return mixed Telegram yanıtındaki "result"
     */
    private static function call(string $token, string $method, array $params = []): mixed
    {
        if ($token === '') {
            throw new \RuntimeException('Bot anahtarı kayıtlı değil.');
        }
        $response = Http::post(
            rtrim((string) config('telegram_api', 'https://api.telegram.org'), '/') . '/bot' . $token . '/' . $method,
            (string) json_encode($params, JSON_UNESCAPED_UNICODE),
            ['Content-Type' => 'application/json'],
            15,
        );
        $data = json_decode($response['body'], true);
        if (!is_array($data) || ($data['ok'] ?? false) !== true) {
            $description = is_array($data) ? (string) ($data['description'] ?? '') : '';
            throw new \RuntimeException('Telegram: ' . ($description !== '' ? $description : 'HTTP ' . $response['status']));
        }
        return $data['result'] ?? null;
    }
}
