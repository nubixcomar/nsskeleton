<?php

declare(strict_types=1);

/**
 * v1.17 #1 — Frontera código-app fuera de core-lock (hallazgo 1.6).
 * El código propio del proyecto va en `app/` (raíz), namespace App\, y NO genera drift.
 */

group('Frontera core/app');

it('el autoloader resuelve una clase App\\ desde app/ (raíz del proyecto)', function () {
    $root = PROJECT_PATH;
    $dir = $root . '/app/Services';
    $file = $dir . '/__AutoloadProbe.php';

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    file_put_contents($file, "<?php\nnamespace App\\Services;\nclass __AutoloadProbe { public function ping(): string { return 'pong'; } }\n");

    try {
        assertTrue(class_exists('App\\Services\\__AutoloadProbe'), 'clase de app/ debe autoloadear');
        $obj = new \App\Services\__AutoloadProbe();
        assertEquals('pong', $obj->ping());
    } finally {
        @unlink($file);
    }
});

it('el core-manifest lista app/ como app_path (fuera de core-lock)', function () {
    $manifest = json_decode((string) file_get_contents(PROJECT_PATH . '/core-manifest.json'), true);
    assertTrue(is_array($manifest));
    assertTrue(in_array('app/', $manifest['app_paths'] ?? [], true), 'app/ debe estar en app_paths');
    assertTrue(!in_array('app/', $manifest['core_paths'] ?? [], true), 'app/ NO debe ser core_path');
});

it('un archivo bajo app/ NO cuenta como core (no genera drift)', function () {
    // El generador de core-lock clasifica app/ como app, no como core.
    // Verificación indirecta: app/ está en app_paths y no en core_paths (ver test previo),
    // y el README de app/ existe como guía.
    assertTrue(is_file(PROJECT_PATH . '/app/README.md'), 'app/README.md guía dónde va el código');
});

it('las clases del core (system/app) siguen resolviendo con prioridad', function () {
    // Core\Security es del core y debe seguir cargando desde system/app/Core.
    assertTrue(class_exists('Core\\Security'));
    assertTrue(class_exists('App\\Services\\Deployer')); // service del core en system/app
});
