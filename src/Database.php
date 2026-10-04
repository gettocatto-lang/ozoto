<?php

declare(strict_types=1);

namespace Ozoto;

use PDO;

final class Database
{
    /** Şemaya tablo eklendikçe artırılır; kurulu sitelerde eksik tablolar ilk bağlantıda oluşturulur. */
    public const SCHEMA_VERSION = 2;

    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $config = (array) App::config('db');
            self::$pdo = self::connect($config);
            self::upgrade(self::$pdo, ($config['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql');
        }
        return self::$pdo;
    }

    private static function upgrade(PDO $pdo, string $driver): void
    {
        $flag = BASE_PATH . '/storage/schema-version';
        if (is_file($flag) && (int) file_get_contents($flag) >= self::SCHEMA_VERSION) {
            return;
        }
        self::migrate($pdo, $driver);
        self::markMigrated();
    }

    public static function markMigrated(): void
    {
        @file_put_contents(BASE_PATH . '/storage/schema-version', (string) self::SCHEMA_VERSION);
    }

    /** @param array<string, mixed> $c */
    public static function connect(array $c): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (($c['driver'] ?? 'mysql') === 'sqlite') {
            $pdo = new PDO('sqlite:' . self::sqlitePath((string) ($c['path'] ?? '')), null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['host'] ?? 'localhost',
            (int) ($c['port'] ?? 3306),
            $c['name'] ?? ''
        );
        return new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), $options);
    }

    /** Göreli SQLite yolu proje köküne göre çözülür (Windows: C:\..., Linux: /...). */
    public static function sqlitePath(string $path): string
    {
        if ($path === '') {
            $path = 'storage/database.sqlite';
        }
        if (preg_match('#^([A-Za-z]:)?[\\\\/]#', $path) === 1) {
            return $path;
        }
        return BASE_PATH . '/' . $path;
    }

    public static function migrate(PDO $pdo, string $driver): void
    {
        $sql = (string) file_get_contents(BASE_PATH . '/database/schema.' . $driver . '.sql');
        foreach (explode(';', $sql) as $statement) {
            if (trim(preg_replace('/^\s*--.*$/m', '', $statement) ?? '') !== '') {
                $pdo->exec($statement);
            }
        }
    }
}
