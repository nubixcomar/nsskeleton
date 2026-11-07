<?php

declare(strict_types=1);

use App\Services\Http;
use App\Services\RemoteDocs;

/**
 * RemoteDocs: sincronización de manuales remotos sin red (transporte HTTP inyectado)
 * contra un directorio temporal.
 */

group('RemoteDocs (manuales remotos)');

$tmpRoot = sys_get_temp_dir() . '/nss-remote-docs-' . bin2hex(random_bytes(4));
$cfg = [
    'target_dir'    => 'kb/remote',
    'max_age_hours' => 24,
    'docs' => [
        'demo' => [
            'enabled' => true, 'title' => 'Demo', 'url' => 'https://example.test/manual',
            'file' => 'demo/manual.md', 'min_bytes' => 10,
            'version_pattern' => '/\*\*Versi[oó]n del manual:\*\*\s*`([^`]+)`/u',
        ],
        'off' => ['enabled' => false, 'url' => 'https://example.test/off', 'file' => 'off.md'],
    ],
];
$manual = static fn (string $v): string => "# Manual demo\n\n**Versión del manual:** `{$v}` — **Fecha:** 2026-11-07\n\nContenido.\n";
$serve = static function (int $status, string $body): void {
    Http::setTransport(static fn () => ['status' => $status, 'body' => $body, 'error' => '']);
};
$cleanup = static function (string $dir) use (&$cleanup): void {
    foreach (glob($dir . '/{,.}[!.]*', GLOB_BRACE) ?: [] as $f) {
        is_dir($f) ? $cleanup($f) : @unlink($f);
    }
    @rmdir($dir);
};

RemoteDocs::useConfig($cfg, $tmpRoot);

it('la config del core declara los dos manuales de nubixstore', function () {
    RemoteDocs::useConfig(null);
    $docs = RemoteDocs::docs();
    assertTrue(isset($docs['nubixstore-referencia'], $docs['buenas-practicas-api']));
    assertContains('https://central.nstore.ar/', $docs['nubixstore-referencia']['url']);
});

RemoteDocs::useConfig($cfg, $tmpRoot);

it('primer sync: escribe el manual verbatim, extrae la versión y crea el lock', function () use ($serve, $manual) {
    $serve(200, $manual('2.6.0+aaaa'));
    $r = RemoteDocs::sync('demo');
    assertEquals('new', $r['demo']['status']);
    assertEquals('2.6.0+aaaa', $r['demo']['version']);
    assertEquals($manual('2.6.0+aaaa'), file_get_contents(RemoteDocs::pathFor('demo')));
    assertTrue(is_file(RemoteDocs::targetDir() . '/' . RemoteDocs::LOCK_FILE));
});

it('mismo contenido: unchanged y no crea .prev', function () use ($serve, $manual) {
    $serve(200, $manual('2.6.0+aaaa'));
    assertEquals('unchanged', RemoteDocs::sync('demo')['demo']['status']);
    assertFalse(is_file(RemoteDocs::targetDir() . '/demo/manual.prev.md'));
});

it('contenido nuevo: updated, guarda el anterior como .prev.md y reporta la versión previa', function () use ($serve, $manual) {
    $serve(200, $manual('2.7.0+bbbb'));
    $r = RemoteDocs::sync('demo')['demo'];
    assertEquals('updated', $r['status']);
    assertEquals('2.6.0+aaaa', $r['prev_version']);
    assertContains('2.6.0+aaaa', (string) file_get_contents(RemoteDocs::targetDir() . '/demo/manual.prev.md'));
    assertContains('2.7.0+bbbb', (string) file_get_contents(RemoteDocs::pathFor('demo')));
});

it('un error de red o HTML no pisa la copia local', function () use ($serve) {
    foreach ([[0, ''], [500, 'boom'], [200, '<!DOCTYPE html><html>login</html>'], [200, 'corto']] as [$st, $body]) {
        $serve($st, $body);
        $r = RemoteDocs::sync('demo')['demo'];
        assertEquals('error', $r['status'], "status {$st} debería ser error");
    }
    assertContains('2.7.0+bbbb', (string) file_get_contents(RemoteDocs::pathFor('demo')));
});

it('dry-run no escribe nada', function () use ($serve, $manual) {
    $serve(200, $manual('9.9.9'));
    assertEquals('updated', RemoteDocs::sync('demo', true)['demo']['status']);
    assertContains('2.7.0+bbbb', (string) file_get_contents(RemoteDocs::pathFor('demo')));
});

it('repone el archivo si lo editaron a mano aunque el remoto no cambió', function () use ($serve, $manual) {
    file_put_contents(RemoteDocs::pathFor('demo'), "# editado a mano\n");
    $serve(200, $manual('2.7.0+bbbb'));
    assertEquals('updated', RemoteDocs::sync('demo')['demo']['status']);
    assertContains('2.7.0+bbbb', (string) file_get_contents(RemoteDocs::pathFor('demo')));
});

it('los apagados se informan como disabled; una clave desconocida es error', function () use ($serve, $manual) {
    $serve(200, $manual('2.7.0+bbbb'));
    $all = RemoteDocs::sync();
    assertEquals('disabled', $all['off']['status']);
    assertEquals('error', RemoteDocs::sync('no-existe')['no-existe']['status']);
});

it('status marca vencido por antigüedad y no permite rutas con ..', function () use ($cfg, $tmpRoot) {
    assertFalse(RemoteDocs::status()['demo']['stale']);
    assertTrue(RemoteDocs::status(time() + 25 * 3600)['demo']['stale']);
    $bad = $cfg;
    $bad['docs']['demo']['file'] = '../../fuera.md';
    RemoteDocs::useConfig($bad, $tmpRoot);
    assertNull(RemoteDocs::pathFor('demo'));
    RemoteDocs::useConfig($cfg, $tmpRoot);
});

Http::resetTransport();
RemoteDocs::useConfig(null);
$cleanup($tmpRoot);
