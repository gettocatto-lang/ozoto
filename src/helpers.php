<?php

declare(strict_types=1);

use Ozoto\App;
use Ozoto\Csrf;
use Ozoto\View;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    return App::config($key, $default);
}

function site_url(string $path = '/'): string
{
    return rtrim((string) config('site.url'), '/') . '/' . ltrim($path, '/');
}

/** Statik dosya adresi; dosya her deploy'da değiştiğinde tarayıcı önbelleği yenilenir. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return '/assets/' . $path . '?v=' . $version;
}

function is_https(): bool
{
    $https = (string) ($_SERVER['HTTPS'] ?? '');
    if ($https !== '' && strtolower($https) !== 'off') {
        return true;
    }
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $path = is_string($path) ? rawurldecode($path) : '/';
    return $path === '' ? '/' : $path;
}

function redirect(string $to, int $status = 303): never
{
    header('Location: ' . $to, true, $status);
    exit;
}

/** @param array<string, mixed> $data */
function view(string $template, array $data = [], ?string $layout = 'layout', int $status = 200): void
{
    http_response_code($status);
    echo View::render($template, $data, $layout);
}

function abort(int $status): never
{
    $titles = [403 => 'Erişim engellendi', 404 => 'Sayfa bulunamadı', 405 => 'Geçersiz istek'];
    view($status === 404 ? 'errors/404' : 'errors/generic', [
        'status' => $status,
        'title' => $titles[$status] ?? 'Bir hata oluştu',
        'noindex' => true,
    ], 'layout', $status);
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Ziyaretçi IP'sinin geri çevrilemez özeti (KVKK: ham IP saklanmaz). */
function client_ip_hash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return hash_hmac('sha256', $ip, (string) config('app_key', 'ozoto'));
}

function digits(string $value): string
{
    return (string) preg_replace('/\D+/', '', $value);
}

function format_tl(?int $amount): string
{
    return $amount === null ? '—' : number_format($amount, 0, ',', '.') . ' ₺';
}

function format_number(?int $value): string
{
    return $value === null ? '' : number_format($value, 0, ',', '.');
}

function format_date(string $datetime): string
{
    $ts = strtotime($datetime);
    return $ts === false ? $datetime : date('d.m.Y H:i', $ts);
}

function selected(mixed $current, mixed $option): string
{
    return (string) $current === (string) $option ? ' selected' : '';
}

/** Satır içi SVG ikon (harici dosya/CDN gerektirmez). */
function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'bolt' => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
        'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/>',
        'car' => '<path d="M3 13l2-6h14l2 6v4H3z"/><path d="M6 17v2M18 17v2M3 13h18"/><circle cx="7.5" cy="15" r=".5"/><circle cx="16.5" cy="15" r=".5"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'radar' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><path d="M12 12l6-6"/><circle cx="12" cy="12" r="1"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'tag' => '<path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'whatsapp' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0zm0 0a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

function logo_mark(): string
{
    return '<svg class="logo-mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
        . '<rect width="32" height="32" rx="8" fill="#f5a524"/>'
        . '<path d="M7.5 21a8.5 8.5 0 1 1 17 0" fill="none" stroke="#0b1220" stroke-width="3" stroke-linecap="round"/>'
        . '<path d="M16 21l5.5-6.5" stroke="#0b1220" stroke-width="3" stroke-linecap="round"/>'
        . '<circle cx="16" cy="21" r="2.4" fill="#0b1220"/></svg>';
}
