<?php

declare(strict_types=1);

/**
 * Genera el paquete descargable de nsSkeleton en landing/downloads/.
 *   php landing/build-download.php [--out=<dir>]
 *
 * Excluye secretos (.env), runtime de storage (incluidas sesiones y la base del merge),
 * vendor/node_modules/.git y la propia carpeta de descargas. Empaqueta todo bajo una
 * carpeta raíz versionada. --out: carpeta de salida (default landing/downloads).
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
$version = trim(@file_get_contents($projectPath . '/VERSION') ?: '') ?: '1.0.0';

$outDir = __DIR__ . '/downloads';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--out=') && $arg !== '--out=') {
        $outDir = rtrim(substr($arg, 6), '/\\');
    }
}
@mkdir($outDir, 0775, true);
$outFile = $outDir . '/nsSkeleton-' . $version . '.zip';
$prefix = 'nsSkeleton-' . $version . '/';

$zip = new ZipArchive();
if ($zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "No se pudo crear el ZIP.\n");
    exit(1);
}

$excludes = ['/.git/', '/vendor/', '/node_modules/', '/landing/downloads/', '/tools/bin/'];
// planning/ (raíz) es el roadmap/briefs/planes del PROPIO skeleton: un proyecto nuevo arranca vacío
// (/roadmap init). Solo la raíz: agentic/templates/planning/ (el scaffold) sí se empaqueta.
$rootExcludes = ['planning/'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectPath, FilesystemIterator::SKIP_DOTS)
);

$count = 0;
foreach ($iterator as $entry) {
    if (!$entry->isFile()) {
        continue;
    }
    $abs = str_replace('\\', '/', $entry->getPathname());

    // Nunca empaquetar el .env (secretos); sí el .env.example.
    if (basename($abs) === '.env') {
        continue;
    }
    // Excluir carpetas no deseadas.
    foreach ($excludes as $ex) {
        if (str_contains($abs, $ex)) {
            continue 2;
        }
    }
    // Excluir contenido de runtime de storage, pero conservar los .gitkeep. Incluye las sesiones
    // (datos de sesión de admins: nunca deben viajar) y la base del merge del actualizador
    // (es del master; el proyecto captura la suya). Mismo criterio que ProjectCreator.
    if (preg_match('#/system/storage/(backups|cache|uploads|logs|sessions)/#', $abs) && basename($abs) !== '.gitkeep') {
        continue;
    }
    if (str_contains($abs, '/system/storage/core-base/')) {
        continue;
    }

    $rel = ltrim(str_replace($projectPath, '', $abs), '/');
    foreach ($rootExcludes as $rx) {
        if (str_starts_with($rel, $rx)) {
            continue 2;
        }
    }
    $zip->addFile($entry->getPathname(), $prefix . $rel);
    $count++;
}

$zip->close();

printf("OK: %s\n  %d archivos · %d KB\n", $outFile, $count, (int) round(filesize($outFile) / 1024));
