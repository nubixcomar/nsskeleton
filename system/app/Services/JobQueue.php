<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\Job;
use Core\Database;
use Throwable;

/**
 * Cola de jobs generalizada con reintentos y backoff.
 * Encolá con push(); drená con work() (lo llama el job interno `queue:work` del cron,
 * o el CLI system/console/queue.php).
 */
final class JobQueue
{
    /** Encola un job. @param array<string,mixed> $payload */
    public static function push(string $handler, array $payload = [], int $maxAttempts = 3, int $delaySeconds = 0): int
    {
        $available = $delaySeconds > 0 ? 'DATE_ADD(NOW(), INTERVAL ' . (int) $delaySeconds . ' SECOND)' : 'NOW()';
        return Database::insert(
            "INSERT INTO jobs (handler, payload, max_attempts, available_at) VALUES (?, ?, ?, {$available})",
            [$handler, json_encode($payload, JSON_UNESCAPED_UNICODE), max(1, $maxAttempts)]
        );
    }

    /**
     * Procesa hasta $limit jobs disponibles.
     * @return array{processed:int,done:int,failed:int,retried:int}
     */
    public static function work(int $limit = 25): array
    {
        $processed = 0;
        $done = 0;
        $failed = 0;
        $retried = 0;

        for ($i = 0; $i < $limit; $i++) {
            $job = self::reserve();
            if ($job === null) {
                break;
            }
            $processed++;
            $result = self::run($job);
            match ($result) {
                'done'   => $done++,
                'failed' => $failed++,
                default  => $retried++,
            };
        }

        return ['processed' => $processed, 'done' => $done, 'failed' => $failed, 'retried' => $retried];
    }

    /** @return array<string,mixed>|null */
    private static function reserve(): ?array
    {
        $row = Database::selectOne(
            "SELECT * FROM jobs WHERE status = 'pending' AND available_at <= NOW() ORDER BY id ASC LIMIT 1"
        );
        if ($row === null) {
            return null;
        }
        // Lock optimista: solo lo toma quien logra el UPDATE.
        $taken = Database::affected(
            "UPDATE jobs SET status = 'processing', reserved_at = NOW() WHERE id = ? AND status = 'pending'",
            [$row['id']]
        );
        return $taken > 0 ? $row : null;
    }

    private static function run(array $job): string
    {
        $attempts = (int) $job['attempts'] + 1;
        $payload = json_decode((string) ($job['payload'] ?? '[]'), true);
        $payload = is_array($payload) ? $payload : [];

        try {
            self::resolve((string) $job['handler'])->handle($payload);
            Database::run(
                "UPDATE jobs SET status = 'done', attempts = ?, completed_at = NOW(), error = NULL WHERE id = ?",
                [$attempts, $job['id']]
            );
            return 'done';
        } catch (Throwable $e) {
            $error = substr($e->getMessage(), 0, 500);
            if ($attempts >= (int) $job['max_attempts']) {
                Database::run(
                    "UPDATE jobs SET status = 'failed', attempts = ?, error = ? WHERE id = ?",
                    [$attempts, $error, $job['id']]
                );
                return 'failed';
            }
            $backoff = $attempts * 60; // backoff lineal (segundos)
            Database::run(
                "UPDATE jobs SET status = 'pending', attempts = ?, error = ?, available_at = DATE_ADD(NOW(), INTERVAL {$backoff} SECOND) WHERE id = ?",
                [$attempts, $error, $job['id']]
            );
            return 'retry';
        }
    }

    private static function resolve(string $handler): Job
    {
        $map = require BASE_PATH . '/config/job_handlers.php';
        $class = $map[$handler] ?? null;
        if (!is_string($class) || !class_exists($class)) {
            throw new \RuntimeException("Handler '{$handler}' no está registrado.");
        }
        $obj = new $class();
        if (!$obj instanceof Job) {
            throw new \RuntimeException("Handler '{$handler}' no implementa App\\Jobs\\Job.");
        }
        return $obj;
    }

    public static function retry(int $id): void
    {
        Database::run("UPDATE jobs SET status = 'pending', available_at = NOW(), error = NULL WHERE id = ? AND status = 'failed'", [$id]);
    }

    public static function forget(int $id): void
    {
        Database::run('DELETE FROM jobs WHERE id = ?', [$id]);
    }

    /** @return array<string,int> conteo por estado */
    public static function stats(): array
    {
        $out = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0];
        foreach (Database::select('SELECT status, COUNT(*) AS c FROM jobs GROUP BY status') as $r) {
            $out[(string) $r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 30): array
    {
        return Database::select('SELECT * FROM jobs ORDER BY id DESC LIMIT ' . max(1, $limit));
    }
}
