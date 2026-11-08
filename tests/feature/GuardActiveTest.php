<?php

declare(strict_types=1);

/**
 * PL-002/F1 — Guard::user() revalida el usuario en cada llamada (BR-001 CS4, CS5).
 * Un usuario desactivado o borrado deja de estar logueado; nunca se exponen password ni totp_secret.
 * it + finally (no itDb): se limpia la fila y la sesión explícitamente.
 */

use Core\Auth;
use Core\Database;
use Core\Guard;

group('Guard · usuario activo (PL-002/F1)');

it('user() es null para inactivos o borrados y no expone password ni totp_secret', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    $tag = 'pl002f1_' . bin2hex(random_bytes(4));
    $id = 0;
    try {
        Database::run(
            'INSERT INTO admin_users (name, username, email, password, role, active, totp_secret) VALUES (?, ?, ?, ?, ?, 1, ?)',
            ['Test F1', $tag, $tag . '@test.local', Auth::hash('x-' . $tag), 'viewer', 'enc:prueba']
        );
        $id = (int) Database::connection()->lastInsertId();
        $guard = Guard::for('admin');
        $guard->login($id);

        $u = $guard->user();
        assertTrue($u !== null, 'el usuario activo debe estar logueado');
        assertFalse(array_key_exists('password', $u), 'no debe exponer password');
        assertFalse(array_key_exists('totp_secret', $u), 'no debe exponer totp_secret');
        assertTrue($guard->check(), 'check() sigue mirando solo la sesión');

        Database::run('UPDATE admin_users SET active = 0 WHERE id = ?', [$id]);
        assertTrue($guard->user() === null, 'inactivo → user() null');

        Database::run('UPDATE admin_users SET active = 1 WHERE id = ?', [$id]);
        assertTrue($guard->user() !== null, 'reactivado → vuelve a estar logueado');

        Database::run('DELETE FROM admin_users WHERE id = ?', [$id]);
        assertTrue($guard->user() === null, 'borrado → user() null');
    } finally {
        if ($id > 0) {
            Database::run('DELETE FROM admin_users WHERE id = ?', [$id]);
        }
        unset($_SESSION['_auth_user_id']);
    }
});
