<?php

declare(strict_types=1);

use App\Services\JobQueue;
use Core\Database;

group('JobQueue (feature · requiere MySQL)');

it('procesa un job válido y queda done', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $logFile = BASE_PATH . '/../logs/jobs-demo.log';
    @unlink($logFile);

    $id = JobQueue::push('demo:log', ['message' => 'ZZJOBTEST']);
    $r = JobQueue::work(10);
    assertTrue($r['done'] >= 1);

    $row = Database::selectOne('SELECT status FROM jobs WHERE id = ?', [$id]);
    assertEquals('done', $row['status']);
    assertTrue(is_file($logFile) && str_contains((string) file_get_contents($logFile), 'ZZJOBTEST'));

    Database::run('DELETE FROM jobs WHERE id = ?', [$id]);
    @unlink($logFile);
});

it('handler inexistente con max=1 queda failed', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $id = JobQueue::push('no:existe', [], 1);
    JobQueue::work(10);
    $row = Database::selectOne('SELECT status, attempts FROM jobs WHERE id = ?', [$id]);
    assertEquals('failed', $row['status']);
    assertEquals(1, (int) $row['attempts']);

    Database::run('DELETE FROM jobs WHERE id = ?', [$id]);
});

it('handler que falla con max>1 se reencola (retry, backoff)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $id = JobQueue::push('no:existe', [], 3);
    JobQueue::work(10);
    $row = Database::selectOne('SELECT status, attempts FROM jobs WHERE id = ?', [$id]);
    assertEquals('pending', $row['status']); // vuelve a pending (con backoff)
    assertEquals(1, (int) $row['attempts']);

    Database::run('DELETE FROM jobs WHERE id = ?', [$id]);
});

it('retry y forget', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $id = JobQueue::push('no:existe', [], 1);
    JobQueue::work(10); // queda failed
    JobQueue::retry($id);
    assertEquals('pending', Database::selectOne('SELECT status FROM jobs WHERE id = ?', [$id])['status']);

    JobQueue::forget($id);
    assertNull(Database::selectOne('SELECT id FROM jobs WHERE id = ?', [$id]));
});
