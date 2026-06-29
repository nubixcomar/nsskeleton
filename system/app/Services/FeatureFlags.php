<?php

declare(strict_types=1);

namespace App\Services;

use Core\Config;
use Throwable;

/**
 * Feature flags: defaults en config/features.php (overrideable por la app vía
 * config/overrides/features.php) y override por settings (grupo `flags`).
 */
final class FeatureFlags
{
    /** @return array<string,bool> */
    public static function defaults(): array
    {
        return array_map(static fn ($v): bool => (bool) $v, Config::load('features'));
    }

    /** @return array<string,bool> estado efectivo de cada flag */
    public static function all(): array
    {
        $defaults = self::defaults();

        try {
            $over = Settings::group('flags');
        } catch (Throwable) {
            $over = [];
        }

        $out = [];
        foreach ($defaults as $name => $default) {
            $out[$name] = array_key_exists($name, $over) ? self::truthy($over[$name]) : $default;
        }
        return $out;
    }

    public static function enabled(string $name): bool
    {
        return self::all()[$name] ?? false;
    }

    public static function set(string $name, bool $on): void
    {
        Settings::set('flags.' . $name, $on ? '1' : '0', 'flags');
    }

    private static function truthy(mixed $v): bool
    {
        return in_array(strtolower((string) $v), ['1', 'true', 'on', 'yes'], true);
    }
}
