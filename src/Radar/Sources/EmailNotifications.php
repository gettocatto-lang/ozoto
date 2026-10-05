<?php

declare(strict_types=1);

namespace Ozoto\Radar\Sources;

use Ozoto\Radar\ListingParser;
use Ozoto\Radar\Listings;
use Ozoto\Radar\Mail\ImapClient;
use Ozoto\Radar\Mail\MimeMessage;
use Ozoto\Radar\Mail\NotificationExtractor;
use Ozoto\Settings;

/**
 * E-posta bildirimleri kaynağı: sitelerin "kayıtlı arama → yeni ilan" e-postalarını Radar posta kutusundan okur.
 * İlan verisini siteler kendileri gönderir; hiçbir siteye tarama isteği atılmaz.
 */
final class EmailNotifications
{
    public const NAME = 'eposta';

    public const DEFAULT_SENDERS = "sahibinden.com\narabam.com\nletgo.com\nfacebookmail.com\notoplus.com\notobid.com";

    public static function configured(): bool
    {
        return (string) Settings::get('mail_host') !== '' && (string) Settings::get('mail_user') !== '' && Settings::secret('mail_pass') !== '';
    }

    /** @return list<string> */
    public static function allowedSenders(): array
    {
        $text = Settings::get('mail_senders') ?? self::DEFAULT_SENDERS;
        return array_values(array_filter(array_map(static fn (string $s): string => strtolower(trim($s, " \t@")), preg_split('/[\s,;]+/u', $text) ?: [])));
    }

    public static function client(): ImapClient
    {
        return new ImapClient(
            (string) Settings::get('mail_host', 'localhost'),
            (int) (Settings::get('mail_port') ?: 143),
            (string) (Settings::get('mail_security') ?: 'none'),
        );
    }

    /** @return string bağlantı testi sonucu */
    public static function test(): string
    {
        $client = self::client();
        $client->connect();
        try {
            $client->login((string) Settings::get('mail_user'), Settings::secret('mail_pass'));
            $total = $client->select((string) (Settings::get('mail_folder') ?: 'INBOX'));
            $unseen = count($client->search('UNSEEN'));
            return sprintf('Bağlantı başarılı: kutuda %d e-posta var, %d tanesi okunmamış.', $total, $unseen);
        } finally {
            $client->logout();
        }
    }

    /**
     * Kutudaki son e-postalar (en yeni önce): üyelik doğrulama linkleri ve kodları ile bildirim biçimini görmek için.
     * Okundu bayrağına dokunmaz.
     *
     * @return list<array{date: string, from: string, subject: string, snippet: string, links: list<array{text: string, url: string}>}>
     */
    public static function recent(int $limit = 10): array
    {
        $client = self::client();
        $client->connect();
        try {
            $client->login((string) Settings::get('mail_user'), Settings::secret('mail_pass'));
            $client->select((string) (Settings::get('mail_folder') ?: 'INBOX'));
            $messages = [];
            foreach (array_reverse(array_slice($client->search('ALL'), -$limit)) as $uid) {
                $raw = $client->fetch($uid);
                if ($raw === null) {
                    continue;
                }
                $message = MimeMessage::parse($raw);
                $html = (string) $message->html();
                $text = (string) $message->text();

                $links = [];
                if ($html !== '' && preg_match_all('#<a\s[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER) > 0) {
                    foreach ($m as [, $href, $label]) {
                        $links[] = ['text' => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''), 'url' => html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
                    }
                }
                if ($text !== '' && preg_match_all('#https?://[^\s<>"\']+#i', $text, $m) > 0) {
                    foreach ($m[0] as $url) {
                        $links[] = ['text' => '', 'url' => rtrim($url, '.,);')];
                    }
                }
                $seen = [];
                $links = array_values(array_filter($links, static function (array $link) use (&$seen): bool {
                    if (!ListingParser::isWebUrl($link['url']) || isset($seen[$link['url']])) {
                        return false;
                    }
                    $seen[$link['url']] = true;
                    return true;
                }));

                $plain = $text !== '' ? $text : html_entity_decode(strip_tags((string) preg_replace('#<(style|script)\b.*?</\1>#is', '', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $messages[] = [
                    'date' => $message->header('Date'),
                    'from' => $message->from(),
                    'subject' => $message->subject(),
                    'snippet' => mb_substr(trim((string) preg_replace('/\s+/u', ' ', $plain)), 0, 600),
                    'links' => array_slice($links, 0, 40),
                ];
            }
            return $messages;
        } finally {
            $client->logout();
        }
    }

    /**
     * Okunmamış bildirimleri işler ve okundu olarak işaretler.
     *
     * @return array{emails: int, found: int, added: int, errors: list<string>}
     */
    public static function run(float $deadline, int $maxEmails = 40): array
    {
        $result = ['emails' => 0, 'found' => 0, 'added' => 0, 'errors' => []];
        if (!self::configured()) {
            $result['errors'][] = 'Posta kutusu bilgileri girilmemiş.';
            return $result;
        }

        $client = self::client();
        try {
            $client->connect();
            $client->login((string) Settings::get('mail_user'), Settings::secret('mail_pass'));
            $client->select((string) (Settings::get('mail_folder') ?: 'INBOX'));

            $extractor = new NotificationExtractor();
            foreach (array_slice($client->search('UNSEEN'), 0, $maxEmails) as $uid) {
                if (microtime(true) > $deadline) {
                    break;
                }
                $raw = $client->fetch($uid);
                if ($raw !== null) {
                    try {
                        $processed = self::process($raw, $extractor, true);
                        $result['found'] += count($processed['listings']);
                        $result['added'] += $processed['added'];
                    } catch (\Throwable $e) {
                        error_log('Bildirim e-postası işlenemedi: ' . $e->getMessage());
                        $result['errors'][] = 'Bir e-posta işlenemedi: ' . $e->getMessage();
                    }
                }
                $client->addFlags([$uid], '\\Seen');
                $result['emails']++;
            }

            $days = (int) (Settings::get('mail_delete_days') ?? '7');
            if ($days > 0) {
                $old = $client->search('SEEN BEFORE ' . ImapClient::date(time() - $days * 86400));
                if ($old !== []) {
                    $client->addFlags($old, '\\Deleted');
                    $client->expunge();
                }
            }
        } catch (\Throwable $e) {
            $result['errors'][] = $e->getMessage();
        } finally {
            $client->logout();
        }
        return $result;
    }

    /**
     * Tek bir e-postayı çözer; $save ise ilanları Radar'a kaydeder.
     *
     * @return array{from: string, subject: string, allowed: bool, listings: list<array<string, mixed>>, added: int}
     */
    public static function process(string $raw, ?NotificationExtractor $extractor = null, bool $save = false): array
    {
        $message = MimeMessage::parse($raw);
        $from = $message->from();
        $domain = substr((string) strrchr($from, '@'), 1);
        $allowed = false;
        foreach (self::allowedSenders() as $sender) {
            if ($from === $sender || $domain === $sender || str_ends_with($domain, '.' . $sender)) {
                $allowed = true;
                break;
            }
        }

        $listings = [];
        $added = 0;
        if ($allowed) {
            foreach (($extractor ?? new NotificationExtractor())->extract($message) as $item) {
                $data = ListingParser::toListing($item['url'], $item['title'], $item['text']);
                $listings[] = $data;
                if ($save) {
                    $saved = Listings::upsert(self::NAME, $data);
                    $added += $saved['created'] ? 1 : 0;
                }
            }
        }
        return ['from' => $from, 'subject' => $message->subject(), 'allowed' => $allowed, 'listings' => $listings, 'added' => $added];
    }
}
