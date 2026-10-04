<?php

declare(strict_types=1);

namespace Ozoto;

final class App
{
    /** @var array<string, mixed> */
    private static array $config = [];

    public static function boot(): void
    {
        $config = require BASE_PATH . '/config/app.php';
        $local = BASE_PATH . '/config/local.php';
        $installed = is_file($local);
        if ($installed) {
            $config = array_replace_recursive($config, require $local);
        }
        $config['installed'] = $installed;
        self::$config = $config;

        date_default_timezone_set((string) ($config['timezone'] ?? 'Europe/Istanbul'));
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        error_reporting(E_ALL);
        ini_set('display_errors', $config['debug'] ? '1' : '0');
        ini_set('log_errors', '1');
        $logDir = BASE_PATH . '/storage/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            ini_set('error_log', $logDir . '/php-error.log');
        }

        set_exception_handler(static function (\Throwable $e): void {
            error_log((string) $e);
            if (!headers_sent()) {
                http_response_code(500);
            }
            if (self::config('debug')) {
                echo '<pre>' . e((string) $e) . '</pre>';
                return;
            }
            try {
                echo View::render('errors/generic', ['status' => 500, 'title' => 'Bir hata oluştu', 'noindex' => true]);
            } catch (\Throwable) {
                echo 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        });
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function installed(): bool
    {
        return (bool) (self::$config['installed'] ?? false);
    }
}
