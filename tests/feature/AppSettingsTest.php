<?php

declare(strict_types=1);

use App\Services\AppSettings;
use App\Services\Settings;
use Core\Database;

group('AppSettings (feature · requiere MySQL)');

it('refleja el nombre guardado en settings', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    Settings::set('app.name', 'Mi Empresa SA', 'app');
    assertEquals('Mi Empresa SA', AppSettings::name());

    // Restaurar vía Settings (invalida la caché correctamente).
    Settings::set('app.name', 'nsSkeleton', 'app');
    assertEquals('nsSkeleton', AppSettings::name());
    Database::run('DELETE FROM settings WHERE `key` = ?', ['app.name']);
});

it('refleja la zona horaria guardada', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    Settings::set('app.timezone', 'Europe/Madrid', 'app');
    assertEquals('Europe/Madrid', AppSettings::timezone());

    Database::run('DELETE FROM settings WHERE `key` = ?', ['app.timezone']);
});
