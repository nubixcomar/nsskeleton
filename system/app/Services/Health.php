<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Throwable;

/**
 * Estado del sistema para healthcheck y métricas.
 */
final class Health
{
    public static function dbUp(): bool
    {
        try {
            Database::connection()->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function version(): string
    {
        $v = trim(@file_get_contents(PROJECT_PATH . '/VERSION') ?: '');
        return $v !== '' ? $v : '?';
    }

    /** Resumen público (sin datos sensibles). @return array<string,mixed> */
    public static function summary(): array
    {
        $db = self::dbUp();
        return [
            'status'      => $db ? 'ok' : 'degraded',
            'version'     => self::version(),       // core (nsSkeleton) — compat
            'core'        => self::version(),
            'app_version' => Version::app(),
            'db'          => $db,
            'time'        => date('c'),
        ];
    }

    /** Métricas completas (solo backend autenticado). @return array<string,mixed> */
    public static function full(): array
    {
        $queue = 0;
        try {
            $r = Database::selectOne("SELECT COUNT(*) AS c FROM email_queue WHERE status = 'pending'");
            $queue = (int) ($r['c'] ?? 0);
        } catch (Throwable) {
            $queue = 0;
        }

        $backups = glob(BASE_PATH . '/storage/backups/*.{sql,zip}', GLOB_BRACE) ?: [];
        rsort($backups);
        $lastBackup = $backups !== [] ? basename($backups[0]) : null;

        $free = @disk_free_space(PROJECT_PATH);

        return self::summary() + [
            'php'                 => PHP_VERSION,
            'email_queue_pending' => $queue,
            'disk_free_mb'        => $free !== false ? (int) round($free / 1048576) : null,
            'last_backup'         => $lastBackup,
        ];
    }
}
