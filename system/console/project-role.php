<?php

declare(strict_types=1);

/**
 * Rol de este árbol: master del skeleton o proyecto derivado.
 *   php system/console/project-role.php            → imprime "master" o "project" (+ motivo)
 *   php system/console/project-role.php --quiet    → solo el rol (para scripts / /release)
 *   php system/console/project-role.php --mark[="Nombre"]
 *        Marca un derivado creado a mano (clon/copia) escribiendo nsproject.json, para que
 *        /release no intente generar paquetes de core desde él.
 *
 * Código de salida: 0 = master · 10 = project.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';

use App\Services\ProjectCreator as PC;

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/s', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

if (isset($opts['mark'])) {
    $file = PROJECT_PATH . '/' . PC::MARKER;
    if (is_file($file)) {
        echo "Ya está marcado como proyecto ({$file}).\n";
        exit(10);
    }
    $name = is_string($opts['mark']) && $opts['mark'] !== '' ? $opts['mark'] : basename(PROJECT_PATH);
    file_put_contents($file, PC::marker([
        'name' => $name,
        'slug' => PC::slug($name),
        'created_at' => date('c'),
        'core_version' => trim((string) @file_get_contents(PROJECT_PATH . '/VERSION')),
    ]));
    echo "Marcado como proyecto derivado: {$file}\n";
    exit(10);
}

$r = PC::role(PROJECT_PATH);
echo isset($opts['quiet']) ? $r['role'] . "\n" : "{$r['role']} ({$r['reason']})\n";
exit($r['role'] === 'master' ? 0 : 10);
