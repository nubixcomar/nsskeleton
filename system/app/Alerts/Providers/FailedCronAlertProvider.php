<?php

declare(strict_types=1);

namespace App\Alerts\Providers;

use App\Alerts\AlertProvider;
use Core\Database;

/**
 * Alerta si alguna tarea programada activa terminó con error en su última corrida.
 */
final class FailedCronAlertProvider implements AlertProvider
{
    public function key(): string
    {
        return 'failed_cron';
    }

    public function collect(): array
    {
        $n = (int) (Database::selectOne(
            "SELECT COUNT(*) AS c FROM cron_tasks WHERE active = 1 AND last_status = 'failed'"
        )['c'] ?? 0);
        if ($n === 0) {
            return [];
        }
        return [[
            'severity' => 'warning',
            'title'    => "{$n} tarea(s) de cron con error",
            'detail'   => 'La última ejecución de alguna tarea programada falló.',
            'url'      => '/admin/cron',
            'icon'     => '⏰',
        ]];
    }
}
