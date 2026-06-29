<?php

declare(strict_types=1);

namespace Core;

/**
 * Resuelve los assets de frontend: locales (sin CDN) o CDN de fallback.
 * Modo por `ASSETS_MODE` (.env): local | cdn. Por defecto local si el CSS existe.
 */
final class Assets
{
    public static function useLocal(): bool
    {
        if (strtolower((string) Env::get('ASSETS_MODE', 'local')) === 'cdn') {
            return false;
        }
        return is_file(BASE_PATH . '/public/assets/css/app.css');
    }

    public static function css(): string
    {
        return Url::to('/assets/css/app.css');
    }

    public static function alpine(): string
    {
        return Url::to('/assets/js/alpine.min.js');
    }

    public static function chart(): string
    {
        return Url::to('/assets/js/chart.umd.min.js');
    }

    /**
     * Devuelve las etiquetas <link>/<script> del <head>, locales o CDN.
     */
    public static function head(bool $withChart = false, bool $withAlpine = true): string
    {
        $e = static fn (string $u): string => htmlspecialchars($u, ENT_QUOTES, 'UTF-8');

        if (self::useLocal()) {
            $tags = '<link rel="stylesheet" href="' . $e(self::css()) . '">';
            if ($withChart) {
                $tags .= "\n    " . '<script src="' . $e(self::chart()) . '"></script>';
            }
            if ($withAlpine) {
                $tags .= "\n    " . '<script defer src="' . $e(self::alpine()) . '"></script>';
            }
        } else {
            $tags = '<script src="https://cdn.tailwindcss.com"></script>';
            if ($withChart) {
                $tags .= "\n    " . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>';
            }
            if ($withAlpine) {
                $tags .= "\n    " . '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>';
            }
        }

        // Tema (claro/oscuro): hoja de overrides + aplicación anti-flash del tema guardado.
        $tags .= "\n    " . '<link rel="stylesheet" href="' . $e(Url::to('/assets/css/theme.css')) . '">';
        $tags .= "\n    " . '<script>(function(){try{if(localStorage.getItem("theme")==="dark")document.documentElement.classList.add("dark");}catch(e){}})();</script>';

        return $tags;
    }
}
