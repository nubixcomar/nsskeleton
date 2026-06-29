<?php

declare(strict_types=1);

use App\Services\Settings;
use Core\Crypto;
use Core\Database;

group('Settings secretos (feature · requiere MySQL)');

it('setSecret cifra en reposo y get descifra (round-trip)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $_ENV['APP_KEY'] = Crypto::generateKey();

    Settings::setSecret('test.secret', 'p@ss-áéí', 'test');

    // Lo que se lee por la API viene descifrado.
    assertEquals('p@ss-áéí', Settings::get('test.secret'));

    // Lo que está en la tabla está cifrado (no es texto plano).
    $row = Database::selectOne('SELECT `value` FROM settings WHERE `key` = ?', ['test.secret']);
    assertNotNull($row);
    assertTrue(Crypto::isEncrypted((string) $row['value']));

    // Limpieza.
    Database::run('DELETE FROM settings WHERE `key` = ?', ['test.secret']);
});
