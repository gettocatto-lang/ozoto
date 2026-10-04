<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\App;
use Ozoto\Radar\Collector;

/** Plesk "Zamanlanmış Görevler → URL getir" için: /cron/radar?anahtar=... */
final class CronController
{
    public function radar(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        $key = (string) ($_GET['anahtar'] ?? '');
        if (!App::installed() || !hash_equals(Collector::cronKey(), $key)) {
            http_response_code(403);
            echo "Yetkisiz.\n";
            return;
        }
        @set_time_limit(90);
        ignore_user_abort(true);
        $summary = Collector::run(45);
        echo implode("\n", $summary['messages']), "\n";
    }
}
