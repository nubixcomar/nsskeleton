<?php

declare(strict_types=1);

/**
 * Actualizador de core de nsSkeleton.
 *
 *   php system/console/core-update.php --source=<dir|zip>          # DRY-RUN (muestra el plan)
 *   php system/console/core-update.php --source=<dir|zip> --apply  # aplica (con backup)
 *   php system/console/core-update.php --latest [--apply]          # usa la última de latest.json
 *   php system/console/core-update.php --check                     # solo avisa si hay versión nueva
 *   php system/console/core-update.php --rollback=<backupDir>      # revierte un update aplicado
 *
 * El paquete <source> debe contener los archivos del core nuevo y su core-lock.json
 * en la raíz (un zip de core o una carpeta extraída). Compara el lock instalado, el
 * árbol local y el lock nuevo, y solo toca archivos del core (lo de la app no se toca).
 *
 * Conflictos (la app editó un archivo del core): desde v1.20 se intenta un merge 3-way
 * con la base guardada en system/storage/core-base/. Si combina sin choques, queda
 * mergeado; si no, se deja `.new` (core nuevo) + `.merge` (propuesta con marcadores).
 *
 * La lógica vive en App\Services\CoreUpdate (la misma que usa /admin/core-update).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));            // .../system
define('PROJECT_PATH', dirname(BASE_PATH));       // .../skeleton
require BASE_PATH . '/app/Core/autoload.php';
\Core\Env::load(PROJECT_PATH . '/.env');

use App\Services\CoreRelease;
use App\Services\CoreUpdate;
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
  --sha256=<hash>      Verifica el sha256 del zip descargado con --url
  --latest[=<url>]     Usa la ultima release de latest.json (default: CORE_UPDATE_URL)
  --check[=<url>]      Solo informa si hay version nueva (exit 3 = hay update)
  --apply              Aplica los cambios (sin esto: DRY-RUN, solo muestra el plan)
  --no-merge           No intenta merge 3-way: los conflictos quedan solo como .new
  --seed-base[=<src>]  Completa la base del merge (sin valor: desde el arbol local;
                       con <dir|zip>: desde el paquete de la version INSTALADA;
                       =remote: lo baja de latest.json)
  --rollback=<dir>     Revierte un update usando su carpeta de backup
  --yes                No pedir confirmacion interactiva al aplicar
  --help               Esta ayuda

Ejemplos:
  php system/console/core-update.php --check
  php system/console/core-update.php --latest
  php system/console/core-update.php --source=../nsSkeleton-core-1.20.0.zip --apply
  php system/console/core-update.php --url=https://misitio/downloads/nsSkeleton-core-1.20.0.zip --sha256=<hash>

TXT);
    exit(0);
}

$cu = CoreUpdate::forInstall();
$installRoot = $cu->installRoot();
$rel = static fn (string $p): string => str_replace(str_replace('\\', '/', PROJECT_PATH) . '/', '', str_replace('\\', '/', $p));

/** URL de latest.json: valor del flag o la configurada. */
$latestUrl = static function ($flag): string {
    $u = is_string($flag) && $flag !== '' ? $flag : CoreRelease::configuredUrl();
    if ($u === '') {
        fwrite(STDERR, "No hay URL de latest.json. Pasala (--latest=<url>) o configurá CORE_UPDATE_URL en .env.\n");
        exit(1);
    }
    return $u;
};

// ── Rollback ─────────────────────────────────────────────────────────────────
if (isset($opts['rollback'])) {
    $dir = (string) $opts['rollback'];
    if (!is_dir($dir) && is_dir($installRoot . '/' . $dir)) {
        $dir = $installRoot . '/' . $dir;
    }
    echo "Revirtiendo update desde: {$dir}\n";
    $r = $cu->rollback($dir);
    echo "Restaurados: {$r['restored']} archivo(s).\n";
    foreach ($r['errors'] as $e) {
        fwrite(STDERR, "  ! {$e}\n");
    }
    exit($r['ok'] ? 0 : 1);
}

// ── Check ────────────────────────────────────────────────────────────────────
if (isset($opts['check'])) {
    $url = $latestUrl($opts['check']);
    $c = CoreRelease::check($url, $cu->installedVersion());
    if (!$c['ok']) {
        fwrite(STDERR, $c['error'] . "\n");
        exit(1);
    }
    echo "Instalado: {$c['installed']} · Última publicada: {$c['latest']}\n";
    if ($c['insecure']) {
        echo "AVISO: latest.json se sirve por http (sin TLS). Usá https.\n";
    }
    if ($c['update_available']) {
        echo "Hay una versión nueva. Probá: php system/console/core-update.php --latest\n";
        exit(3);
    }
    echo "El core está al día.\n";
    exit(0);
}

/** Descarga (con sha opcional) a cache/ y devuelve la ruta del zip. */
$download = static function (string $url, ?string $sha) use ($cu): string {
    $dest = $cu->cacheDir() . '/core-pkg-' . date('Ymd_His') . '.zip';
    echo "Descargando {$url} …\n";
    $d = CoreRelease::download($url, $dest, $sha);
    if (!$d['ok']) {
        fwrite(STDERR, $d['error'] . "\n");
        exit(1);
    }
    echo $sha ? "  sha256 verificado.\n" : "  sha256: {$d['sha256']} (sin verificar: pasá --sha256)\n";
    return $d['path'];
};

// ── Seed de la base ──────────────────────────────────────────────────────────
if (isset($opts['seed-base'])) {
    $val = $opts['seed-base'];
    $cap = $cu->captureBase();
    echo "Base desde el árbol local: {$cap['stored']} guardado(s), {$cap['present']} ya estaban.\n";
    $src = null;
    if ($val === 'remote') {
        $url = $latestUrl(true);
        $idx = CoreRelease::fetchIndex($url);
        $r = $idx['ok'] ? CoreRelease::release($idx['index'], $url, $cu->installedVersion()) : null;
        if ($r === null) {
            fwrite(STDERR, ($idx['error'] ?: 'latest.json no publica la versión instalada (' . $cu->installedVersion() . ').') . "\n");
            exit(1);
        }
        $src = $download($r['url'], $r['sha256']);
    } elseif (is_string($val) && $val !== '') {
        $src = $val;
    }
    if ($src !== null) {
        $p = $cu->prepareSource($src);
        if (!$p['ok']) {
            fwrite(STDERR, $p['error'] . "\n");
            exit(1);
        }
        $s = $cu->seedBaseFrom($p['root']);
        echo "Base desde el paquete {$p['version']}: {$s['stored']} guardado(s).\n";
        if ($p['cleanup']) {
            CoreUpdate::rrmdir($p['cleanup']);
        }
    }
    $st = $cu->baseStatus();
    $edited = $cu->editedWithoutBase();
    echo "Base: {$st['valid']}/{$st['total']} archivos con original guardado.\n";
    if ($edited !== []) {
        echo "Editados por la app SIN original (no se podrán mergear): " . count($edited) . "\n";
        foreach (array_slice($edited, 0, 15) as $e) {
            echo "  - {$e}\n";
        }
        echo "Completalos con --seed-base=<zip de la versión instalada> o --seed-base=remote.\n";
    }
    exit(0);
}

// ── Resolver el paquete ──────────────────────────────────────────────────────
$downloaded = null;
if (isset($opts['latest'])) {
    $url = $latestUrl($opts['latest']);
    $c = CoreRelease::check($url, $cu->installedVersion());
    if (!$c['ok']) {
        fwrite(STDERR, $c['error'] . "\n");
        exit(1);
    }
    echo "Instalado: {$c['installed']} · Última publicada: {$c['latest']}\n";
    if (!$c['update_available']) {
        echo "El core está al día.\n";
        exit(0);
    }
    $downloaded = $download($c['release']['url'], $c['release']['sha256']);
    $opts['source'] = $downloaded;
} elseif (isset($opts['url'])) {
    $sha = isset($opts['sha256']) && is_string($opts['sha256']) ? $opts['sha256'] : null;
    $downloaded = $download((string) $opts['url'], $sha);
    $opts['source'] = $downloaded;
}

if (!isset($opts['source'])) {
    fwrite(STDERR, "Falta --source=<dir|zip> (o --url / --latest). Usá --help.\n");
    exit(1);
}

$pkg = $cu->prepareSource((string) $opts['source']);
if (!$pkg['ok']) {
    fwrite(STDERR, $pkg['error'] . "\n");
    exit(1);
}
// Limpieza de temporales: árbol extraído y zip descargado (en cualquier salida).
$cleanup = static function () use ($pkg, $downloaded): void {
    if ($pkg['cleanup']) {
        CoreUpdate::rrmdir($pkg['cleanup']);
    }
    if ($downloaded !== null) {
        @unlink($downloaded);
    }
};

$merge = !isset($opts['no-merge']);
try {
    $merge = $merge && (bool) \Core\Config::get('core_update', 'merge', true);
} catch (\Throwable) {
}

// ── Plan ─────────────────────────────────────────────────────────────────────
$info = $cu->inspect($pkg['root'], $pkg['lock_file'], $merge);
$sum = $info['summary'];

echo "nsSkeleton core update\n";
echo "  instalado: {$info['installed']}  ({$info['old_count']} archivos core)\n";
echo "  nuevo:     {$info['new']}  ({$info['new_count']} archivos core)\n";
echo str_repeat('-', 56) . "\n";

$order = [
    CoreUpdater::ADD => 'agregar',
    CoreUpdater::UPDATE => 'actualizar',
    CoreUpdater::DELETE => 'eliminar',
    CoreUpdater::CONFLICT => 'CONFLICTO (editado por la app)',
    CoreUpdater::CONFLICT_ADD => 'CONFLICTO nuevo (.new)',
    CoreUpdater::DELETE_MODIFIED => 'eliminado pero editado (se conserva)',
    CoreUpdater::SKIP => 'sin cambios',
];
foreach ($order as $action => $label) {
    if (!empty($sum[$action])) {
        printf("  %-38s %4d\n", $label, $sum[$action]);
    }
}

$mergeLabel = [
    'clean'    => 'merge 3-way limpio',
    'conflict' => 'merge con choques → .merge + .new',
    'no_base'  => 'sin base → .new',
    'binary'   => 'binario → .new',
];
foreach ($info['plan'] as $e) {
    if (!in_array($e['action'], [CoreUpdater::CONFLICT, CoreUpdater::CONFLICT_ADD, CoreUpdater::DELETE_MODIFIED], true)) {
        continue;
    }
    $extra = '';
    if ($e['action'] === CoreUpdater::CONFLICT) {
        $m = $info['merge'][$e['path']] ?? null;
        $extra = $m ? ' [' . ($mergeLabel[$m['status']] ?? $m['status']) . ($m['conflicts'] ? " ({$m['conflicts']})" : '') . ']'
                    : ($merge ? '' : ' [.new]');
    }
    echo "    [{$e['action']}] {$e['path']} — {$e['reason']}{$extra}\n";
}

if ($info['unwritable'] !== []) {
    echo "\nSin permiso de escritura (" . count($info['unwritable']) . "):\n";
    foreach (array_slice($info['unwritable'], 0, 15) as $p) {
        echo "    - {$p}\n";
    }
}

if ($info['changes'] === 0) {
    echo "\nNada para actualizar. El core ya está al día.\n";
    $cleanup();
    exit(0);
}

// ── Dry-run vs apply ─────────────────────────────────────────────────────────
if (!isset($opts['apply'])) {
    echo "\nDRY-RUN. Repetí con --apply para aplicar (se hace backup automático).\n";
    $cleanup();
    exit(0);
}

if ($info['unwritable'] !== []) {
    fwrite(STDERR, "\nNo se puede aplicar: corregí los permisos de escritura primero.\n");
    $cleanup();
    exit(1);
}

if (!isset($opts['yes'])) {
    fwrite(STDOUT, "\n¿Aplicar la actualización a {$info['new']}? Se hace backup. [y/N]: ");
    $line = trim((string) fgets(STDIN));
    if (strtolower($line) !== 'y') {
        echo "Cancelado.\n";
        $cleanup();
        exit(0);
    }
}

echo "\nAplicando…\n";
$res = $cu->run($pkg['root'], $pkg['lock_file'], ['merge' => $merge, 'actor' => 'cli']);
$cleanup();

if ($res['backup_dir'] === '') {
    fwrite(STDERR, "  ! {$res['error']}\n");
    exit(1);
}

echo "  backup en: " . $rel($res['backup_dir']) . "\n";
echo "  archivos aplicados: {$res['applied']}\n";
if ($res['merged'] !== []) {
    echo "  MERGEADOS automáticamente (revisalos):\n";
    foreach ($res['merged'] as $p) {
        echo "    - {$p}\n";
    }
}
if ($res['conflicts'] !== []) {
    echo "  CONFLICTOS (resolvé y borrá los .new/.merge):\n";
    foreach ($res['conflicts'] as $c) {
        $hasMerge = in_array($c, $res['merge_conflicts'], true);
        echo "    - {$c}.new" . ($hasMerge ? "  (propuesta: {$c}.merge)" : '') . "\n";
    }
}
foreach ($res['errors'] as $e) {
    fwrite(STDERR, "  ! {$e}\n");
}
echo "  migraciones del core: {$res['migrations']}\n";

echo "\nListo. Versión: {$res['to']}.\n";
echo "Rollback: php system/console/core-update.php --rollback=" . $rel($res['backup_dir']) . "\n";

exit($res['ok'] ? 0 : 1);
