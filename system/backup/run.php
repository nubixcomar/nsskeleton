<?php

declare(strict_types=1);

/**
 * Backup completo (CLI): base de datos + archivos, con limpieza por retención.
 * Ideal para programar como tarea (ver el cronmaster o el cron del SO).
 *
 *   php system/backup/run.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));

require BASE_PATH . '/app/Core/autoload.php';

use Core\Env;
use App\Services\Backup;

Env::load(PROJECT_PATH . '/.env');

$db = Backup::createDatabaseBackup();
echo $db['ok'] ? "DB:    {$db['file']} ({$db['size']} bytes)\n" : "DB:    ERROR — {$db['error']}\n";

$files = Backup::createFilesBackup();
echo $files['ok'] ? "Files: {$files['file']} ({$files['size']} bytes)\n" : "Files: ERROR — {$files['error']}\n";

$deleted = Backup::cleanup((int) Env::get('BACKUP_RETENTION_DAYS', 30));
echo "Retención: {$deleted} backup(s) antiguo(s) eliminado(s).\n";
