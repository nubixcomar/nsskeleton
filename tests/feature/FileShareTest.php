<?php

declare(strict_types=1);

use App\Services\FileShare;
use Core\Database;

group('FileShare (feature · requiere MySQL)');

it('comparte, resuelve por token, revoca', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $rel = 'zz_test/archivo.txt';
    Database::run('DELETE FROM file_shares WHERE rel_path = ?', [$rel]);

    $token = FileShare::share($rel);
    assertEquals(32, strlen($token));

    // idempotente: vuelve a dar el mismo token
    assertEquals($token, FileShare::share($rel));

    $row = FileShare::byToken($token);
    assertNotNull($row);
    assertEquals($rel, $row['rel_path']);

    FileShare::unshare($rel);
    assertNull(FileShare::byToken($token));
});

it('byToken rechaza tokens con formato inválido', function () {
    assertNull(FileShare::byToken('no-es-un-token'));
    assertNull(FileShare::byToken(''));
});
