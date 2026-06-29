<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminUser;
use Core\Auth;
use Core\Database;

/**
 * Tokens de recuperación de contraseña. Se persiste solo el HASH del token
 * (el token en claro viaja únicamente en el email). Expira a la hora y es de un solo uso.
 */
final class PasswordReset
{
    private const TTL_SECONDS = 3600;

    /**
     * Crea un token para el email (si existe el admin). Devuelve el token en claro,
     * o null si el email no corresponde a ningún admin.
     */
    public static function createToken(string $email): ?string
    {
        $email = self::norm($email);
        if (AdminUser::findBy('email', $email) === null) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);
        Database::insert(
            'INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)',
            [$email, hash('sha256', $token), date('Y-m-d H:i:s', time() + self::TTL_SECONDS)]
        );
        return $token;
    }

    public static function valid(string $email, string $token): bool
    {
        $email = self::norm($email);
        $row = Database::selectOne('SELECT * FROM password_resets WHERE email = ? LIMIT 1', [$email]);
        if ($row === null || strtotime((string) $row['expires_at']) < time()) {
            return false;
        }
        return hash_equals((string) $row['token_hash'], hash('sha256', $token));
    }

    /** Valida y aplica la nueva contraseña; invalida el token. */
    public static function consume(string $email, string $token, string $newPassword): bool
    {
        if (!self::valid($email, $token)) {
            return false;
        }
        $email = self::norm($email);
        $admin = AdminUser::findBy('email', $email);
        if ($admin === null) {
            return false;
        }
        AdminUser::update((int) $admin['id'], ['password' => Auth::hash($newPassword)]);
        Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);
        return true;
    }

    private static function norm(string $email): string
    {
        return strtolower(trim($email));
    }
}
