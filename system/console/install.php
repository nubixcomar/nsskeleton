<?php

declare(strict_types=1);

/**
 * Instalador (CLI, parte mecánica).
 *   php system/console/install.php --answers=respuestas.json [--dry-run] [--force]
 *
 * --dry-run : imprime el .env que se generaría, sin escribir nada.
 * --force   : sobrescribe un .env existente.
 *
 * La parte interactiva (preguntar) la maneja el skill `installer` / comando /instalar.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';

use App\Services\Installer;

$args = $argv;
$dryRun = in_array('--dry-run', $args, true);
$force = in_array('--force', $args, true);

$answers = [];
foreach ($args as $a) {
    if (str_starts_with($a, '--answers=')) {
        $file = substr($a, strlen('--answers='));
        $json = @file_get_contents($file);
        $answers = $json !== false ? (json_decode($json, true) ?: []) : [];
    }
}

$env = Installer::buildEnv($answers);

if ($dryRun) {
    echo $env;
    exit(0);
}

$envPath = PROJECT_PATH . '/.env';
if (file_exists($envPath) && !$force) {
    fwrite(STDERR, "Ya existe un .env. Usá --force para sobrescribir.\n");
    exit(1);
}

file_put_contents($envPath, $env);
echo "Escrito .env.\n\nResumen de acciones:\n";
foreach (Installer::summary($answers) as $step) {
    echo "  - {$step}\n";
}
echo "\nProximos pasos:\n";
echo "  1) php system/database/migrate.php   (crea las tablas)\n";
echo "  2) php system/database/seed.php       (admin inicial)\n";
echo "  3) php system/database/seed-demo.php  (OPCIONAL: datos demo para ver el dashboard con contenido)\n";
echo "     (para quitarlos luego: php system/database/seed-demo.php --undo)\n";
