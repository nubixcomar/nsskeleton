<?php

declare(strict_types=1);

/**
 * Deploy del proyecto (CLI). Dry-run por defecto; ejecución real con --run.
 *   php system/console/deploy.php ftp            → lista qué subiría por FTP
 *   php system/console/deploy.php ftp --run       → sube por FTP (usa .env)
 *   php system/console/deploy.php git             → muestra los comandos git
 *   php system/console/deploy.php git --run       → ejecuta commit + push
 *
 * Seguridad: sin --run no realiza ninguna acción saliente.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;
use App\Services\Deployer;

Env::load(PROJECT_PATH . '/.env');

$mode = $argv[1] ?? 'ftp';
$run = in_array('--run', $argv, true);
$cfg = Deployer::config();

if ($mode === 'git') {
    echo "Comandos git:\n";
    foreach (Deployer::gitCommands() as $cmd) {
        echo "  {$cmd}\n";
    }
    if (!$run) {
        echo "\n(dry-run; usá --run para ejecutar commit + push)\n";
        exit(0);
    }
    if (!is_dir(PROJECT_PATH . '/.git')) {
        fwrite(STDERR, "No hay repo git inicializado.\n");
        exit(1);
    }
    chdir(PROJECT_PATH);
    foreach (Deployer::gitCommands() as $cmd) {
        echo "> {$cmd}\n";
        passthru($cmd);
    }
    exit(0);
}

// FTP
$files = Deployer::filesToDeploy();
echo count($files) . " archivo(s) a subir por FTP a " . ($cfg['ftp_host'] ?: '(sin host)') . $cfg['ftp_path'] . "\n";

if (!$run) {
    echo "(dry-run; usá --run para subir realmente)\n";
    exit(0);
}
if (!Deployer::ftpConfigured()) {
    fwrite(STDERR, "FTP no configurado en el .env (FTP_HOST/FTP_USER).\n");
    exit(1);
}

$res = Deployer::ftpDeploy(static fn (string $f) => print("  ↑ {$f}\n"));
echo $res['ok'] ? "OK: {$res['uploaded']} archivo(s) subido(s).\n" : "Error: {$res['error']}\n";
exit($res['ok'] ? 0 : 1);
