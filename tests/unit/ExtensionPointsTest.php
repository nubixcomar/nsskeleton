<?php

declare(strict_types=1);

/**
 * Puntos de extensión del core (Fase 3): config overrides, routes.app, vistas
 * child-theme y separación de migraciones core/app. No requiere DB.
 */

use Core\Config;
use Core\View;
use App\Services\Migrator;

group('Puntos de extensión (core/app)');

$root = PROJECT_PATH;
$overridesDir = $root . '/system/config/overrides';
$viewsOverrideDir = $root . '/system/app/Views/overrides';

// ── Core\Config: merge core + override (app gana) ─────────────────────────────

it('Config::load lee la config del core', function () {
    Config::flush();
    $app = Config::load('app');
    assertTrue(is_array($app) && array_key_exists('name', $app));
});

it('Config aplica overrides de la app y la app gana', function () use ($overridesDir) {
    $tmp = $overridesDir . '/__test_ext.php';
    file_put_contents($overridesDir . '/__ensuredir.txt', '1'); // asegura dir
    @unlink($overridesDir . '/__ensuredir.txt');
    // config core inexistente '__test_ext' → base []; el override define todo.
    file_put_contents($tmp, "<?php\nreturn ['clave' => 'app', 'nested' => ['a' => 1]];\n");
    Config::flush();
    try {
        assertEquals('app', Config::get('__test_ext', 'clave'));
        $nested = Config::get('__test_ext', 'nested');
        assertEquals(1, $nested['a']);
    } finally {
        @unlink($tmp);
        Config::flush();
    }
});

it('Config deep-merge: mapas se combinan, override pisa la clave', function () use ($overridesDir, $root) {
    // Usa 'features' (existe en core) para probar merge real.
    $tmp = $overridesDir . '/features.php';
    $existed = is_file($tmp);
    $backup = $existed ? (string) file_get_contents($tmp) : null;
    file_put_contents($tmp, "<?php\nreturn ['maintenance_banner' => true, 'mi_flag' => true];\n");
    Config::flush();
    try {
        $feat = Config::load('features');
        assertEquals(true, $feat['maintenance_banner']); // override pisó el default (false)
        assertEquals(true, $feat['mi_flag']);            // flag nuevo de la app
        assertTrue(array_key_exists('exportar_listados', $feat)); // se conserva el del core
    } finally {
        if ($existed) {
            file_put_contents($tmp, (string) $backup);
        } else {
            @unlink($tmp);
        }
        Config::flush();
    }
});

// ── routes.app.php se carga ───────────────────────────────────────────────────

it('routes.php carga config/routes.app.php si existe', function () use ($root) {
    $c = (string) file_get_contents($root . '/system/config/routes.php');
    assertContains('routes.app.php', $c);
});

// ── Vistas child-theme ────────────────────────────────────────────────────────

it('View resuelve el override de la app antes que el del core', function () use ($viewsOverrideDir) {
    // 'home' existe en el core (app/Views/home.php). Creamos un override temporal.
    $tmp = $viewsOverrideDir . '/__ext_probe.php';
    file_put_contents($tmp, "OVERRIDE-OK");
    try {
        assertTrue(View::exists('__ext_probe'));
        assertContains('OVERRIDE-OK', View::partial('__ext_probe'));
    } finally {
        @unlink($tmp);
    }
});

it('View sigue encontrando vistas del core', function () {
    assertTrue(View::exists('home'));
});

// ── Migraciones core/app separadas ───────────────────────────────────────────

it('Migrator distingue dir de core y de app', function () {
    assertContains('database/migrations', str_replace('\\', '/', Migrator::dir()));
    assertContains('database/migrations/app', str_replace('\\', '/', Migrator::appDir()));
    assertTrue(Migrator::dir() !== Migrator::appDir());
});

it('Migrator expone migrateCore y migrateApp', function () {
    assertTrue(method_exists(Migrator::class, 'migrateCore'));
    assertTrue(method_exists(Migrator::class, 'migrateApp'));
});

it('el generador escribe migraciones en el dir de la app', function () use ($root) {
    $c = (string) file_get_contents($root . '/system/console/make-module.php');
    assertContains('database/migrations/app/', $c);
});
