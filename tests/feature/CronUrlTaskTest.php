<?php

declare(strict_types=1);

use App\Services\CronRunner;
use App\Services\Http;
use Core\Database;
use DateTimeImmutable;

/**
 * v1.19 fase 2 — Tareas tipo URL (HTTP) en el cron. Usan App\Services\Http (con reintentos).
 * Transporte de Http mockeado; itDb aísla la DB.
 */

group('Cron · tareas URL');

/** Inserta una tarea y devuelve su fila. */
$seedTask = static function (string $command): array {
    $id = Database::insert(
        'INSERT INTO cron_tasks (name, command, schedule, active) VALUES (?, ?, ?, 1)',
        ['tarea-url', $command, '* * * * *']
    );
    return Database::selectOne('SELECT * FROM cron_tasks WHERE id = ?', [$id]) ?? [];
};

itDb('url: hace GET y marca success en 2xx', function () use ($seedTask) {
    $captured = [];
    Http::setTransport(static function ($m, $u, $b, $h, $t) use (&$captured) {
        $captured = ['method' => $m, 'url' => $u];
        return ['status' => 200, 'body' => 'pong', 'error' => ''];
    });
    try {
        $task = $seedTask('url:https://ejemplo.test/ping');
        $r = CronRunner::runTask($task, new DateTimeImmutable('now'), 'manual');
        assertEquals('success', $r['status']);
        assertEquals('GET', $captured['method']);
        assertEquals('https://ejemplo.test/ping', $captured['url']);
    } finally {
        Http::resetTransport();
    }
});

itDb('url: marca failed en 5xx', function () use ($seedTask) {
    Http::setTransport(static fn ($m, $u, $b, $h, $t) => ['status' => 503, 'body' => '', 'error' => '']);
    try {
        $task = $seedTask('url:https://ejemplo.test/x');
        $r = CronRunner::runTask($task, new DateTimeImmutable('now'), 'manual');
        assertEquals('failed', $r['status']);
    } finally {
        Http::resetTransport();
    }
});

itDb('url:POST con JSON manda POST y cuerpo json', function () use ($seedTask) {
    $captured = [];
    Http::setTransport(static function ($m, $u, $b, $h, $t) use (&$captured) {
        $captured = ['method' => $m, 'url' => $u, 'body' => $b];
        return ['status' => 201, 'body' => '{"ok":true}', 'error' => ''];
    });
    try {
        $task = $seedTask('url:POST https://ejemplo.test/hook {"evento":"ping"}');
        $r = CronRunner::runTask($task, new DateTimeImmutable('now'), 'manual');
        assertEquals('success', $r['status']);
        assertEquals('POST', $captured['method']);
        assertEquals('https://ejemplo.test/hook', $captured['url']);
        assertContains('"evento":"ping"', (string) $captured['body']);
    } finally {
        Http::resetTransport();
    }
});

itDb('las tareas shell/job siguen andando (no rompió url:)', function () use ($seedTask) {
    // Un job interno inexistente devuelve code != 0 → failed, pero NO lanza.
    $task = $seedTask('job:__inexistente__');
    $r = CronRunner::runTask($task, new DateTimeImmutable('now'), 'manual');
    assertTrue(in_array($r['status'], ['success', 'failed'], true), 'runTask no debe lanzar');
});
