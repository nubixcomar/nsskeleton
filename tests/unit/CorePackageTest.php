<?php

declare(strict_types=1);

/**
 * Empaquetado del core (Fase 6). Corre landing/build-core-package.php a una carpeta
 * temporal y verifica que el zip trae el core (y su lock) y NO la app ni secretos.
 */

group('Empaquetado de core');

$root = PROJECT_PATH;

it('build-core-package genera un zip con el core y su lock, sin la app', function () use ($root) {
    if (!class_exists('ZipArchive') || !function_exists('exec')) {
        return 'skip';
    }
    $outDir = sys_get_temp_dir() . '/nscore_' . bin2hex(random_bytes(4));
    @mkdir($outDir, 0775, true);

    $php = escapeshellarg(PHP_BINARY);
    $script = escapeshellarg($root . '/landing/build-core-package.php');
    exec($php . ' ' . $script . ' --out=' . escapeshellarg($outDir) . ' 2>&1', $out, $code);
    assertEquals(0, $code, 'el empaquetador debió salir 0: ' . implode(' ', $out));

    $zips = glob($outDir . '/nsSkeleton-core-*.zip') ?: [];
    assertCount(1, $zips);

    $zip = new ZipArchive();
    assertTrue($zip->open($zips[0]) === true);

    $has = static function (ZipArchive $z, string $name): bool {
        return $z->locateName($name) !== false;
    };
    // Trae el lock y archivos del core.
    assertTrue($has($zip, 'core-lock.json'), 'falta core-lock.json en el zip');
    assertTrue($has($zip, 'VERSION'), 'falta VERSION');
    assertTrue($has($zip, 'system/app/Services/CoreUpdater.php'), 'falta un archivo de core');

    // NO trae lo de la app ni secretos.
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();

    foreach ($names as $n) {
        assertFalse(str_starts_with($n, 'app-agentic/'), "el zip de core no debe traer app-agentic: {$n}");
        assertFalse($n === '.env', 'el zip de core no debe traer .env');
        assertFalse(str_starts_with($n, 'landing/downloads/'), "no debe traer descargas: {$n}");
    }

    // Limpieza.
    @unlink($zips[0]);
    @rmdir($outDir);
});
