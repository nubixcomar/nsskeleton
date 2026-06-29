<?php

declare(strict_types=1);

/**
 * Worker de la cola de jobs.
 *   php system/console/queue.php work [--max=N]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';
Core\Env::load(PROJECT_PATH . '/.env');

$args = array_slice($argv, 1);
$command = $args[0] ?? 'work';

$max = 25;
foreach ($args as $a) {
    if (preg_match('/^--max=(\d+)$/', $a, $m)) {
        $max = (int) $m[1];
    }
}

if ($command !== 'work') {
    fwrite(STDERR, "Uso: php system/console/queue.php work [--max=N]\n");
    exit(1);
}

$r = App\Services\JobQueue::work($max);
echo "Cola: {$r['processed']} procesados — {$r['done']} ok, {$r['retried']} reintentar, {$r['failed']} fallidos.\n";
