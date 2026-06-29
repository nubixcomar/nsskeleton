<?php

declare(strict_types=1);

namespace App\Services;

use Core\Env;
use Throwable;

/**
 * Versionado dual:
 *  - core(): versión del framework nsSkeleton (archivo VERSION).
 *  - app():  versión de la app derivada (settings `app.version` > APP_VERSION env > 1.0.0).
 * Permite saber con qué core se construyó cada app.
 */
final class Version
{
    public static function core(): string
    {
        $v = trim(@file_get_contents(PROJECT_PATH . '/VERSION') ?: '');
        return $v !== '' ? $v : '0.0.0';
    }

    public static function coreName(): string
    {
        return 'nsSkeleton';
    }

    public static function app(): string
    {
        try {
            $s = Settings::get('app.version');
            if (is_string($s) && $s !== '') {
                return $s;
            }
        } catch (Throwable) {
            // sin DB, cae al env
        }
        $e = (string) Env::get('APP_VERSION', '');
        return $e !== '' ? $e : '1.0.0';
    }

    /** @return array{app:string,core:string,core_name:string} */
    public static function all(): array
    {
        return ['app' => self::app(), 'core' => self::core(), 'core_name' => self::coreName()];
    }
}
