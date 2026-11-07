<?php

declare(strict_types=1);

/**
 * Manuales remotos (config/remote_docs.php).
 *   php system/console/docs-sync.php [sync] [<clave>] [--dry-run]   # baja si cambió
 *   php system/console/docs-sync.php status                         # estado local, sin red
 *
 * Códigos de salida: 0 ok · 1 algún error · 2 (status) hay manuales vencidos o faltantes.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';
Core\Env::load(PROJECT_PATH . '/.env');

use App\Services\RemoteDocs;

$args = array_values(array_filter(array_slice($argv, 1), static fn ($a) => !str_starts_with($a, '--')));
$dryRun = in_array('--dry-run', $argv, true);
$command = $args[0] ?? 'sync';
$key = null;
if (!in_array($command, ['sync', 'status'], true)) {
    $key = $command;      // `docs-sync.php <clave>` = `sync <clave>`
    $command = 'sync';
} else {
    $key = $args[1] ?? null;
}

$rel = static fn (?string $p): string => $p === null ? '-' : str_replace('\\', '/', substr($p, strlen(PROJECT_PATH) + 1));

if ($command === 'status') {
    $stale = false;
    foreach (RemoteDocs::status() as $k => $s) {
        $flag = !$s['enabled'] ? 'apagado' : ($s['stale'] ? 'VENCIDO' : 'al día');
        $stale = $stale || ($s['enabled'] && $s['stale']);
        $age = $s['age_hours'] === null ? 'nunca' : "hace {$s['age_hours']} h";
        printf("%-24s %-8s v%-18s chequeado %-14s %s\n", $k, $flag, $s['version'] ?? '?', $age, $rel($s['path']));
    }
    exit($stale ? 2 : 0);
}

$labels = ['new' => 'NUEVO', 'updated' => 'ACTUALIZADO', 'unchanged' => 'sin cambios', 'error' => 'ERROR', 'disabled' => 'apagado'];
$failed = false;
foreach (RemoteDocs::sync($key, $dryRun) as $k => $r) {
    $failed = $failed || $r['status'] === 'error';
    $line = sprintf('%-24s %-12s', $k, $labels[$r['status']] ?? $r['status']);
    if ($r['version'] !== null) {
        $line .= ' v' . $r['version'];
    }
    if ($r['status'] === 'updated' && $r['prev_version'] !== null) {
        $line .= " (antes v{$r['prev_version']})";
    }
    if ($r['error'] !== null) {
        $line .= ' — ' . $r['error'];
    }
    if ($r['path'] !== null && $r['status'] !== 'error') {
        $line .= ' → ' . $rel($r['path']);
    }
    echo $line . ($dryRun ? ' [dry-run]' : '') . "\n";
}
exit($failed ? 1 : 0);
