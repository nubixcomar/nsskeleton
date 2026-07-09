<?php

declare(strict_types=1);

namespace Core;

/**
 * Autenticación de administradores.
 *
 * Desde v1.17 #7 es una **fachada del guard `default`** (`config/auth.php`), que por defecto
 * es `admin` (tabla `admin_users`, clave de sesión `_auth_user_id`) → API y comportamiento
 * idénticos a los históricos. Para otros guards (brand, customer…) usá `Core\Guard::for(...)`.
 */
final class Auth
{
    private static function guard(): Guard
    {
        return Guard::default();
    }

    /** @return array<string,mixed>|null */
    public static function attempt(string $login, string $password): ?array
    {
        return self::guard()->attempt($login, $password);
    }

    /** @return array<string,mixed>|null */
    public static function verifyCredentials(string $login, string $password): ?array
    {
        return self::guard()->verifyCredentials($login, $password);
    }

    public static function login(int $userId): void
    {
        self::guard()->login($userId);
    }

    public static function check(): bool
    {
        return self::guard()->check();
    }

    public static function id(): ?int
    {
        return self::guard()->id();
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        return self::guard()->user();
    }

    public static function logout(): void
    {
        self::guard()->logout();
    }

    public static function hash(string $password): string
    {
        return Guard::hash($password);
    }
}
