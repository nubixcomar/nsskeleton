<?php

declare(strict_types=1);

use App\Services\AlertService;

group('AlertService::sort (I4)');

it('ordena por severidad: danger < warning < info', function () {
    $sorted = AlertService::sort([
        ['severity' => 'info', 'title' => 'i'],
        ['severity' => 'danger', 'title' => 'd'],
        ['severity' => 'warning', 'title' => 'w'],
    ]);
    assertEquals('danger', $sorted[0]['severity']);
    assertEquals('warning', $sorted[1]['severity']);
    assertEquals('info', $sorted[2]['severity']);
});

it('severidad desconocida va al final', function () {
    $sorted = AlertService::sort([
        ['severity' => 'rara', 'title' => 'x'],
        ['severity' => 'danger', 'title' => 'd'],
    ]);
    assertEquals('danger', $sorted[0]['severity']);
});

it('providers() devuelve la lista registrada', function () {
    $p = AlertService::providers();
    assertTrue(in_array(\App\Alerts\Providers\FailedJobsAlertProvider::class, $p, true));
});
