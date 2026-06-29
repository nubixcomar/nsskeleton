<?php

declare(strict_types=1);

use App\Services\Jobs;

group('Jobs (callables internos)');

it('registra los jobs por defecto', function () {
    assertTrue(Jobs::has('demo:ping'));
    assertTrue(in_array('cache:clear', Jobs::names(), true));
});

it('ejecuta demo:ping y captura su salida', function () {
    $r = Jobs::run('demo:ping');
    assertTrue($r['ok']);
    assertEquals(0, $r['code']);
    assertContains('pong', $r['output']);
});

it('un job inexistente devuelve error sin excepción', function () {
    $r = Jobs::run('no:existe');
    assertFalse($r['ok']);
    assertEquals(1, $r['code']);
});
