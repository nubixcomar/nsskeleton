<?php

declare(strict_types=1);

namespace App\Services;

use Core\Auth;

/**
 * Tipos de usuario del sistema (login unificado). Registro en config/user_types.php.
 */
final class UserTypes
{
    /** @return array<string,string> clave => etiqueta */
    public static function all(): array
    {
        $file = BASE_PATH . '/config/user_types.php';
        $types = is_file($file) ? (require $file) : [];
        return is_array($types) && $types !== [] ? $types : ['admin' => 'Administrador'];
    }

    public static function label(string $key): string
    {
        return self::all()[$key] ?? $key;
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** Tipo del usuario logueado (o null). */
    public static function current(): ?string
    {
        $u = Auth::user();
        return $u !== null ? (string) ($u['user_type'] ?? 'admin') : null;
    }
}
