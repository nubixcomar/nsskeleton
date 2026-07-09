<?php

declare(strict_types=1);

use Core\Request;
use Core\Session;
use Core\Url;

/**
 * v1.16 #5 (APP_BASE_PATH) y #6 (session.save_path → storage/sessions/).
 * Derivan de los hallazgos 1.3 (SCRIPT_NAME mal reportado en nginx alias) y
 * 1.4 (save_path global no escribible en pools PHP-FPM aislados).
 */

group('Deploy · APP_BASE_PATH (base de URL configurable)');

$resetBase = static function (): void {
    unset($_ENV['APP_BASE_PATH']);
    putenv('APP_BASE_PATH');
};

it('APP_BASE_PATH override el prefijo base', function () use ($resetBase) {
    $resetBase();
    $_ENV['APP_BASE_PATH'] = '/app';
    assertEquals('/app', Url::base());
    assertEquals('/app/css/x.css', Url::to('/css/x.css'));
    $resetBase();
});

it('APP_BASE_PATH normaliza barras sobrantes', function () use ($resetBase) {
    $resetBase();
    $_ENV['APP_BASE_PATH'] = 'sub/dir/';
    assertEquals('/sub/dir', Url::base());
    $resetBase();
});

it('APP_BASE_PATH="/" equivale a raíz (base vacía)', function () use ($resetBase) {
    $resetBase();
    $_ENV['APP_BASE_PATH'] = '/';
    assertEquals('', Url::base());
    $resetBase();
});

it('Request quita el prefijo APP_BASE_PATH del path', function () use ($resetBase) {
    $resetBase();
    $_ENV['APP_BASE_PATH'] = '/app';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/app/admin/login';
    $r = Request::capture();
    assertEquals('/admin/login', $r->path());
    $resetBase();
});

it('sin APP_BASE_PATH no rompe (deriva de SCRIPT_NAME)', function () use ($resetBase) {
    $resetBase();
    // No debe lanzar; devuelve algún string (posiblemente vacío en CLI).
    $base = Url::base();
    assertTrue(is_string($base));
    $resetBase();
});

group('Deploy · session.save_path a storage/sessions');

it('resolveSavePath usa storage/sessions por defecto', function () {
    unset($_ENV['SESSION_PATH']); putenv('SESSION_PATH');
    $path = Session::resolveSavePath();
    assertNotNull($path);
    assertContains('storage/sessions', str_replace('\\', '/', (string) $path));
});

it('resolveSavePath respeta SESSION_PATH del env', function () {
    $_ENV['SESSION_PATH'] = 'C:/tmp/mis-sesiones/';
    $path = Session::resolveSavePath();
    assertEquals('C:/tmp/mis-sesiones', $path);
    unset($_ENV['SESSION_PATH']); putenv('SESSION_PATH');
});

it('el directorio storage/sessions existe (creado por el core/installer)', function () {
    $dir = BASE_PATH . '/storage/sessions';
    assertTrue(is_dir($dir), 'system/storage/sessions debe existir');
});
