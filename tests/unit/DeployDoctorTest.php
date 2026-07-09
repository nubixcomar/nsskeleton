<?php

declare(strict_types=1);

use App\Services\DeployDoctor;

/**
 * v1.16 #7 — deploy:doctor. Diagnóstico "listo para producción" (hallazgo 1.3).
 */

group('Deploy · doctor');

it('checks() devuelve una lista de resultados estructurados', function () {
    $checks = DeployDoctor::checks();
    assertTrue(count($checks) >= 10, 'al menos 10 chequeos');
    foreach ($checks as $c) {
        assertTrue(isset($c['name'], $c['ok'], $c['severity'], $c['detail']), 'estructura de chequeo');
        assertTrue(is_bool($c['ok']));
        assertTrue(in_array($c['severity'], ['error', 'warn', 'info'], true));
    }
});

it('valida versión de PHP y extensiones requeridas', function () {
    $checks = DeployDoctor::checks();
    $names = array_column($checks, 'name');
    assertTrue(in_array('PHP >= ' . DeployDoctor::MIN_PHP, $names, true));
    foreach (DeployDoctor::REQUIRED_EXT as $ext) {
        assertTrue(in_array("Extensión {$ext}", $names, true), "chequea {$ext}");
    }
});

it('incluye docroot, storage y session.save_path', function () {
    $names = array_column(DeployDoctor::checks(), 'name');
    assertTrue(in_array('Docroot = system/public', $names, true));
    assertTrue(in_array('storage/sessions escribible', $names, true));
    assertTrue(in_array('session.save_path escribible', $names, true));
});

it('ok() es true en el entorno de dev (todo verde) y respeta severidad', function () {
    // En dev todos los chequeos error pasan.
    assertTrue(DeployDoctor::ok());

    // Un warn que falla NO tumba ok().
    $soloWarn = [
        ['name' => 'x', 'ok' => false, 'severity' => 'warn', 'detail' => ''],
        ['name' => 'y', 'ok' => true,  'severity' => 'error', 'detail' => ''],
    ];
    assertTrue(DeployDoctor::ok($soloWarn));

    // Un error que falla SÍ la tumba.
    $conError = [
        ['name' => 'z', 'ok' => false, 'severity' => 'error', 'detail' => ''],
    ];
    assertFalse(DeployDoctor::ok($conError));
});
