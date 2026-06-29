<?php

declare(strict_types=1);

use App\Services\Rbac;
use App\Services\Settings;
use Core\Database;

group('Rbac overrides (F2 · requiere MySQL)');

it('override de rol por panel cambia los permisos', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    // viewer por defecto: solo dashboard.view
    $viewer = ['id' => 999999, 'role' => 'viewer'];
    assertFalse(Rbac::can('files.manage', $viewer));

    Rbac::setRolePermissions('viewer', ['dashboard.view', 'files.manage']);
    assertTrue(Rbac::can('files.manage', $viewer));

    // limpiar override
    Database::run("DELETE FROM settings WHERE `group` = 'rbac_roles' AND `key` = 'rbac_roles.viewer'");
});

it('override por usuario: deny bloquea aunque el rol lo permita', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $admin = Database::selectOne("SELECT id FROM admin_users WHERE role = 'superadmin' OR role = 'admin' LIMIT 1");
    if ($admin === null) {
        return 'skip';
    }
    $uid = (int) $admin['id'];
    $user = ['id' => $uid, 'role' => 'superadmin'];

    assertTrue(Rbac::can('cron.manage', $user)); // superadmin tiene todo

    Rbac::setUserPermission($uid, 'cron.manage', false); // deny
    assertFalse(Rbac::can('cron.manage', $user));

    Rbac::setUserPermission($uid, 'cron.manage', null); // heredar de nuevo
    assertTrue(Rbac::can('cron.manage', $user));
});

it('override por usuario: allow otorga aunque el rol no lo dé', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $u = Database::selectOne('SELECT id FROM admin_users ORDER BY id DESC LIMIT 1');
    $uid = (int) $u['id'];
    $user = ['id' => $uid, 'role' => 'viewer'];

    assertFalse(Rbac::can('api.manage', $user));
    Rbac::setUserPermission($uid, 'api.manage', true);
    assertTrue(Rbac::can('api.manage', $user));
    Rbac::setUserPermission($uid, 'api.manage', null);
    assertFalse(Rbac::can('api.manage', $user));
});
