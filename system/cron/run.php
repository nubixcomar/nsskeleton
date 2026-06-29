<?php

declare(strict_types=1);

/**
 * Despachador de tareas programadas (CLE / "cronmaster").
 *
 * Pensado para ser invocado UNA VEZ POR MINUTO por el cron del sistema operativo
 * (Linux) o el Programador de tareas (Windows). Él decide qué tareas internas
 * están vencidas y las ejecuta. Ver system/cron/README.md.
 *
 *   * * * * *  php /ruta/skeleton/system/cron/run.php   (cron de Linux)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));

require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;
use App\Services\CronRunner;

Env::load(PROJECT_PATH . '/.env');

$cfg = require BASE_PATH . '/config/app.php';
date_default_timezone_set($cfg['timezone'] ?? 'UTC');

$now = new DateTimeImmutable('now');
$ran = CronRunner::runDue($now);

$stamp = '[' . $now->format('Y-m-d H:i') . ']';
if ($ran === []) {
    echo "{$stamp} sin tareas vencidas\n";
} else {
    foreach ($ran as $r) {
        echo "{$stamp} {$r['name']}: {$r['status']} (exit {$r['code']})\n";
    }
}
