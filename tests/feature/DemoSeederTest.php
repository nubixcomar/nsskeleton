<?php

declare(strict_types=1);

use App\Services\DemoSeeder;
use Core\Database;

group('DemoSeeder (feature · requiere MySQL)');

it('seed crea datos demo, es idempotente y undo los limpia', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    DemoSeeder::undo(); // partir de estado limpio
    assertFalse(DemoSeeder::isSeeded());

    $r1 = DemoSeeder::seed();
    assertTrue(DemoSeeder::isSeeded());
    assertTrue($r1['admins'] >= 2, 'debería crear los admins demo');
    assertTrue($r1['cron'] >= 2, 'debería crear las tareas demo');

    // Idempotente: no duplica.
    $r2 = DemoSeeder::seed();
    assertEquals(0, $r2['admins']);
    assertEquals(0, $r2['cron']);

    // Undo limpia.
    $undo = DemoSeeder::undo();
    assertTrue($undo['admins'] >= 2);
    assertFalse(DemoSeeder::isSeeded());
});
