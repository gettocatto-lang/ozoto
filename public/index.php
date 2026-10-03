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
use Ozoto\Controller\AdminController;
use Ozoto\Controller\ApplicationController;
use Ozoto\Controller\HomeController;
use Ozoto\Controller\InstallController;
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

$router->get('/kurulum', [InstallController::class, 'form']);
$router->post('/kurulum', [InstallController::class, 'install']);

$router->dispatch(request_method(), request_path());
