<?php

declare(strict_types=1);

namespace Core;

/**
 * Construye URLs relativas al directorio público, para que los links funcionen
 * aunque la app viva en un subdirectorio (ej. /skeleton/system/public).
 */
final class Url
{
    public static function base(): string
    {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }

    public static function to(string $path = '/'): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }
}
