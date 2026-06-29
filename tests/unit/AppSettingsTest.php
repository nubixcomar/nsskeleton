<?php

declare(strict_types=1);

use App\Services\AppSettings;

group('AppSettings (fallback a .env)');

it('name cae a APP_NAME del entorno', function () {
    assertEquals('nsSkeleton', AppSettings::name());
});

it('timezone no es vacío', function () {
    assertTrue(AppSettings::timezone() !== '');
});

it('logo es null cuando no hay setting', function () {
    assertNull(AppSettings::logo());
});
