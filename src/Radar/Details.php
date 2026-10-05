<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Database;

/** İlan detay sayfasından okunan veriler ve kârlılık analizi (radar_details). */
final class Details
{
    /** @return array{data: array<string, mixed>, analysis: ?array<string, mixed>, raw: ?array<string, mixed>, updated_at: string}|null */
    public static function find(int $listingId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM radar_details WHERE listing_id = ?');
        $stmt->execute([$listingId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $decode = static fn (?string $json): ?array => is_array($v = json_decode((string) $json, true)) ? $v : null;
        return [
            'data' => $decode($row['data']) ?? [],
            'analysis' => $decode($row['analysis']),
            'raw' => $decode($row['raw']),
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function data(int $listingId): ?array
    {
        return self::find($listingId)['data'] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $raw ayrıştırıcıyı geliştirmek için eklentinin gönderdiği ham çiftler/bölümler
     */
    public static function save(int $listingId, array $data, array $raw): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM radar_details WHERE listing_id = ?')->execute([$listingId]);
        $pdo->prepare('INSERT INTO radar_details (listing_id, data, raw, updated_at) VALUES (?, ?, ?, ?)')
            ->execute([$listingId, self::encode($data), self::encode($raw), now()]);
    }

    /** @param array<string, mixed> $analysis */
    public static function saveAnalysis(int $listingId, array $analysis): void
    {
        Database::connection()->prepare('UPDATE radar_details SET analysis = ?, updated_at = ? WHERE listing_id = ?')
            ->execute([self::encode($analysis), now(), $listingId]);
    }

    /** @param array<string, mixed> $value */
    private static function encode(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
