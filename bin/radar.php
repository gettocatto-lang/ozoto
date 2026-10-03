<?php

declare(strict_types=1);

// Kelepir Radar toplayıcısı (komut satırı). Plesk → Zamanlanmış Görevler → "PHP betiği çalıştır":
//   site\bin\radar.php           (varsayılan 300 saniye)
//   site\bin\radar.php 120       (en fazla 120 saniye)

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Ozoto\App;
use Ozoto\Radar\Collector;

App::boot();
if (!App::installed()) {
    fwrite(STDERR, "Önce /kurulum tamamlanmalı.\n");
    exit(1);
}

$seconds = isset($argv[1]) ? max(10, (int) $argv[1]) : 300;
$summary = Collector::run($seconds);
echo '[' . date('Y-m-d H:i:s') . '] ' . implode(' · ', $summary['messages']), "\n";
