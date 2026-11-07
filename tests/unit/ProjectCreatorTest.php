<?php

declare(strict_types=1);

use App\Services\Installer;
use App\Services\ProjectCreator as PC;

/**
 * /nuevo-proyecto — proyecto derivado desde el master (nombres, copia limpia, rol del árbol).
 */

group('ProjectCreator · nuevo proyecto');

$tmp = static function (string $tag): string {
    $d = str_replace('\\', '/', sys_get_temp_dir()) . '/nsnp_' . $tag . '_' . bin2hex(random_bytes(4));
    mkdir($d, 0775, true);
    return $d;
};
$put = static function (string $root, string $rel, string $content = 'x'): void {
    @mkdir(dirname($root . '/' . $rel), 0775, true);
    file_put_contents($root . '/' . $rel, $content);
};
$rm = static function (string $dir) use (&$rm): void {
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
        $p = $dir . '/' . $f;
        is_dir($p) ? $rm($p) : @unlink($p);
    }
    @rmdir($dir);
};

it('slug: minúsculas, sin acentos, guiones', function () {
    assertEquals('jardin-botanico', PC::slug('Jardín Botánico'));
    assertEquals('turnos-2', PC::slug('  Turnos #2 '));
    assertEquals('', PC::slug('***'));
});

it('dbName: identificador MySQL válido (no empieza con dígito)', function () {
    assertEquals('jardin_botanico', PC::dbName('jardin-botanico'));
    assertEquals('p_2026_ventas', PC::dbName('2026-ventas'));
    assertTrue(PC::isValidDbName(PC::dbName(str_repeat('a', 100))));
    assertFalse(PC::isValidDbName('mi-db'));
    assertFalse(PC::isValidDbName('x`; DROP'));
});

it('shouldCopy deja afuera git, secretos, landing, binarios, logs y runtime', function () {
    foreach ([
        '.git/config', '.env', 'system/.env', '.claude/settings.json', 'landing/index.html',
        'landing/downloads/nsSkeleton-core-1.19.0.zip', 'tools/bin/tailwindcss.exe',
        'node_modules/a/b.js', 'vendor/x.php', 'logs/core-dev.log', 'logs/walkthrough/a.md',
        'logs/features-resume.md', 'system/storage/backups/db.sql', 'system/storage/uploads/a.png',
        'system/storage/core-base/system/app/Core/App.php', 'system/public/assets/branding/logo.png',
        'nsproject.json', 'secrets/key.pem', 'x.log.tmp',
        'planning/roadmap/INDEX.md', 'planning/README.md',
    ] as $rel) {
        assertFalse(PC::shouldCopy($rel), "no debía copiar {$rel}");
    }
});

it('shouldCopy conserva el core, la app, la capa agéntica y los .gitkeep', function () {
    foreach ([
        'AGENTS.md', 'VERSION', 'core-lock.json', 'core-manifest.json', '.env.example', '.gitignore',
        'system/app/Core/App.php', 'agentic/rules/rules.md', 'app-agentic/rules/app-rules.md',
        'app/README.md', 'tests/run.php', 'tools/build-css.sh', 'logs/README.md',
        'system/storage/backups/.gitkeep', 'system/storage/sessions/.gitkeep',
        'agentic/templates/planning/roadmap/INDEX.md',
    ] as $rel) {
        assertTrue(PC::shouldCopy($rel), "debía copiar {$rel}");
    }
});

it('copyTree copia filtrando (y poda .git)', function () use ($tmp, $put, $rm) {
    $src = $tmp('src');
    $dst = $tmp('dst');
    $put($src, 'AGENTS.md');
    $put($src, 'system/app/A.php');
    $put($src, '.git/HEAD');
    $put($src, '.env', 'DB_PASS=secreto');
    $put($src, 'logs/README.md');
    $put($src, 'logs/core-dev.log');
    $put($src, 'system/storage/cache/.gitkeep', '');
    $put($src, 'system/storage/cache/tmp.bin');
    try {
        $n = PC::copyTree($src, $dst);
        assertEquals(4, $n);
        assertTrue(is_file($dst . '/AGENTS.md'));
        assertTrue(is_file($dst . '/system/app/A.php'));
        assertTrue(is_file($dst . '/logs/README.md'));
        assertTrue(is_file($dst . '/system/storage/cache/.gitkeep'));
        assertFalse(file_exists($dst . '/.git'));
        assertFalse(file_exists($dst . '/.env'));
        assertFalse(file_exists($dst . '/logs/core-dev.log'));
    } finally {
        $rm($src);
        $rm($dst);
    }
});

it('role: master con empaquetador; project con marcador o sin landing', function () use ($tmp, $put, $rm) {
    $d = $tmp('role');
    try {
        assertEquals('project', PC::role($d)['role'], 'sin landing → project');
        $put($d, 'landing/build-core-package.php', '<?php');
        assertEquals('master', PC::role($d)['role']);
        assertTrue(PC::isMaster($d));
        $put($d, PC::MARKER, PC::marker(['name' => 'X']));
        assertEquals('project', PC::role($d)['role'], 'el marcador manda');
    } finally {
        $rm($d);
    }
});

it('este repo (skeleton) se detecta como master', function () {
    if (is_file(PROJECT_PATH . '/' . PC::MARKER)) {
        return 'skip'; // corriendo en un derivado
    }
    assertEquals('master', PC::role(PROJECT_PATH)['role']);
});

it('marker: JSON con rol project y versión de origen', function () {
    $j = json_decode(PC::marker(['name' => 'Jardín', 'slug' => 'jardin', 'core_version' => '1.19.0', 'skeleton_commit' => 'abc123']), true);
    assertEquals('project', $j['role']);
    assertEquals('Jardín', $j['name']);
    assertEquals('1.19.0', $j['created_from']['core_version']);
    assertEquals('abc123', $j['created_from']['skeleton_commit']);
});

it('projectFiles reinicia roadmap y logs del proyecto', function () {
    $f = PC::projectFiles(['name' => 'Jardín', 'slug' => 'jardin', 'core_version' => '1.19.0', 'date' => '2026-11-07 10:00']);
    assertTrue(isset($f['docs/roadmap.md'], $f['logs/features-resume.md'], $f['logs/bugs-resume.md'], $f['logs/walkthrough/.gitkeep']));
    assertContains('Jardín', $f['docs/roadmap.md']);
    assertFalse(str_contains($f['docs/roadmap.md'], 'v0.1 — Fundaciones'), 'no debe traer el roadmap del skeleton');
});

it('remoteIsSkeleton detecta el repo de nsSkeleton', function () {
    assertTrue(PC::remoteIsSkeleton('https://github.com/nubixcomar/nsskeleton.git'));
    assertTrue(PC::remoteIsSkeleton('git@github.com:NubixComAr/nsSkeleton.git'));
    assertTrue(PC::remoteIsSkeleton("origin\thttps://github.com/nubixcomar/nsskeleton (fetch)"));
    assertFalse(PC::remoteIsSkeleton('https://github.com/nubixcomar/jardin.git'));
});

it('destIsFree: inexistente o vacío sí; con archivos no', function () use ($tmp, $put, $rm) {
    $d = $tmp('dest');
    try {
        assertTrue(PC::destIsFree($d . '/no-existe'));
        assertTrue(PC::destIsFree($d));
        $put($d, 'a.txt');
        assertFalse(PC::destIsFree($d));
        assertFalse(PC::destIsFree($d . '/a.txt'), 'un archivo no es destino');
    } finally {
        $rm($d);
    }
});

it('buildEnv: plantilla explícita, APP_KEY y valores con $ literales', function () use ($tmp, $rm) {
    $d = $tmp('env');
    try {
        file_put_contents($d . '/.env.example', "APP_KEY=\nDB_PASS=\nCORE_UPDATE_URL=\n");
        $env = Installer::buildEnv(['app_key' => 'base64:abc', 'db_pass' => 'p$1\\2', 'core_update_url' => 'https://x/latest.json'], $d . '/.env.example');
        assertContains('APP_KEY=base64:abc', $env);
        assertContains('DB_PASS=p$1\\2', $env);
        assertContains('CORE_UPDATE_URL=https://x/latest.json', $env);
    } finally {
        $rm($d);
    }
});
