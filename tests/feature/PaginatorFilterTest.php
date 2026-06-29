<?php

declare(strict_types=1);

use App\Services\Paginator;
use Core\Database;

group('Paginator filter (E4 · requiere MySQL)');

it('el filtro fijo se aplica al WHERE', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    $all = Paginator::paginate('admin_users', ['filter' => 'id IS NOT NULL']);
    assertTrue($all['total'] >= 1);

    $none = Paginator::paginate('admin_users', ['filter' => '1 = 0']);
    assertEquals(0, $none['total']);
});

it('combina búsqueda + filtro con AND', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    // búsqueda imposible + filtro verdadero → 0 (AND)
    $r = Paginator::paginate('admin_users', [
        'search'     => 'zzz_no_existe_zzz',
        'searchable' => ['name', 'email'],
        'filter'     => 'id IS NOT NULL',
    ]);
    assertEquals(0, $r['total']);
});
