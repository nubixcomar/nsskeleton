<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Control de intentos de login: tras MAX fallos consecutivos, bloquea por
 * LOCK_SECONDS. Se reinicia al loguear con éxito (clear) o al expirar el bloqueo.
 */
final class LoginThrottle
{
    private const MAX = 5;
    private const LOCK_SECONDS = 900; // 15 minutos

    public static function tooManyAttempts(string $id): bool
    {
        return self::secondsRemaining($id) > 0;
    }

    public static function secondsRemaining(string $id): int
    {
        $row = self::row($id);
        if ($row === null || $row['locked_until'] === null) {
            return 0;
        }
        return max(0, strtotime((string) $row['locked_until']) - time());
    }

    /** Registra un intento fallido; bloquea si se alcanza el máximo. */
    public static function hit(string $id): void
    {
        $id = self::norm($id);
        $now = date('Y-m-d H:i:s');
        $row = self::row($id);

        if ($row === null) {
            Database::insert(
                'INSERT INTO login_attempts (identifier, attempts, last_attempt_at) VALUES (?, 1, ?)',
                [$id, $now]
            );
            return;
        }

        $attempts = (int) $row['attempts'];
        // Si el bloqueo previo ya expiró, reiniciar el contador.
        if ($row['locked_until'] !== null && strtotime((string) $row['locked_until']) <= time()) {
            $attempts = 0;
        }
        $attempts++;

        $lockedUntil = $attempts >= self::MAX
            ? date('Y-m-d H:i:s', time() + self::LOCK_SECONDS)
            : null;

        Database::run(
            'UPDATE login_attempts SET attempts = ?, last_attempt_at = ?, locked_until = ? WHERE identifier = ?',
            [$attempts, $now, $lockedUntil, $id]
        );
    }

    public static function clear(string $id): void
    {
        Database::run('DELETE FROM login_attempts WHERE identifier = ?', [self::norm($id)]);
    }

    /** @return array<string,mixed>|null */
    private static function row(string $id): ?array
    {
        return Database::selectOne('SELECT * FROM login_attempts WHERE identifier = ? LIMIT 1', [self::norm($id)]);
    }

    private static function norm(string $id): string
    {
        return strtolower(trim($id));
    }
}
