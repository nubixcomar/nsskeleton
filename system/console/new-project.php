<?php

declare(strict_types=1);

/**
 * Crea un PROYECTO NUEVO desde este skeleton (master), listo para arrancar y desvinculado
 * del repo de nsSkeleton. Lo orquesta el comando /nuevo-proyecto.
 *
 *   php system/console/new-project.php [<nombre>] [opciones]
 *
 * Sin <nombre> (y con terminal interactiva) lo pregunta.
 *
 * Opciones:
 *   --path=<dir>     Carpeta destino (default: hermana del master, ej. c:/xampp/htdocs/<slug>)
 *   --db=<nombre>    Base de datos (default: <slug> con _). Debe NO existir.
 *   --url=<url>      APP_URL (default: http://localhost/<slug>/system/public)
 *   --db-host= --db-port= --db-user= --db-pass=   (default: los del .env del master)
 *   --remote=<url>   Repo git propio del proyecto (se agrega como origin; NO se pushea)
 *   --demo           Carga datos demo (seed-demo.php)
 *   --no-db          No crea ni migra la base (solo copia + .env + git)
 *   --no-git         No inicializa git
 *   --strict         Las advertencias del master (cambios sin commitear, lock con drift) abortan
 *   --dry-run        Muestra el plan y las verificaciones, sin tocar nada
 *   --yes            No pedir confirmación
 *   --force          Permite correrlo desde un árbol que no es el master
 *
 * Qué hace: copia limpia (sin .git/.env/landing/tools/bin/logs/runtime) → archivos propios
 * del proyecto (nsproject.json, roadmap y logs vacíos) → .env (DB, APP_KEY nueva) →
 * crea la DB + migra + seed → base del merge del actualizador → git init + commit inicial.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('BASE_PATH', dirname(__DIR__));
define('PROJECT_PATH', dirname(BASE_PATH));
require BASE_PATH . '/app/Core/autoload.php';

use Core\Crypto;
use Core\Env;
use App\Services\Installer;
use App\Services\ProjectCreator as PC;

Env::load(PROJECT_PATH . '/.env');
@date_default_timezone_set((string) Env::get('APP_TIMEZONE', date_default_timezone_get()));

// ── Args ─────────────────────────────────────────────────────────────────────
$opts = [];
$positional = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/s', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    } else {
        $positional[] = $arg;
    }
}
$opt = static fn (string $k, ?string $d = null): ?string => isset($opts[$k]) && is_string($opts[$k]) ? $opts[$k] : $d;
$flag = static fn (string $k): bool => isset($opts[$k]);

if ($flag('help') || $flag('h')) {
    preg_match('#/\*\*(.*?)\*/#s', (string) file_get_contents(__FILE__), $doc);
    echo preg_replace('/^ ?\* ?/m', '', trim($doc[1] ?? '')) . "\n";
    exit(0);
}

$interactive = function_exists('stream_isatty') ? @stream_isatty(STDIN) : false;
$ask = static function (string $q, string $default = '') use ($interactive): string {
    if (!$interactive) {
        return $default;
    }
    echo $q . ($default !== '' ? " [{$default}]" : '') . ': ';
    $a = trim((string) fgets(STDIN));
    return $a === '' ? $default : $a;
};
$fail = static function (string $msg): never {
    fwrite(STDERR, "ERROR: {$msg}\n");
    exit(1);
};
$run = static function (string $cmd, ?string $cwd = null): int {
    $prev = getcwd();
    if ($cwd !== null) {
        chdir($cwd);
    }
    $code = 0;
    passthru($cmd, $code);
    if ($cwd !== null && $prev !== false) {
        chdir($prev);
    }
    return $code;
};
$capture = static function (string $cmd): array {
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    return [$code, $out];
};
$php = escapeshellarg(PHP_BINARY);
$master = str_replace('\\', '/', PROJECT_PATH);

// ── 0. Debe correr desde el master ──────────────────────────────────────────
$role = PC::role($master);
if ($role['role'] !== 'master' && !$flag('force')) {
    $fail("este árbol es un proyecto derivado ({$role['reason']}). /nuevo-proyecto se corre desde "
        . "el skeleton master; copiar un derivado arrastraría su código. Usá --force si es intencional.");
}

// ── 1. Nombre y derivados ────────────────────────────────────────────────────
$name = trim($positional[0] ?? '');
if ($name === '') {
    $name = $ask('Nombre del proyecto (ej. "Jardín Botánico")');
}
if ($name === '') {
    $fail('falta el nombre del proyecto. Uso: php system/console/new-project.php "<nombre>"');
}
$slug = PC::slug($name);
if ($slug === '') {
    $fail("el nombre \"{$name}\" no produce un nombre de carpeta válido.");
}

$parent = dirname($master);
$dest = str_replace('\\', '/', $opt('path') ?? ($interactive && !$flag('yes') ? $ask('Carpeta destino', $parent . '/' . $slug) : $parent . '/' . $slug));
$dest = rtrim($dest, '/');
$withDb = !$flag('no-db');
$db = $opt('db') ?? ($withDb && $interactive && !$flag('yes') ? $ask('Base de datos', PC::dbName($slug)) : PC::dbName($slug));
$url = $opt('url') ?? 'http://localhost/' . basename($dest) . '/system/public';
$remote = $opt('remote') ?? ($interactive && !$flag('yes') && !$flag('no-git') ? $ask('Repo git propio (vacío = sin remoto)') : '');

$dbHost = $opt('db-host') ?? (string) Env::get('DB_HOST', '127.0.0.1');
$dbPort = $opt('db-port') ?? (string) Env::get('DB_PORT', '3306');
$dbUser = $opt('db-user') ?? (string) Env::get('DB_USER', 'root');
$dbPass = $opt('db-pass') ?? (string) Env::get('DB_PASS', '');

$coreVersion = trim((string) @file_get_contents($master . '/VERSION')) ?: '0.0.0';

// ── 2. Verificaciones ────────────────────────────────────────────────────────
$errors = [];
$warnings = [];

if (realpath($dest) !== false && str_replace('\\', '/', (string) realpath($dest)) === $master) {
    $errors[] = 'el destino es el propio master.';
} elseif (str_starts_with($dest . '/', $master . '/')) {
    $errors[] = "el destino ({$dest}) no puede estar dentro del master.";
} elseif (!PC::destIsFree($dest)) {
    $errors[] = "la carpeta destino ya existe y no está vacía: {$dest}";
}
if ($remote !== '' && PC::remoteIsSkeleton($remote)) {
    $errors[] = "el remoto {$remote} es el repo de nsSkeleton; el proyecto necesita su propio repo.";
}

if ($withDb) {
    if (!PC::isValidDbName($db)) {
        $errors[] = "nombre de base inválido: \"{$db}\" (solo letras, números y _; máx. 64).";
    } else {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbHost, $dbPort),
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $st = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $st->execute([$db]);
            if ($st->fetchColumn() !== false) {
                $errors[] = "la base \"{$db}\" ya existe en {$dbHost}:{$dbPort}. Elegí otra (--db=) o borrala a mano.";
            }
        } catch (PDOException $e) {
            $errors[] = "no se pudo conectar a MySQL {$dbHost}:{$dbPort} ({$e->getMessage()}). ¿Está levantado? (o usá --no-db)";
        }
    }
}

// Estado del master: el proyecto hereda su VERSION y su core-lock.json.
[$code, $out] = $capture('git -C ' . escapeshellarg($master) . ' rev-parse --short HEAD');
$commit = $code === 0 ? trim($out[0] ?? '') : '';
[$code, $out] = $capture('git -C ' . escapeshellarg($master) . ' status --porcelain');
$dirty = $code === 0 && array_filter($out) !== [];
if ($dirty) {
    $warnings[] = 'el master tiene cambios sin commitear: se copian tal cual, pero no son una versión '
        . "publicada (VERSION dice {$coreVersion}). Lo ideal: /release en el master antes de crear el proyecto.";
}
[$code, $out] = $capture($php . ' ' . escapeshellarg($master . '/system/console/core-manifest.php') . ' --check');
if ($code !== 0) {
    $warnings[] = 'core-lock.json tiene drift con el árbol: el proyecto nacería con un lock que no '
        . 'describe su core (el actualizador lo vería como "editado"). Corré /release o core-manifest.php.';
}

// ── 3. Plan ──────────────────────────────────────────────────────────────────
echo "\nNuevo proyecto desde nsSkeleton {$coreVersion}" . ($commit !== '' ? " ({$commit}" . ($dirty ? ', con cambios sin commitear' : '') . ')' : '') . "\n";
echo "  Nombre:   {$name}\n";
echo "  Carpeta:  {$dest}\n";
echo "  APP_URL:  {$url}\n";
echo '  Base:     ' . ($withDb ? "{$db} en {$dbHost}:{$dbPort} (crear + migrar + seed" . ($flag('demo') ? ' + demo' : '') . ')' : '(omitida: --no-db)') . "\n";
echo '  Git:      ' . ($flag('no-git') ? '(omitido: --no-git)' : 'repo nuevo (main) + commit inicial' . ($remote !== '' ? ", origin = {$remote}" : ', sin remoto')) . "\n";

foreach ($warnings as $w) {
    echo "  AVISO: {$w}\n";
}
foreach ($errors as $e) {
    fwrite(STDERR, "  ERROR: {$e}\n");
}
if ($errors !== []) {
    exit(1);
}
if ($warnings !== [] && $flag('strict')) {
    $fail('hay avisos y se pidió --strict.');
}
if ($flag('dry-run')) {
    echo "\n(dry-run: no se tocó nada)\n";
    exit(0);
}
if (!$flag('yes')) {
    if (!$interactive) {
        $fail('sin terminal interactiva: confirmá con --yes (revisá el plan de arriba).');
    }
    if (!in_array(strtolower($ask("\n¿Crear el proyecto? (s/N)", 'n')), ['s', 'si', 'sí', 'y', 'yes'], true)) {
        echo "Cancelado.\n";
        exit(0);
    }
}

// ── 4. Copia limpia ─────────────────────────────────────────────────────────
echo "\n[1/6] Copiando el skeleton…\n";
if (!is_dir($dest) && !mkdir($dest, 0775, true) && !is_dir($dest)) {
    $fail("no se pudo crear {$dest}");
}
try {
    $n = PC::copyTree($master, $dest);
} catch (Throwable $e) {
    $fail('copia: ' . $e->getMessage() . " (la carpeta {$dest} quedó a medias: revisala/borrala a mano).");
}
echo "      {$n} archivos copiados.\n";

// ── 5. Archivos del proyecto ─────────────────────────────────────────────────
echo "[2/6] Archivos del proyecto (nsproject.json, roadmap, logs)…\n";
$now = date('Y-m-d H:i');
foreach (PC::projectFiles(['name' => $name, 'slug' => $slug, 'core_version' => $coreVersion, 'date' => $now]) as $rel => $content) {
    $file = $dest . '/' . $rel;
    @mkdir(dirname($file), 0775, true);
    file_put_contents($file, $content);
}
file_put_contents($dest . '/' . PC::MARKER, PC::marker([
    'name' => $name,
    'slug' => $slug,
    'created_at' => date('c'),
    'core_version' => $coreVersion,
    'skeleton_commit' => $commit,
    'skeleton_dirty' => $dirty,
]));

// ── 6. .env ──────────────────────────────────────────────────────────────────
echo "[3/6] Generando .env (APP_KEY nueva)…\n";
$env = Installer::buildEnv([
    'project_name' => $name,
    'app_url' => $url,
    'db_host' => $dbHost,
    'db_port' => $dbPort,
    'db_name' => $withDb ? $db : PC::dbName($slug),
    'db_user' => $dbUser,
    'db_pass' => $dbPass,
    'app_key' => Crypto::generateKey(),
    'core_update_url' => (string) Env::get('CORE_UPDATE_URL', ''),
], $dest . '/.env.example');
file_put_contents($dest . '/.env', $env);

// Los subprocesos (migrate, seed, core-update) heredan el entorno de ESTE proceso, que cargó el
// .env del master con putenv(); y Env::load() no pisa variables ya definidas. Sin esto, el
// migrate del proyecto nuevo correría contra la base del MASTER. Se quitan las claves del .env
// del master y se carga el del proyecto nuevo, así los hijos ven exactamente ese .env.
$envKeys = static function (string $file): array {
    $keys = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $m)) {
            $keys[] = $m[1];
        }
    }
    return $keys;
};
foreach (array_unique(array_merge($envKeys($master . '/.env'), $envKeys($dest . '/.env'))) as $k) {
    putenv($k);
    unset($_ENV[$k], $_SERVER[$k]);
}
Env::load($dest . '/.env');

// ── 7. Base de datos ────────────────────────────────────────────────────────
if ($withDb) {
    echo "[4/6] Creando y migrando la base {$db}…\n";
    if ($run($php . ' ' . escapeshellarg($dest . '/system/database/migrate.php'), $dest) !== 0) {
        $fail("falló la migración. El proyecto quedó copiado en {$dest}; corregí y corré ahí: php system/database/migrate.php");
    }
    // Verificación: la base del proyecto existe y tiene tablas (no se migró otra).
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?');
        $st->execute([$db]);
        $tables = (int) $st->fetchColumn();
    } catch (PDOException $e) {
        $tables = 0;
    }
    if ($tables === 0) {
        $fail("la migración no creó tablas en \"{$db}\". Revisá {$dest}/.env y corré ahí: php system/database/migrate.php");
    }
    echo "      {$tables} tablas en {$db}.\n";
    $run($php . ' ' . escapeshellarg($dest . '/system/database/seed.php'), $dest);
    if ($flag('demo')) {
        $run($php . ' ' . escapeshellarg($dest . '/system/database/seed-demo.php'), $dest);
    }
} else {
    echo "[4/6] Base de datos omitida (--no-db).\n";
}

// ── 8. Base del merge del actualizador + planning/ vacío ────────────────────
echo "[5/6] Guardando los originales del core (base del merge para /actualizar-core)…\n";
$run($php . ' ' . escapeshellarg($dest . '/system/console/core-update.php') . ' --seed-base', $dest);
if (is_file($dest . '/system/console/planning.php')) {
    // planning/ del proyecto (roadmap RM- / briefs BR- / planes PL-) desde el scaffold, vacío.
    $run($php . ' ' . escapeshellarg($dest . '/system/console/planning.php') . ' init --root=' . escapeshellarg($dest), $dest);
}

// Trazabilidad en el proyecto nuevo.
@file_put_contents(
    $dest . '/logs/new-project.log',
    sprintf("[%s] [FEATURE] new-project | proyecto \"%s\" creado desde nsSkeleton %s%s | modelo: n/a (CLI)\n",
        date('Y-m-d H:i:s'), $name, $coreVersion, $commit !== '' ? " ({$commit})" : ''),
    FILE_APPEND
);

// ── 9. Git propio ────────────────────────────────────────────────────────────
$gitOk = null;
if (!$flag('no-git')) {
    echo "[6/6] Git propio del proyecto…\n";
    // safecrlf=false: sin un aviso "LF will be replaced by CRLF" por archivo en Windows.
    $g = 'git -c core.safecrlf=false -C ' . escapeshellarg($dest);
    $gitOk = $run($g . ' init -q -b main') === 0
        && $run($g . ' add -A') === 0
        && $run($g . ' commit -q -m ' . escapeshellarg("chore: init {$name} desde nsSkeleton {$coreVersion}")) === 0;
    if (!$gitOk) {
        echo "      AVISO: no se pudo completar git (¿falta user.name/user.email?). Hacelo a mano en {$dest}.\n";
    }
    if ($remote !== '') {
        $run($g . ' remote add origin ' . escapeshellarg($remote));
    }
    [, $remotes] = $capture($g . ' remote -v');
    foreach ($remotes as $r) {
        if (PC::remoteIsSkeleton($r)) {
            $fail("el proyecto quedó con un remoto del skeleton ({$r}). Quitalo: git remote remove origin");
        }
    }
} else {
    echo "[6/6] Git omitido (--no-git).\n";
}

// ── 10. Resumen ─────────────────────────────────────────────────────────────
echo "\nListo: {$name}\n";
echo "  Carpeta:  {$dest}\n";
echo "  Web:      {$url}/   ·   Admin: {$url}/admin/login\n";
if ($withDb) {
    echo "  Admin inicial: admin@nsskeleton.local / admin1234  (cambialo al entrar)\n";
}
echo '  Git:      ' . ($flag('no-git') ? 'sin inicializar' : ($gitOk ? 'main con commit inicial' : 'incompleto') . ($remote !== '' ? " · origin {$remote} (sin push)" : ' · sin remoto (desvinculado de nsSkeleton)')) . "\n";
echo "\nSiguiente: abrí {$dest} con tu IA y corré /instalar (brief, stack y reglas del proyecto).\n";
if ($remote !== '') {
    echo "Para subirlo: git -C " . escapeshellarg($dest) . " push -u origin main\n";
}
exit(0);
