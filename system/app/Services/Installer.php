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
     * @param string|null $templateFile `.env.example` a usar (default: el de este proyecto).
     */
    public static function buildEnv(array $answers, ?string $templateFile = null): string
    {
        $template = @file_get_contents($templateFile ?? PROJECT_PATH . '/.env.example') ?: '';

        $map = [
            'APP_NAME'  => $answers['project_name'] ?? null,
            'APP_URL'   => $answers['app_url'] ?? null,
            'DB_HOST'   => $answers['db_host'] ?? null,
            'DB_PORT'   => $answers['db_port'] ?? null,
            'DB_NAME'   => $answers['db_name'] ?? null,
            'DB_USER'   => $answers['db_user'] ?? null,
            'DB_PASS'   => $answers['db_pass'] ?? null,
            'AI_PROVIDER' => $answers['ai_provider_runtime'] ?? null,
            'APP_KEY'   => $answers['app_key'] ?? null,
            'CORE_UPDATE_URL' => $answers['core_update_url'] ?? null,
        ];

        foreach ($map as $key => $value) {
            if ($value === null) {
                continue;
            }
            $line = $key . '=' . self::envValue((string) $value);
            // Callback y no string de reemplazo: un valor con `$1` o `\1` (ej. una contraseña)
            // no debe interpretarse como referencia al grupo capturado.
            $template = (string) preg_replace_callback(
                '/^' . preg_quote($key, '/') . '=.*$/m',
                static fn (): string => $line,
                $template,
                1
            );
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

    /** ¿El proyecto ya es un repositorio git? */
    public static function isGitRepo(string $projectPath): bool
    {
        return is_dir($projectPath . '/.git');
    }

    /** Comandos para inicializar git con un commit inicial. @return list<string> */
    public static function gitInitCommands(): array
    {
        return [
            'git init',
            'git add -A',
            'git commit -m "chore: commit inicial (proyecto nsSkeleton)"',
        ];
    }

    /**
     * Inicializa git (init + commit inicial) si el proyecto no es aún un repo. Así el proyecto
     * arranca con historial/rollback antes de tocar producción (hallazgo menor de la auditoría).
     * El $runner es inyectable para testear sin ejecutar git.
     *
     * @param (callable(string):int)|null $runner
     * @return array{ok:bool, done:bool, message:string, commands:list<string>}
     */
    public static function gitInit(string $projectPath, ?callable $runner = null): array
    {
        if (self::isGitRepo($projectPath)) {
            return ['ok' => true, 'done' => false, 'message' => 'Ya es un repositorio git.', 'commands' => []];
        }
        $runner ??= static function (string $cmd): int {
            $code = 0;
            passthru($cmd, $code);
            return $code;
        };
        $commands = self::gitInitCommands();
        foreach ($commands as $cmd) {
            $runner($cmd);
        }
        return ['ok' => true, 'done' => true, 'message' => 'Repositorio git inicializado con commit inicial.', 'commands' => $commands];
    }
}
