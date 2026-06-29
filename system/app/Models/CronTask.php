<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Core\Model;

final class CronTask extends Model
{
    protected static string $table = 'cron_tasks';
    protected static array $fillable = [
        'name', 'command', 'schedule', 'active', 'priority', 'timeout',
        'last_run_at', 'last_status', 'last_output', 'next_run_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function recentRuns(int $taskId, int $limit = 10): array
    {
        return Database::select(
            'SELECT * FROM cron_runs WHERE task_id = ? ORDER BY id DESC LIMIT ' . (int) $limit,
            [$taskId]
        );
    }
}
