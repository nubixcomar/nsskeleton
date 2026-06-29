<?php

declare(strict_types=1);

namespace App\Services;

use Core\Env;
use Throwable;

/**
 * Configuración general de la aplicación (grupo `app` en settings), con fallback
 * a `.env`. Resiliente: si la base no está, usa los valores de entorno.
 */
final class AppSettings
{
    /** @return array<string,?string> */
    private static function group(): array
    {
        try {
            return Settings::group('app');
        } catch (Throwable) {
            return [];
        }
    }

    private static function value(string $short, string $envKey, string $default): string
    {
        $g = self::group();
        $v = $g[$short] ?? null;
        if ($v !== null && $v !== '') {
            return (string) $v;
        }
        return (string) Env::get($envKey, $default);
    }

    public static function name(): string
    {
        return self::value('name', 'APP_NAME', 'nsSkeleton');
    }

    public static function tagline(): string
    {
        return (string) (self::group()['tagline'] ?? '');
    }

    public static function timezone(string $fallback = 'UTC'): string
    {
        return self::value('timezone', 'APP_TIMEZONE', $fallback);
    }

    public static function logo(): ?string
    {
        $v = self::group()['logo'] ?? null;
        return ($v !== null && $v !== '') ? (string) $v : null;
    }
}
