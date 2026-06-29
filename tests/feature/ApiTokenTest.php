<?php

declare(strict_types=1);

use App\Services\ApiToken;
use Core\Database;

group('ApiToken (feature · requiere MySQL)');

it('genera, valida y revoca un token', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $admin = Database::selectOne('SELECT id FROM admin_users LIMIT 1');
    if ($admin === null) {
        return 'skip';
    }
    $adminId = (int) $admin['id'];

    $raw = ApiToken::generate($adminId, 'test-token');
    assertTrue(str_starts_with($raw, 'nsk_'));

    $valid = ApiToken::validate($raw);
    assertNotNull($valid);
    assertEquals($adminId, (int) $valid['id']);

    assertNull(ApiToken::validate('nsk_token_invalido'));

    $row = Database::selectOne('SELECT id FROM api_tokens WHERE token_hash = ?', [hash('sha256', $raw)]);
    ApiToken::revoke((int) $row['id']);
    assertNull(ApiToken::validate($raw), 'tras revocar, el token no debe validar');
});
