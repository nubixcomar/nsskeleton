<?php

declare(strict_types=1);

namespace App\Alerts\Providers;

use App\Alerts\AlertProvider;
use Core\Database;

/**
 * Alerta si no hay backups recientes (ninguno o el último > N días).
 */
final class OldBackupAlertProvider implements AlertProvider
{
    private const MAX_AGE_DAYS = 7;

    public function key(): string
    {
        return 'old_backup';
    }

    public function collect(): array
    {
        $row = Database::selectOne("SELECT MAX(created_at) AS last FROM backup_log WHERE status = 'ok'");
        $last = $row['last'] ?? null;

        if ($last === null) {
            return [[
                'severity' => 'warning',
                'title'    => 'Nunca se hizo un backup',
                'detail'   => 'No hay backups registrados. Configurá uno en Backups.',
                'url'      => '/admin/backup',
                'icon'     => '💾',
            ]];
        }

        $ageDays = (time() - strtotime((string) $last)) / 86400;
        if ($ageDays > self::MAX_AGE_DAYS) {
            return [[
                'severity' => 'warning',
                'title'    => 'Último backup hace ' . (int) $ageDays . ' días',
                'detail'   => 'El último backup es viejo. Considerá correr uno nuevo.',
                'url'      => '/admin/backup',
                'icon'     => '💾',
            ]];
        }
        return [];
    }
}
