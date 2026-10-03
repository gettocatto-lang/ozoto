<?php

declare(strict_types=1);

namespace Ozoto;

/** Panelden değiştirilebilen ayarlar (settings tablosu). */
final class Settings
{
    /** @var array<string, ?string>|null */
    private static ?array $cache = null;

    public static function get(string $name, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::connection()->query('SELECT name, value FROM settings') as $row) {
                self::$cache[(string) $row['name']] = $row['value'] === null ? null : (string) $row['value'];
            }
        }
        return self::$cache[$name] ?? $default;
    }

    public static function set(string $name, ?string $value): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM settings WHERE name = ?')->execute([$name]);
        $pdo->prepare('INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?)')->execute([$name, $value, now()]);
        if (self::$cache !== null) {
            self::$cache[$name] = $value;
        }
    }
}
