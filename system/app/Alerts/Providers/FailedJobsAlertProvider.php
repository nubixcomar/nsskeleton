<?php

declare(strict_types=1);

namespace App\Alerts\Providers;

use App\Alerts\AlertProvider;
use Core\Database;

/**
 * Alerta si hay jobs fallidos en la cola.
 */
final class FailedJobsAlertProvider implements AlertProvider
{
    public function key(): string
    {
        return 'failed_jobs';
    }

    public function collect(): array
    {
        $n = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM jobs WHERE status = 'failed'")['c'] ?? 0);
        if ($n === 0) {
            return [];
        }
        return [[
            'severity' => 'danger',
            'title'    => "{$n} job(s) fallidos en la cola",
            'detail'   => 'Revisá la cola de jobs para reintentarlos o descartarlos.',
            'url'      => '/admin/jobs',
            'icon'     => '⚠️',
        ]];
    }
}
