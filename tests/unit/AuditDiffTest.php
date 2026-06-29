<?php

declare(strict_types=1);

use App\Services\Audit;

group('Audit::diff (F4)');

it('detecta solo los campos cambiados', function () {
    $diff = Audit::diff(
        ['name' => 'Ana', 'role' => 'viewer', 'active' => 1],
        ['name' => 'Ana', 'role' => 'admin', 'active' => 0]
    );
    assertCount(2, $diff);
    assertEquals('viewer', $diff['role']['old']);
    assertEquals('admin', $diff['role']['new']);
    assertTrue(isset($diff['active']));
    assertFalse(isset($diff['name']));
});

it('ignora password/created_at/updated_at por defecto', function () {
    $diff = Audit::diff(
        ['password' => 'hashA', 'updated_at' => '2020', 'x' => '1'],
        ['password' => 'hashB', 'updated_at' => '2021', 'x' => '2']
    );
    assertCount(1, $diff);
    assertTrue(isset($diff['x']));
});

it('sin cambios devuelve vacío', function () {
    assertEquals(0, count(Audit::diff(['a' => 1], ['a' => 1])));
});

it('considera campos nuevos o ausentes', function () {
    $diff = Audit::diff(['a' => 1], ['a' => 1, 'b' => 2]);
    assertTrue(isset($diff['b']));
    assertEquals(null, $diff['b']['old']);
    assertEquals(2, $diff['b']['new']);
});
