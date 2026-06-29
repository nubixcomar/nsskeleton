<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Presets de dashboard (config/dashboard.php). El activo se puede cambiar por panel
 * (settings `dashboard.preset`).
 */
final class Dashboard
{
    /** @return array<string,mixed> */
    private static function config(): array
    {
        $f = BASE_PATH . '/config/dashboard.php';
        return is_file($f) ? (require $f) : ['default' => 'completo', 'presets' => []];
    }

    /** @return array<string,array<string,mixed>> */
    public static function presets(): array
    {
        return self::config()['presets'] ?? [];
    }

    public static function active(): string
    {
        $cfg = self::config();
        $presets = $cfg['presets'] ?? [];

        try {
            $s = Settings::get('dashboard.preset');
            if (is_string($s) && isset($presets[$s])) {
                return $s;
            }
        } catch (Throwable) {
            // sin DB: usa default
        }

        $def = (string) ($cfg['default'] ?? 'completo');
        return isset($presets[$def]) ? $def : (string) (array_key_first($presets) ?? 'completo');
    }

    /** @return array<int,string> bloques del preset activo */
    public static function blocks(): array
    {
        $p = self::active();
        return self::presets()[$p]['blocks'] ?? ['kpis', 'charts'];
    }
}
