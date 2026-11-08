<?php

declare(strict_types=1);

/**
 * Runner de migraciones (CLI).
 *   php system/database/migrate.php            → aplica pendientes (up)
 *   php system/database/migrate.php status     → estado de cada migración
 *   php system/database/migrate.php rollback [N] [--core|--app]
 *                                               → revierte las últimas N (default 1), en orden
 *                                                 inverso de aplicación entre core y app
 *                                                 (--core / --app: solo ese directorio)
 *   php system/database/migrate.php fresh       → revierte todo (core + app) y vuelve a migrar
 *
 * Cada .sql puede incluir una sección de reversa tras una línea `-- @DOWN`.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));

require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;
use Core\Database;
use App\Services\Migrator;

Env::load(PROJECT_PATH . '/.env');

$cfg = require BASE_PATH . '/config/database.php';

// Crear la base si no existe (conexión sin dbname).
try {
    $server = new PDO(
        sprintf('%s:host=%s;port=%s;charset=%s', $cfg['driver'], $cfg['host'], $cfg['port'], $cfg['charset']),
        $cfg['user'],
        $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $server->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        str_replace('`', '', $cfg['name'])
    ));
} catch (PDOException $e) {
    fwrite(STDERR, "No se pudo conectar/crear la base: {$e->getMessage()}\n");
    exit(1);
}

$cmd = $argv[1] ?? 'up';

try {
    switch ($cmd) {
        case 'status':
            foreach (Migrator::status() as $m) {
                $mark = $m['applied'] ? '[x]' : '[ ]';
                $rev = $m['reversible'] ? '' : '  (sin rollback)';
                echo "  {$mark} {$m['migration']}{$rev}\n";
            }
            break;

        case 'rollback':
            // rollback [N] [--core|--app]: flags aparte de los posicionales; N = primer número (default 1).
            $rest = array_slice($argv, 2);
            $flags = array_values(array_filter($rest, static fn (string $a): bool => str_starts_with($a, '--')));
            $nums = array_values(array_filter($rest, static fn (string $a): bool => ctype_digit($a)));
            $n = $nums !== [] ? max(1, (int) $nums[0]) : 1;
            $dir = in_array('--core', $flags, true) ? Migrator::dir()
                : (in_array('--app', $flags, true) ? Migrator::appDir() : null); // null = core + app
            $done = Migrator::rollback($n, $dir);
            echo $done === []
                ? "Nada para revertir.\n"
                : "Revertidas:\n  - " . implode("\n  - ", $done) . "\n";
            break;

        case 'fresh':
            // Revierte TODO lo aplicado (core + app, en orden inverso) y reaplica core + app.
            $count = count(array_filter(Migrator::status(), static fn (array $m): bool => $m['applied']));
            Migrator::rollback($count);
            $done = Migrator::migrate();
            echo "Fresh: " . count($done) . " migración(es) aplicadas desde cero.\n";
            break;

        case 'up':
        default:
            $done = Migrator::migrate();
            foreach ($done as $name) {
                echo "  ✓ aplicada: {$name}\n";
            }
            echo $done === [] ? "Sin migraciones pendientes.\n" : "Listo: " . count($done) . " migración(es) aplicada(s).\n";
            break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
