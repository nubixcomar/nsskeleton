<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Evaluador de expresiones cron de 5 campos: minuto hora día-mes mes día-semana.
 * Soporta comodín, pasos (cada-n), rangos (a-b), rango con paso y listas (a,b,c).
 * Día de semana: 0-6 (0 = domingo).
 */
final class CronExpression
{
    /** Rango válido por campo. */
    private const RANGES = [
        [0, 59],  // minuto
        [0, 23],  // hora
        [1, 31],  // día del mes
        [1, 12],  // mes
        [0, 6],   // día de la semana
    ];

    public static function isValid(string $expr): bool
    {
        $parts = preg_split('/\s+/', trim($expr)) ?: [];
        if (count($parts) !== 5) {
            return false;
        }
        foreach ($parts as $field) {
            foreach (explode(',', $field) as $token) {
                $token = trim($token);
                if ($token === '' || !preg_match('/^(\*|\d+(-\d+)?)(\/\d+)?$/', $token)) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function isDue(string $expr, DateTimeImmutable $dt): bool
    {
        $parts = preg_split('/\s+/', trim($expr)) ?: [];
        if (count($parts) !== 5) {
            return false;
        }
        [$min, $hour, $dom, $mon, $dow] = $parts;

        $minOk  = self::match($min, (int) $dt->format('i'), 0, 59);
        $hourOk = self::match($hour, (int) $dt->format('G'), 0, 23);
        $monOk  = self::match($mon, (int) $dt->format('n'), 1, 12);

        $domRestricted = trim($dom) !== '*';
        $dowRestricted = trim($dow) !== '*';
        $domOk = self::match($dom, (int) $dt->format('j'), 1, 31);
        $dowOk = self::match($dow, (int) $dt->format('w'), 0, 6);

        // Semántica cron estándar: si ambos (día-mes y día-semana) están restringidos,
        // basta que coincida cualquiera; si no, se combinan con AND.
        $dayOk = ($domRestricted && $dowRestricted) ? ($domOk || $dowOk) : ($domOk && $dowOk);

        return $minOk && $hourOk && $monOk && $dayOk;
    }

    /** Próxima ejecución estrictamente posterior a $from (busca dentro de 1 año). */
    public static function nextRunAfter(string $expr, DateTimeImmutable $from): ?DateTimeImmutable
    {
        $cursor = $from
            ->setTime((int) $from->format('G'), (int) $from->format('i'), 0)
            ->modify('+1 minute');

        for ($i = 0; $i < 527040; $i++) { // ~366 días en minutos
            if (self::isDue($expr, $cursor)) {
                return $cursor;
            }
            $cursor = $cursor->modify('+1 minute');
        }
        return null;
    }

    private static function match(string $field, int $value, int $min, int $max): bool
    {
        foreach (explode(',', $field) as $token) {
            if (self::tokenMatch(trim($token), $value, $min, $max)) {
                return true;
            }
        }
        return false;
    }

    private static function tokenMatch(string $token, int $value, int $min, int $max): bool
    {
        $step = 1;
        $range = $token;

        if (str_contains($token, '/')) {
            [$range, $stepStr] = explode('/', $token, 2);
            $step = max(1, (int) $stepStr);
        }

        if ($range === '*' || $range === '') {
            $lo = $min;
            $hi = $max;
        } elseif (str_contains($range, '-')) {
            [$loS, $hiS] = explode('-', $range, 2);
            $lo = (int) $loS;
            $hi = (int) $hiS;
        } else {
            $n = (int) $range;
            if ($step > 1) {
                $lo = $n;
                $hi = $max;
            } else {
                return $value === $n;
            }
        }

        if ($value < $lo || $value > $hi) {
            return false;
        }
        return (($value - $lo) % $step) === 0;
    }
}
