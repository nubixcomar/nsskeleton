<?php

declare(strict_types=1);

namespace Core;

use Throwable;

/**
 * Log de errores server-side, SIEMPRE activo (independiente de APP_DEBUG).
 *
 * Hallazgo 1.8 de la auditoría de nsCheckout: con `APP_DEBUG=false` (correcto en prod) un
 * 500 no dejaba rastro accesible → había que prender debug en producción (riesgoso) para
 * diagnosticar. Ahora toda excepción se escribe con traza a `storage/logs/error.log`, con
 * rotación básica, y se puede leer desde el panel (ver visor de logs).
 */
final class ErrorLog
{
    /** Tamaño máximo antes de rotar (5 MB). */
    private const MAX_BYTES = 5_000_000;

    /** Registra una excepción con traza completa. */
    public static function exception(Throwable $e): void
    {
        $msg = sprintf(
            "%s: %s in %s:%d\n%s",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        self::write('ERROR', $msg);
    }

    /** Escribe una línea de log con nivel arbitrario. */
    public static function write(string $level, string $message): void
    {
        $file = self::path();
        if ($file === null) {
            return;
        }
        self::rotateIfNeeded($file);
        $entry = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
        @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    /** Ruta del error.log (crea el dir si hace falta). Null si no se puede determinar. */
    public static function path(): ?string
    {
        if (!defined('BASE_PATH')) {
            return null;
        }
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return is_dir($dir) ? $dir . '/error.log' : null;
    }

    /**
     * Devuelve las últimas N líneas del error.log (para el visor de /admin).
     * @return list<string>
     */
    public static function tail(int $lines = 100): array
    {
        $file = self::path();
        if ($file === null || !is_file($file)) {
            return [];
        }
        $all = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        return array_slice($all, -max(1, $lines));
    }

    private static function rotateIfNeeded(string $file): void
    {
        if (is_file($file) && filesize($file) >= self::MAX_BYTES) {
            @rename($file, $file . '.1'); // conserva una generación previa
        }
    }
}
