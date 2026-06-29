<?php

declare(strict_types=1);

use App\Services\RelationOptions;
use Core\Database;

group('RelationOptions (feature · requiere MySQL)');

it('labelColumn elige una columna legible', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    // admin_users tiene name + email → debería elegir "name".
    assertEquals('name', RelationOptions::labelColumn('admin_users'));
});

it('forTable devuelve id => etiqueta', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    $opts = RelationOptions::forTable('admin_users');
    assertTrue(count($opts) >= 1);
    foreach ($opts as $id => $label) {
        assertTrue(is_int($id));
        assertTrue($label !== '');
        break;
    }
});

it('forTable de tabla inexistente no rompe', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    assertEquals(0, count(RelationOptions::forTable('tabla_que_no_existe_xyz')));
});

it('rechaza nombres de tabla inseguros', function () {
    assertEquals('id', RelationOptions::labelColumn('admin_users; DROP TABLE x'));
});
