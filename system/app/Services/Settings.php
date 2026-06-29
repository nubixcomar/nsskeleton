<?php

declare(strict_types=1);

namespace App\Services;

use Core\Crypto;
use Core\Database;

/**
 * Configuración persistente clave/valor agrupada (tabla `settings`).
 * Cachea en memoria durante la petición. Los valores cifrados (secretos) se
 * descifran automáticamente al leerse; usá setSecret() para guardarlos.
 */
final class Settings
{
    /** @var array<string,array{value:?string,group:string}> */
    private static array $cache = [];
    private static bool $loaded = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        if (!array_key_exists($key, self::$cache)) {
            return $default;
        }
        return Crypto::maybeDecrypt(self::$cache[$key]['value']);
    }

    /** @return array<string,?string> Valores del grupo, con la clave "corta" (sin prefijo). */
    public static function group(string $group): array
    {
        self::load();
        $out = [];
        foreach (self::$cache as $key => $row) {
            if ($row['group'] !== $group) {
                continue;
            }
            $short = str_starts_with($key, $group . '.') ? substr($key, strlen($group) + 1) : $key;
            $out[$short] = Crypto::maybeDecrypt($row['value']);
        }
        return $out;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        Database::run(
            'INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`)',
            [$key, $value === null ? null : (string) $value, $group]
        );
        self::$cache = [];
        self::$loaded = false;
    }

    /** Guarda un secreto cifrado en reposo (AES-256-GCM con APP_KEY). */
    public static function setSecret(string $key, mixed $value, string $group = 'general'): void
    {
        self::set($key, Crypto::encrypt((string) $value), $group);
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        foreach (Database::select('SELECT `key`, `value`, `group` FROM settings') as $r) {
            self::$cache[(string) $r['key']] = ['value' => $r['value'], 'group' => (string) $r['group']];
        }
        self::$loaded = true;
    }
}
