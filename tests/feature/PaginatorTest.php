<?php

declare(strict_types=1);

use App\Services\Paginator;
use Core\Database;

group('Paginator::paginate (feature · requiere MySQL)');

it('pagina admin_users respetando perPage', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $p = Paginator::paginate('admin_users', ['perPage' => 1, 'page' => 1, 'order' => 'id ASC']);
    assertTrue($p['total'] >= 1);
    assertTrue(count($p['rows']) <= 1);
    assertEquals(1, $p['page']);
});

it('busca por email (encuentra el admin del seed)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $p = Paginator::paginate('admin_users', [
        'search'     => 'admin@nsskeleton',
        'searchable' => ['name', 'email'],
    ]);
    assertTrue($p['total'] >= 1);
});

it('búsqueda sin coincidencias devuelve 0', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $p = Paginator::paginate('admin_users', [
        'search'     => 'zzz-no-existe-xyz',
        'searchable' => ['name', 'email'],
    ]);
    assertEquals(0, $p['total']);
    assertEquals([], $p['rows']);
});
