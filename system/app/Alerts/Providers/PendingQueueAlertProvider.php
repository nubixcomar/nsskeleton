<?php

declare(strict_types=1);

namespace App\Alerts\Providers;

use App\Alerts\AlertProvider;
use Core\Database;

/**
 * Alerta si la cola de jobs acumula demasiados pendientes (worker caído / atrasado).
 */
final class PendingQueueAlertProvider implements AlertProvider
{
    private const THRESHOLD = 20;

    public function key(): string
    {
        return 'pending_queue';
    }

    public function collect(): array
    {
        $n = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM jobs WHERE status = 'pending'")['c'] ?? 0);
        if ($n < self::THRESHOLD) {
            return [];
        }
        return [[
            'severity' => 'warning',
            'title'    => "{$n} jobs pendientes en la cola",
            'detail'   => '¿El worker (job:queue:work) está corriendo? La cola está acumulando trabajo.',
            'url'      => '/admin/jobs',
            'icon'     => '📥',
        ]];
    }
}
