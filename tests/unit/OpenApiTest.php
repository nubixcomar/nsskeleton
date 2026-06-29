<?php

declare(strict_types=1);

use App\Services\OpenApiGenerator;

group('OpenApiGenerator (G2)');

$spec = OpenApiGenerator::spec();

it('declara OpenAPI 3.0.3 con info', function () use ($spec) {
    assertEquals('3.0.3', $spec['openapi']);
    assertTrue(isset($spec['info']['title']));
    assertTrue(isset($spec['info']['version']));
});

it('define el esquema de seguridad Bearer', function () use ($spec) {
    assertEquals('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
    assertEquals('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
});

it('cada recurso registrado expone get/post en su listado', function () use ($spec) {
    // El core no trae recursos por defecto; el test es robusto si no hay ninguno.
    foreach (array_keys($spec['paths']) as $p) {
        if (str_contains($p, '{id}')) {
            continue;
        }
        assertTrue(isset($spec['paths'][$p]['get']), "{$p} debería tener GET");
        assertTrue(isset($spec['paths'][$p]['post']), "{$p} debería tener POST");
    }
    assertTrue(true);
});

it('los listados documentan parámetros de paginación', function () use ($spec) {
    foreach (array_keys($spec['paths']) as $p) {
        if (str_contains($p, '{id}')) {
            continue;
        }
        $names = array_map(static fn ($x) => $x['name'], $spec['paths'][$p]['get']['parameters']);
        assertTrue(in_array('page', $names, true));
        assertTrue(in_array('per_page', $names, true));
        assertTrue(in_array('q', $names, true));
    }
    assertTrue(true);
});
