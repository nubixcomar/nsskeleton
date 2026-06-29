<?php

declare(strict_types=1);

/**
 * Carga (o elimina) datos de ejemplo.
 *   php system/database/seed-demo.php          → crea datos demo (idempotente)
 *   php system/database/seed-demo.php --undo    → los elimina
 *
 * Admins demo: editor@demo.local / viewer@demo.local (password: demo1234).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;
use App\Services\DemoSeeder;

Env::load(PROJECT_PATH . '/.env');

$fmt = static fn (array $r): string => implode(', ', array_map(static fn ($k, $v) => "{$v} {$k}", array_keys($r), array_values($r)));

if (in_array('--undo', $argv, true)) {
    $r = DemoSeeder::undo();
    echo "Datos demo eliminados: " . $fmt($r) . ".\n";
    exit(0);
}

$r = DemoSeeder::seed();
echo "Datos demo cargados: " . $fmt($r) . ".\n";
echo "  Admins demo: editor / viewer / cliente1 / vendedor1 · password: demo1234\n";
echo "  (Para eliminarlos: php system/database/seed-demo.php --undo)\n";
