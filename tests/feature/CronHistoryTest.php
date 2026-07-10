<?php

declare(strict_types=1);

use App\Models\CronTask;
use App\Services\CronRunner;
use App\Services\Http;
use Core\Database;
use DateTimeImmutable;

/**
 * v1.19 fase 3 — Historial de cron (filtros + Limpiar OK / Vaciar) + registro de disparo/duración.
 */

group('Cron · historial');

/** Inserta una tarea y devuelve su id. */
$task = static function (): int {
    return Database::insert(
        'INSERT INTO cron_tasks (name, command, schedule, active) VALUES (?, ?, ?, 1)',
        ['t-hist', 'job:x', '* * * * *']
    );
};
$run = static function (int $taskId, string $status, string $trigger, string $out): void {
    Database::run(
        'INSERT INTO cron_runs (task_id, started_at, finished_at, status, trigger_type, exit_code, duration_ms, output)
         VALUES (?, NOW(), NOW(), ?, ?, ?, ?, ?)',
        [$taskId, $status, $trigger, $status === 'success' ? 0 : 1, 120, $out]
    );
};

itDb('history filtra por estado y busca en el mensaje', function () use ($task, $run) {
    $id = $task();
    $run($id, 'success', 'cron', 'todo bien');
    $run($id, 'failed', 'master', 'explotó feo');
    $run($id, 'success', 'manual', 'otra ok');

    $all = CronTask::history([], 1, 50);
    assertEquals(3, $all['total']);

    $onlyFail = CronTask::history(['status' => 'failed'], 1, 50);
    assertEquals(1, $onlyFail['total']);
    assertEquals('failed', $onlyFail['rows'][0]['status']);

    $search = CronTask::history(['q' => 'explotó'], 1, 50);
    assertEquals(1, $search['total']);
});

itDb('Limpiar OK borra solo los exitosos (queda el log de errores)', function () use ($task, $run) {
    $id = $task();
    $run($id, 'success', 'cron', 'a');
    $run($id, 'success', 'cron', 'b');
    $run($id, 'failed', 'cron', 'c');

    $removed = CronTask::clearOkRuns();
    assertEquals(2, $removed);
    $rest = CronTask::history([], 1, 50);
    assertEquals(1, $rest['total']);
    assertEquals('failed', $rest['rows'][0]['status'], 'solo queda el error');
});

itDb('Vaciar registro borra todo', function () use ($task, $run) {
    $id = $task();
    $run($id, 'success', 'cron', 'a');
    $run($id, 'failed', 'cron', 'b');

    CronTask::clearAllRuns();
    assertEquals(0, CronTask::history([], 1, 50)['total']);
});

itDb('runTask registra trigger_type y duration_ms', function () use ($task) {
    Http::setTransport(static fn ($m, $u, $b, $h, $t) => ['status' => 200, 'body' => 'ok', 'error' => '']);
    try {
        $id = Database::insert(
            'INSERT INTO cron_tasks (name, command, schedule, active) VALUES (?, ?, ?, 1)',
            ['t-url', 'url:https://x.test/p', '* * * * *']
        );
        $taskRow = Database::selectOne('SELECT * FROM cron_tasks WHERE id = ?', [$id]);
        CronRunner::runTask($taskRow, new DateTimeImmutable('now'), 'master');

        $row = Database::selectOne('SELECT * FROM cron_runs WHERE task_id = ? ORDER BY id DESC LIMIT 1', [$id]);
        assertEquals('master', $row['trigger_type']);
        assertNotNull($row['duration_ms']);
        assertEquals('success', $row['status']);
    } finally {
        Http::resetTransport();
    }
});
