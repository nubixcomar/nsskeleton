<?php

declare(strict_types=1);

/**
 * Actualizador de core de nsSkeleton (Fase 4).
 *
 *   php system/console/core-update.php --source=<dir|zip>          # DRY-RUN (muestra el plan)
 *   php system/console/core-update.php --source=<dir|zip> --apply  # aplica (con backup)
 *   php system/console/core-update.php --rollback=<backupDir>      # revierte un update aplicado
 *
 * El paquete <source> debe contener los archivos del core nuevo y su core-lock.json
 * en la raíz (un zip de core o una carpeta extraída). Compara el lock instalado, el
 * árbol local y el lock nuevo, y solo toca archivos del core (lo de la app no se toca).
 * Si la app editó un archivo del core, el nuevo se deja como `.new` (conflicto a resolver).
 *
 * Decisión de distribución (usuario): zip + manifiesto. La descarga desde URL/landing
 * se integra en Fase 6 (/release publica el zip de core); acá se consume un source local.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));            // .../system
define('PROJECT_PATH', dirname(BASE_PATH));       // .../skeleton
require BASE_PATH . '/app/Core/autoload.php';
\Core\Env::load(PROJECT_PATH . '/.env');

use App\Services\CoreUpdater;

// ── Args ─────────────────────────────────────────────────────────────────────
$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

if (isset($opts['help']) || $argv === [$argv[0]]) {
    fwrite(STDOUT, <<<TXT
Actualizador de core de nsSkeleton

  --source=<dir|zip>   Paquete del core nuevo (carpeta o .zip con core-lock.json en la raiz)
  --url=<url>          Descarga el zip de core desde una URL (p. ej. del landing)
  --apply              Aplica los cambios (sin esto: DRY-RUN, solo muestra el plan)
  --rollback=<dir>     Revierte un update usando su carpeta de backup
  --yes                No pedir confirmacion interactiva al aplicar
  --help               Esta ayuda

Ejemplos:
  php system/console/core-update.php --source=../nsSkeleton-core-1.13.0.zip
  php system/console/core-update.php --url=https://misitio/downloads/nsSkeleton-core-1.13.0.zip
  php system/console/core-update.php --source=../core-1.13.0 --apply

TXT);
    exit(0);
}

$installRoot = PROJECT_PATH;

// ── Rollback ─────────────────────────────────────────────────────────────────
if (isset($opts['rollback'])) {
    $dir = (string) $opts['rollback'];
    if (!is_dir($dir)) {
        fwrite(STDERR, "No existe el backupDir: {$dir}\n");
        exit(1);
    }
    echo "Revirtiendo update desde: {$dir}\n";
    $r = CoreUpdater::rollback($dir, $installRoot);
    // Restaurar también los metadatos respaldados (core-lock.json / core-manifest.json / VERSION).
    foreach (['core-lock.json', 'core-manifest.json', 'VERSION'] as $meta) {
        $bak = $dir . '/meta/' . $meta;
        if (is_file($bak)) {
            @copy($bak, $installRoot . '/' . $meta);
        }
    }
    echo "Restaurados: {$r['restored']} archivo(s).\n";
    foreach ($r['errors'] as $e) {
        fwrite(STDERR, "  ! {$e}\n");
    }
    exit($r['errors'] === [] ? 0 : 1);
}

// ── Descargar el paquete si vino por URL ─────────────────────────────────────
$downloaded = null;
if (isset($opts['url'])) {
    $url = (string) $opts['url'];
    if (!function_exists('curl_init')) {
        fwrite(STDERR, "cURL no disponible para --url; descargá el zip y usá --source.\n");
        exit(1);
    }
    @mkdir(BASE_PATH . '/storage/cache', 0775, true);
    $downloaded = BASE_PATH . '/storage/cache/core-pkg-' . date('Ymd_His') . '.zip';
    echo "Descargando {$url} …\n";
    $fh = fopen($downloaded, 'wb');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_FAILONERROR => true,
    ]);
    $ok = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fh);
    if ($ok === false) {
        @unlink($downloaded);
        fwrite(STDERR, "Fallo la descarga: {$err}\n");
        exit(1);
    }
    $opts['source'] = $downloaded;
}

// ── Resolver source ──────────────────────────────────────────────────────────
if (!isset($opts['source'])) {
    fwrite(STDERR, "Falta --source=<dir|zip> (o --url). Usá --help.\n");
    exit(1);
}
$source = (string) $opts['source'];
$cleanup = null;

if (is_file($source) && preg_match('/\.zip$/i', $source)) {
    if (!class_exists('ZipArchive')) {
        fwrite(STDERR, "ZipArchive no disponible; extraé el zip y pasá la carpeta con --source.\n");
        exit(1);
    }
    $tmp = BASE_PATH . '/storage/cache/core-update-src-' . date('Ymd_His');
    @mkdir($tmp, 0775, true);
    $zip = new ZipArchive();
    if ($zip->open($source) !== true) {
        fwrite(STDERR, "No se pudo abrir el zip: {$source}\n");
        exit(1);
    }
    $zip->extractTo($tmp);
    $zip->close();
    $sourceRoot = $tmp;
    $cleanup = $tmp;
} elseif (is_dir($source)) {
    $sourceRoot = rtrim($source, '/\\');
} else {
    fwrite(STDERR, "--source no es ni carpeta ni zip: {$source}\n");
    exit(1);
}

// El core-lock.json puede estar en la raíz del source o un nivel abajo (zip con carpeta).
$newLockFile = $sourceRoot . '/core-lock.json';
if (!is_file($newLockFile)) {
    $candidates = glob($sourceRoot . '/*/core-lock.json') ?: [];
    if (count($candidates) === 1) {
        $newLockFile = $candidates[0];
        $sourceRoot = dirname($newLockFile);
    }
}
if (!is_file($newLockFile)) {
    fwrite(STDERR, "El paquete no tiene core-lock.json en la raíz: {$sourceRoot}\n");
    exit(1);
}

// ── Versiones ────────────────────────────────────────────────────────────────
$oldLock = CoreUpdater::loadLockFiles($installRoot . '/core-lock.json');
$newLock = CoreUpdater::loadLockFiles($newLockFile);
$oldVer = trim((string) @file_get_contents($installRoot . '/VERSION'));
$newVer = trim((string) @file_get_contents($sourceRoot . '/VERSION'));

echo "nsSkeleton core update\n";
echo "  instalado: {$oldVer}  (" . count($oldLock) . " archivos core)\n";
echo "  nuevo:     {$newVer}  (" . count($newLock) . " archivos core)\n";
echo str_repeat('-', 56) . "\n";

// ── Plan ─────────────────────────────────────────────────────────────────────
$plan = CoreUpdater::plan($oldLock, $newLock, $installRoot, $sourceRoot);
$sum = CoreUpdater::summarize($plan);

$order = [
    CoreUpdater::ADD => 'agregar',
    CoreUpdater::UPDATE => 'actualizar',
    CoreUpdater::DELETE => 'eliminar',
    CoreUpdater::CONFLICT => 'CONFLICTO (.new)',
    CoreUpdater::CONFLICT_ADD => 'CONFLICTO nuevo (.new)',
    CoreUpdater::DELETE_MODIFIED => 'eliminado pero editado (se conserva)',
    CoreUpdater::SKIP => 'sin cambios',
];
foreach ($order as $action => $label) {
    if (!empty($sum[$action])) {
        printf("  %-38s %4d\n", $label, $sum[$action]);
    }
}

// Detalle de conflictos y borrados (lo que el humano debe mirar).
$flag = static fn (array $p, string $a): array => array_values(array_filter($p, fn ($e) => $e['action'] === $a));
foreach ([CoreUpdater::CONFLICT, CoreUpdater::CONFLICT_ADD, CoreUpdater::DELETE_MODIFIED] as $a) {
    foreach ($flag($plan, $a) as $e) {
        echo "    [{$e['action']}] {$e['path']} — {$e['reason']}\n";
    }
}

$willChange = array_sum(array_map(fn ($a) => $sum[$a] ?? 0, CoreUpdater::MUTATING));
if ($willChange === 0) {
    echo "\nNada para actualizar. El core ya está al día.\n";
    if ($cleanup) {
        self_rrmdir($cleanup);
    }
    exit(0);
}

// ── Dry-run vs apply ─────────────────────────────────────────────────────────
if (!isset($opts['apply'])) {
    echo "\nDRY-RUN. Repetí con --apply para aplicar (se hace backup automático).\n";
    if (!empty($sum[CoreUpdater::CONFLICT]) || !empty($sum[CoreUpdater::CONFLICT_ADD])) {
        echo "Hay conflictos: el core nuevo se dejará como archivo `.new` junto al tuyo para que mergees.\n";
    }
    if ($cleanup) {
        self_rrmdir($cleanup);
    }
    exit(0);
}

if (!isset($opts['yes'])) {
    fwrite(STDOUT, "\n¿Aplicar la actualización a {$newVer}? Se hace backup. [y/N]: ");
    $line = trim((string) fgets(STDIN));
    if (strtolower($line) !== 'y') {
        echo "Cancelado.\n";
        if ($cleanup) {
            self_rrmdir($cleanup);
        }
        exit(0);
    }
}

$backupDir = BASE_PATH . '/storage/backups/core-update-' . date('Ymd_His');
@mkdir($backupDir . '/meta', 0775, true);
// Respaldar metadatos antes de pisarlos.
foreach (['core-lock.json', 'core-manifest.json', 'VERSION'] as $meta) {
    if (is_file($installRoot . '/' . $meta)) {
        @copy($installRoot . '/' . $meta, $backupDir . '/meta/' . $meta);
    }
}

echo "\nAplicando… (backup en " . str_replace(PROJECT_PATH . '/', '', $backupDir) . ")\n";
$res = CoreUpdater::apply($plan, $installRoot, $sourceRoot, $backupDir);

// Actualizar metadatos del core en la instalación.
@copy($newLockFile, $installRoot . '/core-lock.json');
if (is_file($sourceRoot . '/core-manifest.json')) {
    @copy($sourceRoot . '/core-manifest.json', $installRoot . '/core-manifest.json');
}
if ($newVer !== '') {
    file_put_contents($installRoot . '/VERSION', $newVer);
}

echo "  archivos aplicados: " . count($res['applied']) . "\n";
if ($res['conflicts'] !== []) {
    echo "  CONFLICTOS (revisá los .new y mergeá):\n";
    foreach ($res['conflicts'] as $c) {
        echo "    - {$c}.new\n";
    }
}
foreach ($res['errors'] as $e) {
    fwrite(STDERR, "  ! {$e}\n");
}

// Migraciones del core (best-effort).
echo "  migraciones del core: ";
try {
    $done = \App\Services\Migrator::migrateCore();
    echo $done === [] ? "ninguna pendiente\n" : (count($done) . " aplicada(s)\n");
} catch (\Throwable $ex) {
    echo "omitidas (" . $ex->getMessage() . ")\n";
}

echo "\nListo. Versión: {$newVer}.\n";
echo "Rollback: php system/console/core-update.php --rollback=" . str_replace(PROJECT_PATH . '/', '', $backupDir) . "\n";

if ($cleanup) {
    self_rrmdir($cleanup);
}
exit($res['errors'] === [] ? 0 : 1);

/** Borra recursivamente un directorio temporal. */
function self_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $e) {
        if ($e === '.' || $e === '..') {
            continue;
        }
        $p = $dir . '/' . $e;
        is_dir($p) ? self_rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}
