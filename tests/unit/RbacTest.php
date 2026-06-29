<?php

declare(strict_types=1);

use App\Services\Rbac;

group('Rbac (roles y permisos)');

it('superadmin puede todo (*)', function () {
    assertTrue(Rbac::can('admins.manage', ['role' => 'superadmin']));
    assertTrue(Rbac::can('cualquier.cosa', ['role' => 'superadmin']));
});

it('admin tiene permisos de gestión pero no es comodín', function () {
    assertTrue(Rbac::can('settings.manage', ['role' => 'admin']));
    assertTrue(Rbac::can('audit.view', ['role' => 'admin']));
});

it('viewer solo ve el dashboard', function () {
    assertTrue(Rbac::can('dashboard.view', ['role' => 'viewer']));
    assertFalse(Rbac::can('admins.manage', ['role' => 'viewer']));
    assertFalse(Rbac::can('settings.manage', ['role' => 'viewer']));
});

it('editor gestiona archivos/módulos pero no admins', function () {
    assertTrue(Rbac::can('files.manage', ['role' => 'editor']));
    assertFalse(Rbac::can('admins.manage', ['role' => 'editor']));
});

it('rol inexistente o vacío no tiene permisos', function () {
    assertFalse(Rbac::can('dashboard.view', ['role' => 'no-existe']));
    assertFalse(Rbac::can('dashboard.view', ['role' => '']));
    assertFalse(Rbac::can('dashboard.view', []));
});

it('roles() y permissionsFor() son consistentes', function () {
    $roles = Rbac::roles();
    assertTrue(in_array('superadmin', $roles, true));
    assertTrue(in_array('viewer', $roles, true));
    assertEquals(['dashboard.view'], Rbac::permissionsFor('viewer'));
});
