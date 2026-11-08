<?php

declare(strict_types=1);

/**
 * PL-002/F6 — `core-manifest.php --check` en un proyecto derivado (BR-001 CS6, NSK-02).
 * Árbol temporal con nsproject.json: solo cuentan los archivos del lock; lo que agrega el
 * proyecto (routes.app.php, tests/app/…) no es drift. Ejecuta el script real con --root.
 */

group('core-manifest --check · modo proyecto (PL-002/F6)');

$run = static function (string $root): array {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/console/core-manifest.php')
        . ' --check --root=' . escapeshellarg($root) . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    return [$code, implode("\n", $out)];
};

$rrmdir = static function (string $dir) use (&$rrmdir): void {
    foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $p) {
        if (in_array(basename($p), ['.', '..'], true)) {
            continue;
        }
        is_dir($p) ? $rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
};

it('archivos agregados por el proyecto no son drift; modificar o borrar uno del core sí', function () use ($run, $rrmdir) {
    if (!function_exists('exec')) {
        return 'skip';
    }
    $tmp = sys_get_temp_dir() . '/nsck_' . bin2hex(random_bytes(4));
    $put = static function (string $rel, string $content) use ($tmp): void {
        @mkdir(dirname($tmp . '/' . $rel), 0775, true);
        file_put_contents($tmp . '/' . $rel, $content);
    };
    try {
        $put('nsproject.json', '{"role":"project","name":"Prueba"}');
        $put('core-manifest.json', (string) file_get_contents(PROJECT_PATH . '/core-manifest.json'));
        $put('a.txt', "A\n");
        $put('system/x.php', "<?php // core\n");
        $put('core-lock.json', json_encode(['core_version' => '9.9.9', 'files' => [
            'a.txt'        => hash('sha256', "A\n"),
            'system/x.php' => hash('sha256', "<?php // core\n"),
        ]]));
        // Lo que agrega el proyecto (puntos de extensión, módulos, tests propios).
        $put('system/config/routes.app.php', "<?php return static function (\$r): void {};\n");
        $put('system/app/Controllers/Admin/MiModuloController.php', "<?php // generado\n");
        $put('tests/app/MiTest.php', "<?php // test del proyecto\n");

        [$code, $out] = $run($tmp);
        assertEquals(0, $code, 'sin drift esperado: ' . $out);
        assertTrue(str_contains($out, 'modo proyecto'));

        $put('system/x.php', "<?php // editado por la app\n");
        [$code, $out] = $run($tmp);
        assertEquals(1, $code);
        assertTrue(str_contains($out, '[modificado] system/x.php'), $out);

        @unlink($tmp . '/a.txt');
        [$code, $out] = $run($tmp);
        assertEquals(1, $code);
        assertTrue(str_contains($out, '[falta]') && str_contains($out, 'a.txt'), $out);
    } finally {
        $rrmdir($tmp);
    }
});

it('en el master --check sigue sin drift', function () use ($run) {
    if (!function_exists('exec')) {
        return 'skip';
    }
    [$code, $out] = $run(PROJECT_PATH);
    assertEquals(0, $code, $out);
});
