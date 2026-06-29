<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

final class AdminUser extends Model
{
    protected static string $table = 'admin_users';
    protected static array $fillable = ['name', 'username', 'email', 'password', 'role', 'user_type', 'active'];

    public static function count(): int
    {
        $row = \Core\Database::selectOne('SELECT COUNT(*) AS n FROM admin_users');
        return (int) ($row['n'] ?? 0);
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            return self::findBy('email', $email) !== null;
        }
        $row = \Core\Database::selectOne(
            'SELECT id FROM admin_users WHERE email = ? AND id <> ? LIMIT 1',
            [$email, $exceptId]
        );
        return $row !== null;
    }
}
