<?php

declare(strict_types=1);

use Core\Auth;
use Core\Guard;

/**
 * v1.17 #7 — Auth multi-guard / login por rol (hallazgo 1.9).
 * Guards independientes (admin/brand/customer) con sesión, tabla, prefijo y redirect propios.
 */

group('Guards de autenticación');

/** Limpia las claves de sesión de todos los guards (aislamiento). */
$resetSessions = static function (): void {
    foreach (['_auth_user_id', '_auth_brand_id', '_auth_customer_id'] as $k) {
        unset($_SESSION[$k]);
    }
};

it('el guard default es admin y usa la tabla/clave históricas', function () use ($resetSessions) {
    $resetSessions();
    $g = Guard::default();
    assertEquals('admin', $g->name());
    assertEquals('admin_users', $g->table());
    assertEquals('_auth_user_id', $g->sessionKey()); // retrocompat con Core\Auth
});

it('cada guard resuelve su config (tabla, sesión, path, redirect)', function () {
    $brand = Guard::for('brand');
    assertEquals('brand_users', $brand->table());
    assertEquals('_auth_brand_id', $brand->sessionKey());
    assertEquals('/panel/login', $brand->loginPath());
    assertEquals('/panel', $brand->redirect());

    $cust = Guard::for('customer');
    assertEquals('_auth_customer_id', $cust->sessionKey());
    assertEquals('/cuenta', $cust->redirect());
});

it('un guard no definido lanza excepción', function () {
    try {
        Guard::for('inexistente');
        assertTrue(false, 'debía lanzar');
    } catch (\InvalidArgumentException $e) {
        assertContains('inexistente', $e->getMessage());
    }
});

it('sesiones aisladas: login en admin y brand no se pisan', function () use ($resetSessions) {
    $resetSessions();
    Guard::for('admin')->login(5);
    Guard::for('brand')->login(7);

    assertTrue(Guard::for('admin')->check());
    assertTrue(Guard::for('brand')->check());
    assertEquals(5, Guard::for('admin')->id());
    assertEquals(7, Guard::for('brand')->id());
    assertFalse(Guard::for('customer')->check());
    $resetSessions();
});

it('logout de un guard no afecta a los demás', function () use ($resetSessions) {
    $resetSessions();
    Guard::for('admin')->login(5);
    Guard::for('brand')->login(7);

    Guard::for('admin')->logout();
    assertFalse(Guard::for('admin')->check());
    assertTrue(Guard::for('brand')->check(), 'brand sigue logueado');
    $resetSessions();
});

it('Core\\Auth es fachada del guard default (misma sesión)', function () use ($resetSessions) {
    $resetSessions();
    Auth::login(9);
    assertTrue(Auth::check());
    assertEquals(9, Auth::id());
    // El guard admin ve la misma sesión.
    assertTrue(Guard::for('admin')->check());
    assertEquals(9, Guard::for('admin')->id());
    Auth::logout();
    assertFalse(Auth::check());
    $resetSessions();
});

it('Auth::hash produce un hash verificable', function () {
    $h = Auth::hash('secreto123');
    assertTrue(password_verify('secreto123', $h));
    assertFalse(password_verify('otro', $h));
});
