<?php

declare(strict_types=1);

/**
 * Paquete completo (landing/build-download.php): nunca debe llevar secretos ni runtime.
 * Regresión v1.20: se colaban las sesiones (sess_*) y la base del merge (core-base/).
 */

group('Paquete completo (build-download)');

it('build-download no empaqueta .env, sesiones, core-base, planning/ ni descargas', function () {
    if (!class_exists('ZipArchive') || !function_exists('exec')) {
        return 'skip';
    }
    if (!is_file(PROJECT_PATH . '/landing/build-download.php')) {
        return 'skip'; // proyecto derivado: no tiene landing/
    }

    // Plantar un archivo de sesión y uno de base para asegurar que el filtro los ve.
    $sess = BASE_PATH . '/storage/sessions/sess_testpkg' . bin2hex(random_bytes(3));
    $base = BASE_PATH . '/storage/core-base/__testpkg.txt';
    @mkdir(dirname($sess), 0775, true);
    @mkdir(dirname($base), 0775, true);
    file_put_contents($sess, 'admin|a:1:{}');
    file_put_contents($base, 'x');

    $outDir = sys_get_temp_dir() . '/nsdl_' . bin2hex(random_bytes(4));
    try {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PROJECT_PATH . '/landing/build-download.php')
            . ' --out=' . escapeshellarg($outDir) . ' 2>&1';
        exec($cmd, $out, $code);
        assertEquals(0, $code, 'build-download debió salir 0: ' . implode(' ', $out));

        $zips = glob($outDir . '/nsSkeleton-*.zip') ?: [];
        assertCount(1, $zips);
        $z = new ZipArchive();
        assertTrue($z->open($zips[0]) === true);
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = (string) preg_replace('#^nsSkeleton-[^/]+/#', '', (string) $z->getNameIndex($i));
        }
        $z->close();

        assertTrue(in_array('VERSION', $names, true), 'falta VERSION');
        assertTrue(in_array('system/storage/sessions/.gitkeep', $names, true), 'debe conservar el .gitkeep');
        foreach ($names as $n) {
            assertFalse($n === '.env', 'no debe traer .env');
            assertFalse(str_starts_with($n, 'system/storage/sessions/sess_'), "no debe traer sesiones: {$n}");
            assertFalse(str_starts_with($n, 'system/storage/core-base/'), "no debe traer la base del merge: {$n}");
            assertFalse(str_starts_with($n, 'planning/'), "no debe traer el planning del master: {$n}");
            assertFalse(str_starts_with($n, 'landing/downloads/') && !str_ends_with($n, '.gitkeep'), "no debe traer descargas: {$n}");
            assertFalse(str_starts_with($n, '.git/'), "no debe traer .git: {$n}");
        }
    } finally {
        @unlink($sess);
        @unlink($base);
        foreach (glob($outDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($outDir);
    }
});
