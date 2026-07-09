<?php

declare(strict_types=1);

use App\Services\DbDoctor;
use Core\Database;

/**
 * v1.18 #3 — db:doctor. Diagnóstico de base de datos.
 * El grueso requiere MySQL; se saltea si no está.
 */

group('DbDoctor');

it('checks() devuelve resultados estructurados', function () {
    $checks = DbDoctor::checks();
    assertTrue(count($checks) >= 1);
    foreach ($checks as $c) {
        assertTrue(isset($c['name'], $c['ok'], $c['severity'], $c['detail']));
        assertTrue(in_array($c['severity'], ['error', 'warn', 'info'], true));
    }
    // Siempre chequea el driver (no requiere conexión).
    $names = array_column($checks, 'name');
    assertTrue(in_array('Driver pdo_mysql disponible', $names, true));
});

it('con MySQL disponible, incluye conexión, versión y migraciones', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    $names = array_column(DbDoctor::checks(), 'name');
    assertTrue(in_array('Conexión a la base', $names, true));
    assertTrue(in_array('Versión del servidor', $names, true));
    assertTrue(in_array('Migraciones aplicadas (tabla settings)', $names, true));
});

it('ok() respeta la severidad', function () {
    // warn que falla no tumba
    assertTrue(DbDoctor::ok([
        ['name' => 'x', 'ok' => false, 'severity' => 'warn', 'detail' => ''],
    ]));
    // error que falla sí tumba
    assertFalse(DbDoctor::ok([
        ['name' => 'y', 'ok' => false, 'severity' => 'error', 'detail' => ''],
    ]));
});

it('en dev (con MySQL) ok() es true', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    assertTrue(DbDoctor::ok());
});
