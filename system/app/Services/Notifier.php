<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Throwable;

/**
 * Notificaciones in-app por usuario (campanita + bandeja). Best-effort: nunca rompe el flujo.
 */
final class Notifier
{
    public static function notify(int $userId, string $title, ?string $body = null, ?string $url = null): void
    {
        try {
            Database::run(
                'INSERT INTO notifications (user_id, title, body, url) VALUES (?, ?, ?, ?)',
                [$userId, $title, $body, $url]
            );
        } catch (Throwable) {
            // silencioso
        }
    }

    /** Notifica a todos los administradores activos. */
    public static function notifyAll(string $title, ?string $body = null, ?string $url = null, ?int $exceptUserId = null): void
    {
        try {
            $admins = Database::select('SELECT id FROM admin_users WHERE active = 1');
        } catch (Throwable) {
            return;
        }
        foreach ($admins as $a) {
            $id = (int) $a['id'];
            if ($id !== $exceptUserId) {
                self::notify($id, $title, $body, $url);
            }
        }
    }

    public static function unreadCount(int $userId): int
    {
        try {
            $row = Database::selectOne('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
        } catch (Throwable) {
            return 0;
        }
        return (int) ($row['c'] ?? 0);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId, bool $onlyUnread = false, int $limit = 20): array
    {
        $where = $onlyUnread ? ' AND read_at IS NULL' : '';
        try {
            return Database::select(
                "SELECT * FROM notifications WHERE user_id = ?{$where} ORDER BY created_at DESC LIMIT {$limit}",
                [$userId]
            );
        } catch (Throwable) {
            return [];
        }
    }

    public static function markRead(int $id, int $userId): void
    {
        try {
            Database::run('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL', [$id, $userId]);
        } catch (Throwable) {
        }
    }

    public static function markAllRead(int $userId): void
    {
        try {
            Database::run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
        } catch (Throwable) {
        }
    }
}
