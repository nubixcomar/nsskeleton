<?php

declare(strict_types=1);

namespace Core;

/**
 * Wrapper de sesión con helpers de flash y CSRF.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::configureSavePath();

        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }

    /**
     * Apunta `session.save_path` a `storage/sessions/` del proyecto (como Laravel),
     * en vez del directorio global de PHP (`/var/lib/php/sessions`), que NO es escribible
     * en pools PHP-FPM aislados con `open_basedir` → login/CSRF/carrito rotos (hallazgo 1.4).
     *
     * Configurable por env `SESSION_PATH`. Solo se aplica si el directorio existe y es
     * escribible (si no, se deja el default del server y no se rompe nada).
     */
    private static function configureSavePath(): void
    {
        // Sin control del ini (cabeceras ya enviadas) → no tocar.
        if (headers_sent()) {
            return;
        }

        $path = self::resolveSavePath();
        if ($path === null) {
            return;
        }

        if (!is_dir($path)) {
            @mkdir($path, 0770, true);
        }
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
            ini_set('session.gc_maxlifetime', (string) Env::get('SESSION_LIFETIME', 7200));
        }
    }

    /**
     * Resuelve el directorio de sesiones (sin efectos): `SESSION_PATH` si está seteado,
     * si no `system/storage/sessions/`. Devuelve null si no se puede determinar.
     */
    public static function resolveSavePath(): ?string
    {
        $configured = Env::get('SESSION_PATH', '');
        if (is_string($configured) && trim($configured) !== '') {
            return rtrim(str_replace('\\', '/', trim($configured)), '/');
        }
        if (defined('BASE_PATH')) {
            return BASE_PATH . '/storage/sessions';
        }
        return null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Mensaje flash (vive una sola petición siguiente). */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** Token CSRF para formularios. */
    public static function csrf(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}
