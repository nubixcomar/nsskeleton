<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Parte mecánica del instalador: aplica las respuestas del Q&A para generar el `.env`
 * y la sección de stack de la documentación. La parte interactiva (preguntar) la maneja
 * el skill `installer` de la capa agéntica.
 */
final class Installer
{
    /**
     * Genera el contenido de `.env` a partir de `.env.example` + respuestas.
     * @param array<string,mixed> $answers
     */
    public static function buildEnv(array $answers): string
    {
        $template = @file_get_contents(PROJECT_PATH . '/.env.example') ?: '';

        $map = [
            'APP_NAME'  => $answers['project_name'] ?? null,
            'APP_URL'   => $answers['app_url'] ?? null,
            'DB_HOST'   => $answers['db_host'] ?? null,
            'DB_PORT'   => $answers['db_port'] ?? null,
            'DB_NAME'   => $answers['db_name'] ?? null,
            'DB_USER'   => $answers['db_user'] ?? null,
            'DB_PASS'   => $answers['db_pass'] ?? null,
            'AI_PROVIDER' => $answers['ai_provider_runtime'] ?? null,
        ];

        foreach ($map as $key => $value) {
            if ($value === null) {
                continue;
            }
            $line = $key . '=' . self::envValue((string) $value);
            $template = (string) preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $template, 1);
        }

        return $template;
    }

    private static function envValue(string $value): string
    {
        return ($value !== '' && (str_contains($value, ' ') || str_contains($value, '#')))
            ? '"' . $value . '"'
            : $value;
    }

    /**
     * Genera la sección "Stack elegido" para docs/stack.md.
     * @param array<string,mixed> $answers
     */
    public static function stackDoc(array $answers): string
    {
        $lang = (string) ($answers['language'] ?? 'php-mvc');
        $db = (string) ($answers['database'] ?? 'mysql');
        $front = (string) ($answers['frontend'] ?? 'tailwind-alpine');
        $ai = (string) ($answers['ai_target'] ?? 'none');
        $deploy = (string) ($answers['deploy_method'] ?? 'none');

        return "## Stack elegido para ESTE proyecto\n\n"
            . "- **Lenguaje:** {$lang}\n"
            . "- **Base de datos:** {$db}\n"
            . "- **CSS/JS:** {$front}\n"
            . "- **IA / herramienta agéntica:** {$ai}\n"
            . "- **Hosting / deploy:** {$deploy}\n";
    }

    /**
     * Resumen de las acciones que el instalador realizará con las respuestas.
     * @param array<string,mixed> $answers
     * @return array<int,string>
     */
    public static function summary(array $answers): array
    {
        return [
            'Generar .env (DB, app) desde .env.example',
            'Completar docs/stack.md y docs/brief.md',
            'Ajustar el adapter de stack (' . ($answers['adapter'] ?? 'php-mvc') . ')',
            'La IA arranca por AGENTS.md (sin carpetas propietarias)',
            ($answers['install_system_base'] ?? true) ? 'Migrar + seed del sistema base' : 'Omitir sistema base',
        ];
    }
}
