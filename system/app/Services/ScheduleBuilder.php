<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Constructor de expresiones cron desde presets amigables + descripción humana.
 * Pensado para que un usuario no técnico arme el horario sin saber sintaxis cron.
 */
final class ScheduleBuilder
{
    private const DAYS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    /**
     * Convierte un preset en expresión cron de 5 campos.
     * @param array<string,mixed> $p type: minutes|hourly|daily|weekly|monthly
     */
    public static function fromPreset(array $p): string
    {
        $type = (string) ($p['type'] ?? 'minutes');
        $min = max(0, min(59, (int) ($p['minute'] ?? 0)));
        $hour = max(0, min(23, (int) ($p['hour'] ?? 0)));

        return match ($type) {
            'hourly'  => "{$min} * * * *",
            'daily'   => "{$min} {$hour} * * *",
            'weekly'  => "{$min} {$hour} * * " . max(0, min(6, (int) ($p['dow'] ?? 1))),
            'monthly' => "{$min} {$hour} " . max(1, min(31, (int) ($p['dom'] ?? 1))) . ' * *',
            default   => '*/' . max(1, min(59, (int) ($p['every'] ?? 5))) . ' * * * *', // minutes
        };
    }

    /** Describe en lenguaje natural una expresión cron de 5 campos. */
    public static function describe(string $expr): string
    {
        $parts = preg_split('/\s+/', trim($expr)) ?: [];
        if (count($parts) !== 5) {
            return $expr;
        }
        [$m, $h, $dom, $mon, $dow] = $parts;
        $time = static fn (): string => sprintf('%02d:%02d', is_numeric($h) ? (int) $h : 0, is_numeric($m) ? (int) $m : 0);
        $allDate = ($dom === '*' && $mon === '*');

        if ($m === '*' && $h === '*' && $allDate && $dow === '*') {
            return 'Cada minuto';
        }
        if (preg_match('#^\*/(\d+)$#', $m, $mm) && $h === '*' && $allDate && $dow === '*') {
            return "Cada {$mm[1]} minutos";
        }
        if (is_numeric($m) && $h === '*' && $allDate && $dow === '*') {
            return "Cada hora al minuto {$m}";
        }
        if (is_numeric($m) && is_numeric($h) && $allDate && $dow === '*') {
            return 'Todos los días a las ' . $time();
        }
        if (is_numeric($m) && is_numeric($h) && $dom === '*' && $mon === '*' && is_numeric($dow)) {
            return 'Los ' . (self::DAYS[(int) $dow] ?? $dow) . ' a las ' . $time();
        }
        if (is_numeric($m) && is_numeric($h) && is_numeric($dom) && $mon === '*' && $dow === '*') {
            return "El día {$dom} de cada mes a las " . $time();
        }
        return $expr;
    }
}
