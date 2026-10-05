<?php

declare(strict_types=1);

// Yerel geliştirme sunucusunda (php -S) statik dosyaları doğrudan sun.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Ozoto\App;
use Ozoto\Controller\AccountController;
use Ozoto\Controller\AdminController;
use Ozoto\Controller\ApplicationController;
use Ozoto\Controller\CronController;
use Ozoto\Controller\ExtensionController;
use Ozoto\Controller\HomeController;
use Ozoto\Controller\InstallController;
use Ozoto\Controller\RadarController;
use Ozoto\Router;

App::boot();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; "
    . "form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'");

$router = new Router();

$router->get('/', [HomeController::class, 'index']);
$router->get('/kvkk', [HomeController::class, 'kvkk']);
$router->get('/sitemap.xml', [HomeController::class, 'sitemap']);

$router->get('/aracimi-hemen-sat', [ApplicationController::class, 'create']);
$router->post('/aracimi-hemen-sat', [ApplicationController::class, 'store']);
$router->get('/basvuru-alindi', [ApplicationController::class, 'thanks']);

$router->get('/yonetim', [AdminController::class, 'index']);
$router->get('/yonetim/giris', [AdminController::class, 'loginForm']);
$router->post('/yonetim/giris', [AdminController::class, 'login']);
$router->post('/yonetim/cikis', [AdminController::class, 'logout']);
$router->get('/yonetim/basvuru/{id}', [AdminController::class, 'show']);
$router->post('/yonetim/basvuru/{id}', [AdminController::class, 'update']);
$router->get('/yonetim/foto/{id}', [AdminController::class, 'photo']);
$router->get('/yonetim/hesap', [AccountController::class, 'show']);
$router->post('/yonetim/hesap/site', [AccountController::class, 'saveSite']);
$router->post('/yonetim/hesap/sifre', [AccountController::class, 'changePassword']);

$router->get('/yonetim/radar', [RadarController::class, 'index']);
$router->get('/yonetim/radar/ekle', [RadarController::class, 'create']);
$router->post('/yonetim/radar/ekle', [RadarController::class, 'store']);
$router->get('/yonetim/radar/ayarlar', [RadarController::class, 'settings']);
$router->post('/yonetim/radar/ayarlar', [RadarController::class, 'saveSettings']);
$router->post('/yonetim/radar/tara', [RadarController::class, 'runNow']);
$router->post('/yonetim/radar/eposta', [RadarController::class, 'saveMailSettings']);
$router->post('/yonetim/radar/eposta-test', [RadarController::class, 'testMail']);
$router->post('/yonetim/radar/eposta-onizle', [RadarController::class, 'previewMail']);
$router->get('/yonetim/radar/eposta-kutusu', [RadarController::class, 'mailbox']);
$router->post('/yonetim/radar/telegram', [RadarController::class, 'saveTelegram']);
$router->post('/yonetim/radar/telegram-bagla', [RadarController::class, 'linkTelegram']);
$router->post('/yonetim/radar/telegram-test', [RadarController::class, 'testTelegram']);
$router->post('/yonetim/radar/eklenti-anahtar', [RadarController::class, 'extensionToken']);
$router->post('/yonetim/radar/kaynak-testi', [RadarController::class, 'diagnose']);
$router->post('/yonetim/radar/kaynak-incele', [RadarController::class, 'probe']);
$router->get('/yonetim/radar/eklenti.zip', [RadarController::class, 'extensionDownload']);
$router->get('/api/radar/eklenti/durum', [ExtensionController::class, 'status']);
$router->post('/api/radar/eklenti', [ExtensionController::class, 'ingest']);
$router->post('/yonetim/radar/arama-kaydet', [RadarController::class, 'saveSearch']);
$router->post('/yonetim/radar/arama-sil/{id}', [RadarController::class, 'deleteSearch']);
$router->get('/yonetim/radar/{id}', [RadarController::class, 'show']);
$router->post('/yonetim/radar/{id}', [RadarController::class, 'update']);
$router->post('/yonetim/radar/{id}/sil', [RadarController::class, 'destroy']);
$router->post('/yonetim/radar/{id}/degerle', [RadarController::class, 'revaluate']);
$router->get('/cron/radar', [CronController::class, 'radar']);

$router->get('/kurulum', [InstallController::class, 'form']);
$router->post('/kurulum', [InstallController::class, 'install']);

$router->dispatch(request_method(), request_path());
