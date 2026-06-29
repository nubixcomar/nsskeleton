<?php

declare(strict_types=1);

use App\Services\FileManager as FM;

group('FileManager avanzado (extensiones, rename, move)');

$base = '__test_adv_' . substr(md5('nsfmadv'), 0, 6);
FM::delete($base); // por si quedó de una corrida previa
FM::makeDir('', $base);

$mkUpload = static function (string $name, int $size = 10): array {
    $tmp = sys_get_temp_dir() . '/nsfmadv_' . uniqid();
    file_put_contents($tmp, str_repeat('x', max(1, $size)));
    return ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => $name, 'size' => $size];
};

it('rechaza una extensión no permitida (.php)', function () use ($base, $mkUpload) {
    $r = FM::upload($base, $mkUpload('hack.php'));
    assertFalse($r['ok']);
    assertContains('no permitida', $r['error']);
});

it('rechaza un archivo demasiado grande', function () use ($base, $mkUpload) {
    $r = FM::upload($base, $mkUpload('grande.txt', 6 * 1024 * 1024));
    assertFalse($r['ok']);
});

it('acepta una extensión permitida (.txt)', function () use ($base, $mkUpload) {
    $r = FM::upload($base, $mkUpload('nota.txt', 20));
    assertTrue($r['ok']);
});

it('renombra un archivo', function () use ($base) {
    $r = FM::rename($base . '/nota.txt', 'renombrada.txt');
    assertTrue($r['ok']);
    assertTrue(in_array('renombrada.txt', array_column(FM::list($base)['files'], 'name'), true));
});

it('mueve un archivo a una subcarpeta', function () use ($base) {
    FM::makeDir($base, 'sub');
    $r = FM::move($base . '/renombrada.txt', $base . '/sub');
    assertTrue($r['ok']);
    assertTrue(in_array('renombrada.txt', array_column(FM::list($base . '/sub')['files'], 'name'), true));
});

it('no permite mover una carpeta dentro de sí misma', function () use ($base) {
    $r = FM::move($base . '/sub', $base . '/sub');
    assertFalse($r['ok']);
});

it('rename neutraliza traversal (queda dentro de la carpeta)', function () use ($base, $mkUpload) {
    FM::upload($base, $mkUpload('trav.txt', 10));
    $r = FM::rename($base . '/trav.txt', '../escape.txt');
    // cleanName convierte "../escape.txt" en "escape.txt" → no escapa de la raíz de uploads
    assertNull(FM::resolve('escape.txt'), 'no debe existir en la raíz de uploads');
});

it('detecta imágenes por extensión', function () {
    assertTrue(FM::isImage('foto.PNG'));
    assertFalse(FM::isImage('doc.pdf'));
});

it('limpieza', function () use ($base) {
    assertTrue(FM::delete($base)['ok']);
});
