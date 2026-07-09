<?php

declare(strict_types=1);

use App\Services\AppSettings;
use Core\Env;

group('AppSettings (fallback a .env)');

it('name cae a APP_NAME del entorno (no hardcodeado)', function () {
    // v1.17 #4 (hallazgo 1.7): leer el nombre esperado del entorno, para que renombrar
    // la app (cambiar APP_NAME) no rompa la suite.
    $expected = (string) Env::get('APP_NAME', 'nsSkeleton');
    assertEquals($expected, AppSettings::name());
});

it('timezone no es vacío', function () {
    assertTrue(AppSettings::timezone() !== '');
});

it('logo es null cuando no hay setting', function () {
    assertNull(AppSettings::logo());
});
