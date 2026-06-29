<?php

declare(strict_types=1);

namespace App\Services;

use Core\Auth;
use Core\Database;
use Throwable;

/**
 * Registro de auditoría de acciones de administradores.
 * Best-effort: nunca rompe la operación si falla el registro.
 */
final class Audit
{
    public static function log(string $action, string $target = '', string $details = ''): void
    {
        self::record(
            $action,
            $target !== '' ? substr($target, 0, 190) : null,
            $details !== '' ? substr($details, 0, 500) : null,
            null
        );
    }

    /**
     * Registra un cambio con diff (solo los campos modificados).
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    public static function logChange(string $action, string $target, array $before, array $after): void
    {
        $changes = self::diff($before, $after);
        if ($changes === []) {
            return;
        }
        self::record(
            $action,
            $target !== '' ? substr($target, 0, 190) : null,
            null,
            json_encode($changes, JSON_UNESCAPED_UNICODE) ?: null
        );
    }

    /**
     * Calcula los campos que cambiaron: clave => ['old'=>.., 'new'=>..].
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @param array<int,string> $ignore
     * @return array<string,array{old:mixed,new:mixed}>
     */
    public static function diff(array $before, array $after, array $ignore = ['password', 'created_at', 'updated_at']): array
    {
        $changes = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        foreach ($keys as $key) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ((string) $old !== (string) $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }
        return $changes;
    }

    private static function record(string $action, ?string $target, ?string $details, ?string $changes): void
    {
        try {
            $user = null;
            try {
                $user = Auth::user();
            } catch (Throwable) {
                $user = null;
            }

            Database::insert(
                'INSERT INTO audit_log (admin_id, admin_name, action, target, details, changes, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $user['id'] ?? null,
                    $user['name'] ?? null,
                    $action,
                    $target,
                    $details,
                    $changes,
                    self::ip(),
                ]
            );
        } catch (Throwable) {
            // auditoría best-effort
        }
    }

    private static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null;
    }
}
