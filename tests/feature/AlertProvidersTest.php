<?php

declare(strict_types=1);

use App\Alerts\Providers\FailedJobsAlertProvider;
use App\Services\AlertService;
use Core\Database;

group('Alert providers (feature · requiere MySQL)');

it('FailedJobsAlertProvider alerta cuando hay jobs failed', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    Database::run("DELETE FROM jobs WHERE handler = 'zz:alert:test'");

    // sin jobs failed → sin alerta
    $p = new FailedJobsAlertProvider();
    $before = $p->collect();

    Database::run("INSERT INTO jobs (handler, status, max_attempts, available_at) VALUES ('zz:alert:test', 'failed', 1, NOW())");
    $after = $p->collect();

    assertTrue(count($after) >= 1);
    assertEquals('danger', $after[0]['severity']);
    // AlertService::all las incluye y ordena
    $all = AlertService::all();
    $hasDanger = false;
    foreach ($all as $a) {
        if (($a['severity'] ?? '') === 'danger') {
            $hasDanger = true;
        }
    }
    assertTrue($hasDanger);

    Database::run("DELETE FROM jobs WHERE handler = 'zz:alert:test'");
});
