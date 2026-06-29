<?php

declare(strict_types=1);

use App\Services\EmailQueue;
use Core\Database;

group('EmailQueue (feature · requiere MySQL)');

it('encola un email como pending', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $id = EmailQueue::push('dest@example.com', 'Hola', '<p>contenido</p>');
    $row = Database::selectOne('SELECT * FROM email_queue WHERE id = ?', [$id]);
    assertNotNull($row);
    assertEquals('pending', $row['status']);
    assertEquals(0, (int) $row['attempts']);

    Database::run('DELETE FROM email_queue WHERE id = ?', [$id]);
});

it('process reintenta y marca failed tras el máximo (sin SMTP configurado)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    // Sin SMTP configurado, el envío falla → se acumulan intentos.
    if (\App\Services\AppSettings::name() === '' /* nunca */) {
        return 'skip';
    }

    $id = EmailQueue::push('dest@example.com', 'Cola test', '<p>x</p>');

    // 3 pasadas → 3 intentos → failed.
    for ($i = 0; $i < 3; $i++) {
        EmailQueue::process(10);
    }

    $row = Database::selectOne('SELECT status, attempts FROM email_queue WHERE id = ?', [$id]);
    assertEquals('failed', $row['status']);
    assertEquals(3, (int) $row['attempts']);

    Database::run('DELETE FROM email_queue WHERE id = ?', [$id]);
    // Limpia los email_log generados por los intentos fallidos.
    Database::run('DELETE FROM email_log WHERE to_address = ? AND subject = ?', ['dest@example.com', 'Cola test']);
});
