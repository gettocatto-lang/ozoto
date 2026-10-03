<?php

declare(strict_types=1);

namespace Ozoto;

final class RateLimiter
{
    public static function hits(string $bucket, string $key, int $seconds): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND key_hash = ? AND created_at >= ?'
        );
        $stmt->execute([$bucket, $key, date('Y-m-d H:i:s', time() - $seconds)]);
        return (int) $stmt->fetchColumn();
    }

    public static function record(string $bucket, string $key): void
    {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO rate_limits (bucket, key_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$bucket, $key, now()]);
        if (random_int(1, 50) === 1) {
            $pdo->prepare('DELETE FROM rate_limits WHERE created_at < ?')
                ->execute([date('Y-m-d H:i:s', time() - 86400)]);
        }
    }

    /** Sınır aşılmadıysa denemeyi kaydeder ve true döner. */
    public static function attempt(string $bucket, string $key, int $max, int $seconds): bool
    {
        if (self::hits($bucket, $key, $seconds) >= $max) {
            return false;
        }
        self::record($bucket, $key);
        return true;
    }
}
