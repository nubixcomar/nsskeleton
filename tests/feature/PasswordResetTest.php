<?php

declare(strict_types=1);

use App\Services\PasswordReset;
use Core\Database;

group('PasswordReset (feature · requiere MySQL)');

$email = 'admin@nsskeleton.local';

it('crea token, valida, consume y queda invalidado', function () use ($email) {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $token = PasswordReset::createToken($email);
    assertNotNull($token);
    assertTrue(PasswordReset::valid($email, (string) $token));
    assertFalse(PasswordReset::valid($email, 'token-incorrecto'));

    // Reset a la misma contraseña del seed (no altera el estado para otros tests).
    assertTrue(PasswordReset::consume($email, (string) $token, 'admin1234'));
    assertFalse(PasswordReset::valid($email, (string) $token), 'el token debe invalidarse al consumirse');
});

it('un token expirado no es válido', function () use ($email) {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);
    Database::insert(
        'INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)',
        [$email, hash('sha256', 'x'), date('Y-m-d H:i:s', time() - 10)]
    );
    assertFalse(PasswordReset::valid($email, 'x'));
    Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);
});

it('email inexistente no genera token', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    assertNull(PasswordReset::createToken('no-existe@example.com'));
});
