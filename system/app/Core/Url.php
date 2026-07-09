<?php

declare(strict_types=1);

namespace Core;

/**
 * Construye URLs relativas al directorio público, para que los links funcionen
 * aunque la app viva en un subdirectorio (ej. /skeleton/system/public).
 *
 * El prefijo base se resuelve, en orden de precedencia:
 *   1) `APP_BASE_PATH` (.env) — fallback explícito y robusto para subdirectorios/alias
 *      donde `SCRIPT_NAME` se reporta mal (caso nginx `alias` del hallazgo 1.3).
 *   2) el directorio de `SCRIPT_NAME` (derivación automática por defecto).
 */
final class Url
{
    public static function base(): string
    {
        $env = Env::get('APP_BASE_PATH', '');
        if (is_string($env) && trim($env) !== '') {
            $b = '/' . trim(str_replace('\\', '/', $env), '/');
            return $b === '/' ? '' : $b;
        }
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }

    public static function to(string $path = '/'): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }
}
