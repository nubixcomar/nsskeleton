<?php

declare(strict_types=1);

/**
 * Generador del snapshot de propiedad del core (core-lock.json).
 *
 *   php system/console/core-manifest.php          # genera core-lock.json
 *   php system/console/core-manifest.php --check   # verifica drift, no escribe (exit 1 si difiere)
 *
 * Lee las reglas de `core-manifest.json` (core_paths / app_paths / exclude),
 * recorre el arbol del proyecto y produce el set de archivos CORE con su sha256.
 * Un archivo es core si:  matchea core_paths  &&  !app_paths  &&  !exclude.
 *
 * Lo corre `/release` antes de empaquetar el zip de core. Tambien lo usa el
 * actualizador (core-update) para comparar instalado vs nuevo y detectar drift.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

$root = dirname(__DIR__, 2); // .../skeleton
foreach ($argv as $arg) {
    // --root=<dir>: otra raíz (lo usan los tests con un árbol temporal).
    if (str_starts_with($arg, '--root=') && $arg !== '--root=') {
        $root = rtrim(str_replace('\\', '/', substr($arg, 7)), '/');
    }
}
$manifestFile = $root . '/core-manifest.json';
$lockFile     = $root . '/core-lock.json';
$check        = in_array('--check', $argv, true);

// ── --check en un PROYECTO DERIVADO (PL-002/F6) ──────────────────────────────
// El lock describe el core que el proyecto RECIBIÓ: se verifican solo esos archivos (faltantes o
// modificados = drift). Lo que el proyecto agrega (routes.app.php, overrides, migraciones y
// módulos propios, tests/app/…) no es del core y no cuenta, igual que para el actualizador.
if ($check && is_file($root . '/nsproject.json')) {
    $lockData = is_file($lockFile) ? json_decode((string) file_get_contents($lockFile), true) : null;
    $lockFiles = is_array($lockData) && is_array($lockData['files'] ?? null) ? $lockData['files'] : null;
    if ($lockFiles === null) {
        fwrite(STDERR, "DRIFT (modo proyecto): no hay un core-lock.json válido en {$root}\n");
        exit(1);
    }
    $issues = [];
    foreach ($lockFiles as $path => $hash) {
        $abs = $root . '/' . $path;
        if (!is_file($abs)) {
            $issues[] = "[falta]      {$path}";
        } elseif (hash_file('sha256', $abs) !== $hash) {
            $issues[] = "[modificado] {$path}";
        }
    }
    $n = count($lockFiles);
    if ($issues === []) {
        echo "core-lock.json OK — modo proyecto: {$n} archivos del core verificados (los archivos nuevos son del proyecto).\n";
        exit(0);
    }
    fwrite(STDERR, "DRIFT (modo proyecto): " . count($issues) . " de {$n} archivos del core difieren del lock:\n");
    foreach (array_slice($issues, 0, 20) as $i) {
        fwrite(STDERR, "  {$i}\n");
    }
    if (count($issues) > 20) {
        fwrite(STDERR, '  … y ' . (count($issues) - 20) . " más\n");
    }
    fwrite(STDERR, "Si editaste archivos del core, movelos a un override (ver docs/CORE-UPDATE.md §2).\n");
    exit(1);
}

// En un proyecto derivado el lock describe el core RECIBIDO: regenerarlo metería las
// ediciones de la app como si fueran del core y el actualizador dejaría de verlas.
// (--check es solo lectura y sí se permite.)
if (!$check && is_file($root . '/nsproject.json') && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Este árbol es un proyecto derivado (nsproject.json): core-lock.json lo genera el\n"
        . "master del skeleton al publicar. No se regenera acá (usá --force si sabés lo que hacés).\n");
    exit(1);
}

if (!is_file($manifestFile)) {
    fwrite(STDERR, "No existe core-manifest.json en {$root}\n");
    exit(1);
}

/** @var array<string,mixed> $manifest */
$manifest = json_decode((string) file_get_contents($manifestFile), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "core-manifest.json invalido.\n");
    exit(1);
}

$coreVersion = (string) ($manifest['core_version'] ?? trim((string) @file_get_contents($root . '/VERSION')));
$corePaths   = array_map('strval', $manifest['core_paths'] ?? []);
$appPaths    = array_map('strval', $manifest['app_paths'] ?? []);
$exclude     = array_map('strval', $manifest['exclude'] ?? []);

/** Normaliza a ruta relativa con barras '/'. */
$rel = static function (string $abs) use ($root): string {
    $p = str_replace('\\', '/', $abs);
    $r = str_replace('\\', '/', $root);
    return ltrim(substr($p, strlen($r)), '/');
};

/** ¿La ruta relativa matchea alguno de los patrones? */
$matches = static function (string $path, array $patterns): bool {
    foreach ($patterns as $p) {
        if ($p === '') {
            continue;
        }
        if (str_starts_with($p, '*.')) {            // sufijo de extension
            if (str_ends_with($path, substr($p, 1))) {
                return true;
            }
        } elseif (str_ends_with($p, '/')) {          // prefijo de directorio
            if ($path === rtrim($p, '/') || str_starts_with($path, $p)) {
                return true;
            }
        } elseif ($path === $p) {                     // ruta exacta
            return true;
        }
    }
    return false;
};

/** ¿Algún segmento de la ruta está en exclude (cortar ramas al recorrer)? */
$isExcludedDir = static function (string $relDir) use ($exclude, $matches): bool {
    return $matches(rtrim($relDir, '/') . '/', $exclude);
};

// Recorrido del árbol, podando directorios excluidos.
$files = [];
$walk = static function (string $dir) use (&$walk, &$files, $root, $rel, $isExcludedDir): void {
    $entries = @scandir($dir);
    if ($entries === false) {
        return;
    }
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') {
            continue;
        }
        $abs = $dir . '/' . $e;
        $relPath = $rel($abs);
        if (is_dir($abs)) {
            if ($isExcludedDir($relPath)) {
                continue;
            }
            $walk($abs);
        } else {
            $files[] = $relPath;
        }
    }
};
$walk($root);
sort($files);

// Clasificación.
$lock = [];
$appCount = 0;
$untracked = 0;
foreach ($files as $f) {
    if ($matches($f, $exclude)) {
        continue;
    }
    $isCore = $matches($f, $corePaths) && !$matches($f, $appPaths);
    if (!$isCore) {
        if ($matches($f, $appPaths)) {
            $appCount++;
        } else {
            $untracked++;
        }
        continue;
    }
    $hash = hash_file('sha256', $root . '/' . $f);
    if ($hash !== false) {
        $lock[$f] = $hash;
    }
}
ksort($lock);

$payload = [
    'core_version' => $coreVersion,
    'count'        => count($lock),
    'files'        => $lock,
];
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

if ($check) {
    $current = is_file($lockFile) ? (string) file_get_contents($lockFile) : '';
    if (trim($current) === trim($json)) {
        echo "core-lock.json OK — sin drift ({$payload['count']} archivos core).\n";
        exit(0);
    }
    fwrite(STDERR, "DRIFT: core-lock.json no coincide con el arbol. Corre 'php system/console/core-manifest.php' para regenerarlo.\n");
    // Qué difiere (agregados / quitados / modificados respecto del lock actual).
    $old = json_decode($current, true);
    $oldFiles = is_array($old) && is_array($old['files'] ?? null) ? $old['files'] : [];
    $diff = [];
    foreach (array_diff_key($lock, $oldFiles) as $p => $_) {
        $diff[] = "[nuevo]      {$p}";
    }
    foreach (array_diff_key($oldFiles, $lock) as $p => $_) {
        $diff[] = "[quitado]    {$p}";
    }
    foreach (array_intersect_key($lock, $oldFiles) as $p => $h) {
        if ($oldFiles[$p] !== $h) {
            $diff[] = "[modificado] {$p}";
        }
    }
    foreach (array_slice($diff, 0, 20) as $d) {
        fwrite(STDERR, "  {$d}\n");
    }
    if (count($diff) > 20) {
        fwrite(STDERR, '  … y ' . (count($diff) - 20) . " más\n");
    }
    exit(1);
}

file_put_contents($lockFile, $json);
echo "core-lock.json generado: {$payload['count']} archivos core";
echo " (app: {$appCount}, sin clasificar/ignorados: {$untracked}).\n";
echo "Version de core: {$coreVersion}\n";
