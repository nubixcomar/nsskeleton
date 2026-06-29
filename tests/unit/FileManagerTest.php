<?php

declare(strict_types=1);

use App\Services\FileManager as FM;

group('FileManager');

$base = '__test_' . substr(md5('nsfm'), 0, 6);

it('crea carpeta', fn () => assertTrue(FM::makeDir('', $base)['ok']));
it('crea subcarpeta', fn () => assertTrue(FM::makeDir($base, 'sub')['ok']));

it('sube un archivo (simulado)', function () use ($base) {
    $tmp = sys_get_temp_dir() . '/nsfm_upload.txt';
    file_put_contents($tmp, 'contenido');
    $r = FM::upload($base . '/sub', ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => 'saludo.txt']);
    assertTrue($r['ok']);
    @unlink($tmp);
});

it('lista la carpeta creada en la raíz', function () use ($base) {
    $names = array_column(FM::list('')['dirs'], 'name');
    assertTrue(in_array($base, $names, true));
});
it('lista el archivo subido en la subcarpeta', function () use ($base) {
    $names = array_column(FM::list($base . '/sub')['files'], 'name');
    assertTrue(in_array('saludo.txt', $names, true));
});
it('resuelve la ruta del archivo', fn () => assertNotNull(FM::resolve($base . '/sub/saludo.txt')));
it('breadcrumb de 3 niveles', fn () => assertCount(3, FM::breadcrumb($base . '/sub')));

// Anti path-traversal
it('normalizeRel rechaza ".."', fn () => assertNull(FM::normalizeRel('../../config')));
it('safeDir rechaza ".."', fn () => assertNull(FM::safeDir('../..')));
it('resolve rechaza traversal', fn () => assertNull(FM::resolve('../../config/database.php')));
it('cleanName neutraliza ".."', function () {
    $c = FM::cleanName('../x');
    assertTrue($c === null || !str_contains($c, '..'));
});

it('elimina la carpeta de forma recursiva', fn () => assertTrue(FM::delete($base)['ok']));
it('la carpeta ya no existe', fn () => assertNull(FM::safeDir($base)));
