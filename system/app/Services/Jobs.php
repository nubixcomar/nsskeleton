<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Registro y ejecución de jobs internos (callables PHP) para el cron.
 */
final class Jobs
{
    /** @return array<string,callable> */
    public static function all(): array
    {
        return require BASE_PATH . '/config/jobs.php';
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

    /**
     * Ejecuta un job. Captura su salida (echo + valor de retorno).
     * @return array{ok:bool,output:string,code:int}
     */
    public static function run(string $name): array
    {
        $jobs = self::all();
        if (!isset($jobs[$name])) {
            return ['ok' => false, 'output' => "Job '{$name}' no existe.", 'code' => 1];
        }

        try {
            ob_start();
            $ret = ($jobs[$name])();
            $printed = (string) ob_get_clean();
            $value = is_string($ret) ? $ret : (is_scalar($ret) ? (string) $ret : '');
            return ['ok' => true, 'output' => trim($printed . $value), 'code' => 0];
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            return ['ok' => false, 'output' => $e->getMessage(), 'code' => 1];
        }
    }
}
