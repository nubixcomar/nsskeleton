<?php

declare(strict_types=1);

namespace Core;

/**
 * Cargador simple de archivos .env (sin dependencias).
 * Soporta comentarios (#), comillas y expansión ${VAR}.
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Quitar comillas envolventes.
            if (strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[strlen($value) - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            // Expandir referencias ${VAR}.
            $value = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', static function (array $m): string {
                $v = self::get($m[1], '');
                return is_scalar($v) ? (string) $v : '';
            }, $value);

            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Lee una variable de entorno con casteo de literales (true/false/null).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            ''      => $default,
            default => $value,
        };
    }
}
