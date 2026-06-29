<?php

declare(strict_types=1);

use App\Services\LoginThrottle;
use Core\Database;

group('LoginThrottle (feature · requiere MySQL)');

it('bloquea tras 5 intentos fallidos y se limpia', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $id = 'throttle-test@example.com';
    LoginThrottle::clear($id);

    assertFalse(LoginThrottle::tooManyAttempts($id), 'no debería estar bloqueado al inicio');

    for ($i = 0; $i < 5; $i++) {
        LoginThrottle::hit($id);
    }

    assertTrue(LoginThrottle::tooManyAttempts($id), 'debería bloquear tras 5 intentos');
    assertTrue(LoginThrottle::secondsRemaining($id) > 0);

    LoginThrottle::clear($id);
    assertFalse(LoginThrottle::tooManyAttempts($id), 'clear debería desbloquear');
});

it('no bloquea con menos del máximo de intentos', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $id = 'throttle-test2@example.com';
    LoginThrottle::clear($id);
    LoginThrottle::hit($id);
    LoginThrottle::hit($id);
    assertFalse(LoginThrottle::tooManyAttempts($id));
    LoginThrottle::clear($id);
});
