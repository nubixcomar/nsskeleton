<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CronTask;
use Core\Database;
use DateTimeImmutable;

/**
 * Ejecuta las tareas programadas que estén vencidas y registra el resultado.
 */
final class CronRunner
{
    private const OUTPUT_LIMIT = 5000;

    /**
     * Corre todas las tareas activas que estén "due" en $now.
     *
     * @return array<int,array<string,mixed>> Resumen de lo ejecutado.
     */
    public static function runDue(DateTimeImmutable $now): array
    {
        // Lock anti-solapamiento: si otra corrida está en curso, se omite esta.
        $lockFile = BASE_PATH . '/storage/cache/cron.lock';
        $lock = @fopen($lockFile, 'c');
        if ($lock !== false && !flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return [];
        }

        // Throttle: si pasó menos de `cron.min_interval` segundos desde el último tick, se omite.
        $minInterval = (int) (Settings::get('cron.min_interval', 0) ?? 0);
        if ($minInterval > 0) {
            $lastTick = (string) (Settings::get('cron.last_tick', '') ?? '');
            if ($lastTick !== '') {
                $elapsed = $now->getTimestamp() - (new DateTimeImmutable($lastTick))->getTimestamp();
                if ($elapsed < $minInterval) {
                    if ($lock !== false) {
                        flock($lock, LOCK_UN);
                        fclose($lock);
                    }
                    return [];
                }
            }
            Settings::set('cron.last_tick', $now->format('Y-m-d H:i:s'), 'cron');
        }

        try {
            $ran = [];
            // Orden por prioridad (mayor primero), luego por id.
            $tasks = Database::select('SELECT * FROM cron_tasks WHERE active = 1 ORDER BY priority DESC, id ASC');

            foreach ($tasks as $task) {
                if (!CronExpression::isDue((string) $task['schedule'], $now)) {
                    continue;
                }

                // Evita doble ejecución dentro del mismo minuto.
                if (!empty($task['last_run_at'])) {
                    $last = new DateTimeImmutable((string) $task['last_run_at']);
                    if ($last->format('Y-m-d H:i') === $now->format('Y-m-d H:i')) {
                        continue;
                    }
                }

                $ran[] = self::runTask($task, $now);
            }

            return $ran;
        } finally {
            if ($lock !== false) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * Ejecuta una tarea concreta (también usado por "ejecutar ahora" desde el panel).
     *
     * @param array<string,mixed> $task
     * @return array<string,mixed>
     */
    public static function runTask(array $task, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable('now');
        $command = (string) $task['command'];

        // Timeout por tarea (0 = sin límite).
        $timeout = (int) ($task['timeout'] ?? 0);
        if ($timeout > 0) {
            @set_time_limit($timeout);
        }

        // Job interno (`job:<nombre>`) o comando de shell.
        if (preg_match('/^job:(.+)$/', trim($command), $m)) {
            $r = Jobs::run(trim($m[1]));
            $outStr = (string) $r['output'];
            $code = (int) $r['code'];
        } else {
            $output = [];
            $code = 0;
            exec($command . ' 2>&1', $output, $code);
            $outStr = trim(implode("\n", $output));
        }

        if (strlen($outStr) > self::OUTPUT_LIMIT) {
            $outStr = substr($outStr, 0, self::OUTPUT_LIMIT) . "\n...(truncado)";
        }

        $status = $code === 0 ? 'success' : 'failed';
        $next = CronExpression::nextRunAfter((string) $task['schedule'], $now);

        CronTask::update((int) $task['id'], [
            'last_run_at' => $now->format('Y-m-d H:i:s'),
            'last_status' => $status,
            'last_output' => $outStr,
            'next_run_at' => $next?->format('Y-m-d H:i:s'),
        ]);

        Database::insert(
            'INSERT INTO cron_runs (task_id, started_at, finished_at, status, exit_code, output)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                (int) $task['id'],
                $now->format('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
                $status,
                $code,
                $outStr,
            ]
        );

        return [
            'id'     => (int) $task['id'],
            'name'   => (string) $task['name'],
            'status' => $status,
            'code'   => $code,
        ];
    }
}
