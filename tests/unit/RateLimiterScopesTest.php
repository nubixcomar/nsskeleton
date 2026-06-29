<?php

declare(strict_types=1);

use App\Services\ApiToken;
use App\Services\RateLimiter;
use Core\Database;

group('ApiToken::normalizeScopes (G1)');

it('normaliza y filtra scopes', function () {
    assertEquals('read,write', ApiToken::normalizeScopes('read,write'));
    assertEquals('read', ApiToken::normalizeScopes('read'));
    assertEquals('write', ApiToken::normalizeScopes('write,foo'));
    assertEquals('read', ApiToken::normalizeScopes('basura')); // fallback a read
});

group('RateLimiter (feature · requiere MySQL)');

it('permite hasta el límite y luego bloquea', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $key = 'test:rl:' . bin2hex(random_bytes(4));
    Database::run('DELETE FROM rate_limits WHERE rkey = ?', [$key]);

    $r1 = RateLimiter::hit($key, 3);
    assertTrue($r1['allowed']);
    assertEquals(2, $r1['remaining']);

    RateLimiter::hit($key, 3); // 2
    $r3 = RateLimiter::hit($key, 3); // 3
    assertTrue($r3['allowed']);
    $r4 = RateLimiter::hit($key, 3); // 4 → excede
    assertFalse($r4['allowed']);

    Database::run('DELETE FROM rate_limits WHERE rkey = ?', [$key]);
});
