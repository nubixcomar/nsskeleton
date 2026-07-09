<?php

declare(strict_types=1);

/**
 * Utilidades de base de datos (CLI).
 *   php system/console/db.php doctor    → diagnóstico de conexión/versión/extensiones/migraciones
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;
use App\Services\DbDoctor;

Env::load(PROJECT_PATH . '/.env');

$mode = $argv[1] ?? 'doctor';

if ($mode === 'doctor') {
    $checks = DbDoctor::checks();
    echo "Diagnóstico de base de datos (db:doctor)\n" . str_repeat('-', 52) . "\n";
    foreach ($checks as $c) {
        $mark = $c['ok'] ? 'OK ' : (($c['severity'] === 'error') ? 'ERR' : (($c['severity'] === 'warn') ? 'WRN' : 'i  '));
        echo sprintf("[%s] %-40s %s\n", $mark, $c['name'], $c['detail']);
    }
    $ok = DbDoctor::ok($checks);
    echo str_repeat('-', 52) . "\n";
    echo $ok ? "Base de datos OK.\n" : "Hay chequeos en ERROR — revisá la conexión/config.\n";
    exit($ok ? 0 : 1);
}

fwrite(STDERR, "Modo desconocido: {$mode}. Usá 'doctor'.\n");
exit(1);
