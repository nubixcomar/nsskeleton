<?php

declare(strict_types=1);

use Core\Crypto;

group('Crypto (cifrado en reposo)');

// Garantiza una clave conocida para el test (independiente del .env).
$_ENV['APP_KEY'] = Crypto::generateKey();

it('generateKey tiene formato base64: y 32 bytes', function () {
    $k = Crypto::generateKey();
    assertContains('base64:', $k);
    assertEquals(32, strlen(base64_decode(substr($k, 7), true) ?: ''));
});

it('encrypt produce un payload marcado y distinto del plano', function () {
    $c = Crypto::encrypt('hunter2');
    assertTrue(Crypto::isEncrypted($c));
    assertTrue($c !== 'hunter2');
});

it('decrypt(encrypt(x)) === x', function () {
    assertEquals('s3cr3t-áé', Crypto::decrypt(Crypto::encrypt('s3cr3t-áé')));
});

it('maybeDecrypt deja pasar texto plano', function () {
    assertEquals('plano', Crypto::maybeDecrypt('plano'));
});

it('un payload alterado no se descifra (GCM autenticado)', function () {
    $c = Crypto::encrypt('dato');
    // Alterar el último carácter del base64.
    $tampered = substr($c, 0, -1) . ($c[strlen($c) - 1] === 'A' ? 'B' : 'A');
    assertNull(Crypto::decrypt($tampered));
});

it('sin APP_KEY válida, encrypt degrada a texto plano', function () {
    $prev = $_ENV['APP_KEY'] ?? null;
    $_ENV['APP_KEY'] = '';
    $r = Crypto::encrypt('x');
    $_ENV['APP_KEY'] = $prev;
    assertEquals('x', $r);
});
