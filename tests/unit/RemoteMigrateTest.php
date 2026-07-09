<?php

declare(strict_types=1);

use App\Controllers\MigrateController;
use App\Services\RemoteMigrate;
use Core\Request;

/**
 * v1.18 #4 — Migración remota protegida por token (deshabilitada por default).
 */

group('Migración remota (token)');

$reset = static function (): void {
    unset($_ENV['MIGRATE_TOKEN']);
    putenv('MIGRATE_TOKEN');
};

it('deshabilitada por defecto (sin MIGRATE_TOKEN)', function () use ($reset) {
    $reset();
    assertFalse(RemoteMigrate::enabled());
    assertFalse(RemoteMigrate::tokenValid('lo-que-sea'));
    $reset();
});

it('habilitada solo con token; valida constant-time', function () use ($reset) {
    $reset();
    $_ENV['MIGRATE_TOKEN'] = 'tok-super-secreto-123';
    assertTrue(RemoteMigrate::enabled());
    assertTrue(RemoteMigrate::tokenValid('tok-super-secreto-123'));
    assertFalse(RemoteMigrate::tokenValid('otro'));
    assertFalse(RemoteMigrate::tokenValid(''));
    assertFalse(RemoteMigrate::tokenValid(null));
    $reset();
});

it('el endpoint responde 404 cuando está deshabilitado', function () use ($reset) {
    $reset();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/_migrate';
    $req = Request::capture();
    $res = (new MigrateController())->handle($req);
    assertEquals(404, $res->statusCode(), 'sin token configurado → 404');
    $reset();
});

it('el endpoint responde 404 con token inválido', function () use ($reset) {
    $reset();
    $_ENV['MIGRATE_TOKEN'] = 'correcto';
    $_GET['token'] = 'incorrecto';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/_migrate?token=incorrecto';
    $req = Request::capture();
    $res = (new MigrateController())->handle($req);
    assertEquals(404, $res->statusCode(), 'token inválido → 404');
    unset($_GET['token']);
    $reset();
});
