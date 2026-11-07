<?php

declare(strict_types=1);

namespace Core;

/**
 * Cargador simple de archivos .env (sin dependencias).
 * Soporta comentarios (#, también al final de línea), comillas y expansión ${VAR}.
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
            $value = self::parseValue($value);

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
     * Valor crudo de una línea `CLAVE=valor` → valor final (semántica dotenv):
     *  - Entre comillas ("…" o '…'): se toma el contenido; lo que siga al cierre (ej. un
     *    `# comentario`) se ignora. Un `#` dentro de las comillas es parte del valor.
     *  - Sin comillas: un `#` precedido de espacio (o al inicio) abre un comentario hasta el
     *    final de la línea. Un `#` pegado al valor (`abc#123`) se conserva.
     * Así funcionan los comentarios al final de línea que usa `.env.example`.
     */
    public static function parseValue(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        $q = $value[0];
        if ($q === '"' || $q === "'") {
            $end = strpos($value, $q, 1);
            if ($end !== false) {
                return substr($value, 1, $end - 1);
            }
            // Comilla sin cerrar: se deja tal cual (compatibilidad).
            return $value;
        }

        if (str_starts_with($value, '#')) {
            return '';
        }
        $value = (string) preg_replace('/\s+#.*$/', '', $value);
        return rtrim($value);
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
