<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Librería de prompts reutilizables (config/prompts.php) con variables {{var}}.
 */
final class PromptLibrary
{
    /** @return array<string,string> */
    public static function all(): array
    {
        return require BASE_PATH . '/config/prompts.php';
    }

    /** @return array<int,string> */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    public static function get(string $name): ?string
    {
        return self::all()[$name] ?? null;
    }

    /**
     * Renderiza un prompt reemplazando {{var}} con las variables dadas.
     * Las variables faltantes se reemplazan por vacío.
     * @param array<string,mixed> $vars
     */
    public static function render(string $name, array $vars = []): string
    {
        $tpl = self::get($name);
        if ($tpl === null) {
            return '';
        }
        return (string) preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            static fn (array $m): string => (string) ($vars[$m[1]] ?? ''),
            $tpl
        );
    }
}
