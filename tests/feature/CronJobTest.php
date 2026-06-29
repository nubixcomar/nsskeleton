<?php

declare(strict_types=1);

use App\Services\CronRunner;
use Core\Database;

group('Cron jobs + lock (feature · requiere MySQL)');

it('runTask ejecuta un job interno y registra la corrida', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $id = Database::insert(
        'INSERT INTO cron_tasks (name, command, schedule, active) VALUES (?, ?, ?, 1)',
        ['Test ping', 'job:demo:ping', '*/5 * * * *']
    );
    $task = Database::selectOne('SELECT * FROM cron_tasks WHERE id = ?', [$id]);

    $result = CronRunner::runTask($task);
    assertEquals('success', $result['status']);

    $task2 = Database::selectOne('SELECT last_output, last_status FROM cron_tasks WHERE id = ?', [$id]);
    assertEquals('success', $task2['last_status']);
    assertContains('pong', (string) $task2['last_output']);

    $run = Database::selectOne('SELECT * FROM cron_runs WHERE task_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    assertNotNull($run);

    Database::run('DELETE FROM cron_tasks WHERE id = ?', [$id]); // cron_runs cae por FK
});

it('el lock evita el solapamiento de runDue', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $lockFile = BASE_PATH . '/storage/cache/cron.lock';
    @mkdir(dirname($lockFile), 0775, true);
    $held = fopen($lockFile, 'c');
    assertTrue($held !== false);
    flock($held, LOCK_EX); // simula otra corrida en curso

    $ran = CronRunner::runDue(new DateTimeImmutable('now'));
    assertEquals([], $ran, 'con el lock tomado, runDue debe omitirse');

    flock($held, LOCK_UN);
    fclose($held);
});
