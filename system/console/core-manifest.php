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
$manifestFile = $root . '/core-manifest.json';
$lockFile     = $root . '/core-lock.json';
$check        = in_array('--check', $argv, true);

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
    exit(1);
}

file_put_contents($lockFile, $json);
echo "core-lock.json generado: {$payload['count']} archivos core";
echo " (app: {$appCount}, sin clasificar/ignorados: {$untracked}).\n";
echo "Version de core: {$coreVersion}\n";
