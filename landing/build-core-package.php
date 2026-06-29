<?php

declare(strict_types=1);

/**
 * Genera el paquete de CORE de nsSkeleton (lo que consume el actualizador).
 *   php landing/build-core-package.php [--out=<dir>] [--regen]
 *
 * Empaqueta EXACTAMENTE los archivos de `core-lock.json` (el core, sin lo de la
 * app) + el propio `core-lock.json` en la raíz del zip. El actualizador
 * (`system/console/core-update.php --source=<zip>`) lo aplica a un proyecto.
 *
 *   --out=<dir>   Carpeta de salida (default: landing/downloads).
 *   --regen       Regenera core-lock.json antes de empaquetar (recomendado en /release).
 *
 * A diferencia de `build-download.php` (zip COMPLETO: core + app + demo), este
 * trae SOLO el core, para un update limpio que no pisa lo del proyecto.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Falta la extensión ZipArchive.\n");
    exit(1);
}

$projectPath = str_replace('\\', '/', dirname(__DIR__));

// Args.
$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

// Regenerar el lock si se pide (que refleje el árbol exacto que se publica).
if (isset($opts['regen'])) {
    $gen = $projectPath . '/system/console/core-manifest.php';
    if (is_file($gen)) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($gen));
    }
}

$lockFile = $projectPath . '/core-lock.json';
if (!is_file($lockFile)) {
    fwrite(STDERR, "No existe core-lock.json. Generalo con: php system/console/core-manifest.php\n");
    exit(1);
}
$lock = json_decode((string) file_get_contents($lockFile), true);
$files = is_array($lock) && isset($lock['files']) && is_array($lock['files']) ? $lock['files'] : [];
$version = (string) ($lock['core_version'] ?? trim((string) @file_get_contents($projectPath . '/VERSION')) ?: '1.0.0');

if ($files === []) {
    fwrite(STDERR, "El lock no tiene archivos. Regeneralo.\n");
    exit(1);
}

$outDir = isset($opts['out']) ? rtrim((string) $opts['out'], '/\\') : __DIR__ . '/downloads';
@mkdir($outDir, 0775, true);
$outFile = $outDir . '/nsSkeleton-core-' . $version . '.zip';

$zip = new ZipArchive();
if ($zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "No se pudo crear el ZIP: {$outFile}\n");
    exit(1);
}

// Archivos del core (los del lock; quedan en la raíz del zip, sin prefijo de versión,
// para que core-lock.json esté en la raíz al extraer).
$count = 0;
$missing = [];
foreach (array_keys($files) as $rel) {
    $abs = $projectPath . '/' . $rel;
    if (!is_file($abs)) {
        $missing[] = $rel;
        continue;
    }
    $zip->addFile($abs, $rel);
    $count++;
}

// El propio lock (no está dentro del lock: se excluye a sí mismo).
$zip->addFile($lockFile, 'core-lock.json');
$count++;

$zip->close();

printf("OK: %s\n  core %s · %d archivos · %d KB\n", $outFile, $version, $count, (int) round(filesize($outFile) / 1024));
if ($missing !== []) {
    fwrite(STDERR, "AVISO: " . count($missing) . " archivo(s) del lock no existen (lock desfasado). Regenerá con --regen.\n");
    foreach (array_slice($missing, 0, 10) as $m) {
        fwrite(STDERR, "  - {$m}\n");
    }
    exit(1);
}
