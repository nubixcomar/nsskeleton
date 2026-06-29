<?php

declare(strict_types=1);

namespace Core;

/**
 * Autenticación de administradores contra la tabla `admin_users`.
 * (La tabla se crea en la migración del módulo de login.)
 *
 * Columnas esperadas: id, name, email, password (hash), role, active.
 */
final class Auth
{
    private const TABLE = 'admin_users';
    private const SESSION_KEY = '_auth_user_id';

    /**
     * Intenta autenticar. Devuelve el usuario (sin password) si tiene éxito, o null.
     *
     * @return array<string,mixed>|null
     */
    public static function attempt(string $login, string $password): ?array
    {
        $user = Database::selectOne(
            'SELECT * FROM ' . self::TABLE . ' WHERE email = ? OR username = ? LIMIT 1',
            [$login, $login]
        );

        if ($user === null || (int) ($user['active'] ?? 0) !== 1) {
            return null;
        }

        if (!password_verify($password, (string) $user['password'])) {
            return null;
        }

        self::login((int) $user['id']);
        unset($user['password']);
        return $user;
    }

    /**
     * Verifica credenciales SIN iniciar sesión (para flujos con 2FA).
     * @return array<string,mixed>|null
     */
    public static function verifyCredentials(string $login, string $password): ?array
    {
        $user = Database::selectOne(
            'SELECT * FROM ' . self::TABLE . ' WHERE email = ? OR username = ? LIMIT 1',
            [$login, $login]
        );

        if ($user === null || (int) ($user['active'] ?? 0) !== 1) {
            return null;
        }
        if (!password_verify($password, (string) $user['password'])) {
            return null;
        }

        unset($user['password']);
        return $user;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, $userId);
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);
        return $id === null ? null : (int) $id;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }

        $user = Database::selectOne(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1',
            [$id]
        );

        if ($user !== null) {
            unset($user['password']);
        }
        return $user;
    }

    public static function logout(): void
    {
        Session::remove(self::SESSION_KEY);
        Session::regenerate();
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
