<?php

declare(strict_types=1);

use App\Services\Webhook;
use Core\Database;

group('Webhook::sign (G4)');

it('firma HMAC-SHA256 con prefijo sha256=', function () {
    $sig = Webhook::sign('s3cr3t', '{"a":1}');
    assertEquals('sha256=' . hash_hmac('sha256', '{"a":1}', 's3cr3t'), $sig);
});

it('firmas distintas para secretos distintos', function () {
    assertFalse(Webhook::sign('a', 'x') === Webhook::sign('b', 'x'));
});

group('Webhook::dispatch (feature · requiere MySQL)');

it('encola una entrega por webhook activo del evento', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    Database::run("DELETE FROM webhooks WHERE event = 'zz.test'");
    Database::run("DELETE FROM jobs WHERE handler = 'webhook:deliver' AND payload LIKE '%zz.test%'");

    $a = Webhook::subscribe('zz.test', 'https://ejemplo.com/a');
    $b = Webhook::subscribe('zz.test', 'https://ejemplo.com/b');
    Webhook::toggle($b); // desactiva b

    $n = Webhook::dispatch('zz.test', ['x' => 1]);
    assertEquals(1, $n); // solo el activo

    $jobs = Database::select("SELECT * FROM jobs WHERE handler = 'webhook:deliver' AND payload LIKE '%zz.test%'");
    assertCount(1, $jobs);

    // limpieza
    Database::run("DELETE FROM webhooks WHERE event = 'zz.test'");
    Database::run("DELETE FROM jobs WHERE handler = 'webhook:deliver' AND payload LIKE '%zz.test%'");
});
