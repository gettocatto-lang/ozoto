<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Database;
use Ozoto\Radar\Sources\EmailNotifications;
use Ozoto\Radar\Sources\SearchEngine;
use Ozoto\Settings;

/**
 * Zamanlanmış görev: kaynakları tarar, yeni ilanları kaydeder, bekleyenleri değerler.
 * Plesk'te "Zamanlanmış Görevler" ile CLI'dan (bin/radar.php) veya URL'den (/cron/radar) çalışır.
 */
final class Collector
{
    /** @return array{found: int, added: int, valued: int, messages: list<string>} */
    public static function run(int $seconds, bool $force = false): array
    {
        $deadline = microtime(true) + $seconds;
        $summary = ['found' => 0, 'added' => 0, 'valued' => 0, 'messages' => []];

        if (Settings::get('mail_enabled') === '1') {
            $runId = self::startRun(EmailNotifications::NAME);
            $result = EmailNotifications::run($deadline - 10);
            $summary['found'] += $result['found'];
            $summary['added'] += $result['added'];
            $message = sprintf('%d e-posta, %d ilan, %d yeni', $result['emails'], $result['found'], $result['added']);
            if ($result['errors'] !== []) {
                $message .= ' · Hatalar: ' . implode(' | ', array_slice($result['errors'], 0, 3));
            }
            $summary['messages'][] = 'E-posta: ' . $message;
            self::finishRun($runId, $result['found'], $result['added'], $message);
        }

        // Arama motoru ücretli kredi harcadığı için ayarlanan aralıktan sık çalışmaz.
        $interval = max(15, (int) (Settings::get('search_interval') ?: 180)) * 60;
        $searchDue = time() - (int) strtotime((string) (Settings::get('search_last_run') ?? '2000-01-01')) >= $interval;
        if (Settings::get('search_enabled') === '1' && ($searchDue || $force)) {
            Settings::set('search_last_run', now());
            $runId = self::startRun(SearchEngine::NAME);
            $result = SearchEngine::run($deadline - 5);
            $summary['found'] += $result['found'];
            $summary['added'] += $result['added'];
            $message = sprintf('%d sorgu, %d ilan, %d yeni', $result['queries'], $result['found'], $result['added']);
            if ($result['errors'] !== []) {
                $message .= ' · Hatalar: ' . implode(' | ', array_slice($result['errors'], 0, 3));
            }
            $summary['messages'][] = 'Arama motoru: ' . $message;
            self::finishRun($runId, $result['found'], $result['added'], $message);
        }

        $summary['valued'] = Listings::valuatePending(200, $deadline);
        $summary['messages'][] = 'Değerlenen: ' . $summary['valued'];

        if (Telegram::enabled()) {
            try {
                $summary['messages'][] = 'Telegram: ' . Telegram::dispatch($deadline + 15) . ' bildirim';
            } catch (\Throwable $e) {
                $summary['messages'][] = 'Telegram hatası: ' . $e->getMessage();
                error_log('Telegram: ' . $e->getMessage());
            }
        }
        Settings::set('last_collect_at', now());
        return $summary;
    }

    /** @return list<array<string, mixed>> */
    public static function recentRuns(int $limit = 10): array
    {
        return Database::connection()->query('SELECT * FROM radar_runs ORDER BY id DESC LIMIT ' . $limit)->fetchAll();
    }

    public static function cronKey(): string
    {
        return substr(hash_hmac('sha256', 'radar-cron', (string) config('app_key', 'ozoto')), 0, 32);
    }

    private static function startRun(string $source): int
    {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO radar_runs (source, started_at) VALUES (?, ?)')->execute([$source, now()]);
        $pdo->prepare('DELETE FROM radar_runs WHERE started_at < ?')->execute([date('Y-m-d H:i:s', time() - 30 * 86400)]);
        return (int) $pdo->lastInsertId();
    }

    private static function finishRun(int $id, int $found, int $added, string $message): void
    {
        Database::connection()->prepare('UPDATE radar_runs SET finished_at = ?, found = ?, added = ?, message = ? WHERE id = ?')
            ->execute([now(), $found, $added, mb_substr($message, 0, 2000), $id]);
    }
}
