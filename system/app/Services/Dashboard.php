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

    /**
     * Permisos de cada bloque (alcanza con tener UNO). Lista vacía = visible para todo usuario
     * logueado (el bloque filtra por dentro lo que no corresponde). Un bloque desconocido
     * (agregado por la app) se considera visible: su parcial decide.
     */
    public const BLOCK_PERMISSIONS = [
        'kpis'    => ['dashboard.view'],
        'charts'  => ['dashboard.view'],
        'alerts'  => ['logs.view'],
        'feed'    => [],                            // novedades abiertas; la salud exige logs.view
        'recent'  => ['audit.view', 'cron.manage'], // cada tarjeta exige el suyo
        'modules' => [],                            // la galería ya filtra por ítem
    ];

    /**
     * Bloques que el usuario puede ver (PL-002/F3), en el orden del preset.
     * @param array<int,string> $blocks
     * @param callable(string):bool $can
     * @return array<int,string>
     */
    public static function visibleBlocks(array $blocks, callable $can): array
    {
        $out = [];
        foreach ($blocks as $b) {
            $perms = self::BLOCK_PERMISSIONS[$b] ?? [];
            if ($perms === []) {
                $out[] = $b;
                continue;
            }
            foreach ($perms as $p) {
                if ($can($p)) {
                    $out[] = $b;
                    break;
                }
            }
        }
        return $out;
    }
}
