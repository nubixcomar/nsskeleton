<?php

declare(strict_types=1);

namespace Core;

/**
 * Cargador de configuración con OVERRIDES de la app.
 *
 * Lee `config/{name}.php` (core) y, si existe, lo mezcla con
 * `config/overrides/{name}.php` (app). **En conflicto gana la app.** Así un
 * proyecto puede personalizar cualquier config del core sin editar el archivo
 * del core → el actualizador de core lo pisa sin pisar tu personalización.
 *
 * Merge: los mapas (arrays asociativos) se combinan recursivamente; las listas
 * y los escalares del override REEMPLAZAN al del core.
 *
 *   Core\Config::load('app')                 // array completo (core + override)
 *   Core\Config::get('app', 'timezone', 'UTC')
 *
 * Para que una config sea "overrideable", leela con Config en vez de `require`.
 */
final class Config
{
    /** @var array<string,array<mixed>> */
    private static array $cache = [];

    /** @return array<mixed> */
    public static function load(string $name): array
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $coreFile = BASE_PATH . '/config/' . $name . '.php';
        $overFile = BASE_PATH . '/config/overrides/' . $name . '.php';

        $base = is_file($coreFile) ? require $coreFile : [];
        if (!is_array($base)) {
            $base = [];
        }

        if (is_file($overFile)) {
            $over = require $overFile;
            if (is_array($over)) {
                $base = self::merge($base, $over);
            }
        }

        return self::$cache[$name] = $base;
    }

    public static function get(string $name, ?string $key = null, mixed $default = null): mixed
    {
        $cfg = self::load($name);
        if ($key === null) {
            return $cfg;
        }
        return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
    }

    /** Limpia la caché (tests). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Deep-merge: mapas se combinan recursivamente; listas/escalares del override ganan.
     * @param array<mixed> $base
     * @param array<mixed> $over
     * @return array<mixed>
     */
    private static function merge(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (
                is_string($k)
                && isset($base[$k])
                && is_array($base[$k]) && self::isAssoc($base[$k])
                && is_array($v) && self::isAssoc($v)
            ) {
                $base[$k] = self::merge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    /** @param array<mixed> $a */
    private static function isAssoc(array $a): bool
    {
        if ($a === []) {
            return false;
        }
        return array_keys($a) !== range(0, count($a) - 1);
    }
}
