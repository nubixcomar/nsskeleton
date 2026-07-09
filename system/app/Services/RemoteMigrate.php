<?php

declare(strict_types=1);

namespace App\Services;

use Core\Env;

/**
 * Habilitación y verificación de token para la migración remota por web (v1.18 #4).
 *
 * Deriva del "otros (menores)" de la auditoría: provisionar una DB remota sin shell fue
 * tedioso. Este helper gatea un endpoint web one-shot que corre las migraciones pendientes.
 *
 * SEGURIDAD: deshabilitado por defecto. Solo se habilita si `MIGRATE_TOKEN` está seteado en el
 * `.env`. El token se compara constant-time. Pensado para provisionar y luego **borrar el
 * token** del `.env`.
 */
final class RemoteMigrate
{
    /** ¿Está habilitado? (hay token configurado) */
    public static function enabled(): bool
    {
        $token = Env::get('MIGRATE_TOKEN', '');
        return is_string($token) && trim($token) !== '';
    }

    /** Verifica el token provisto contra el del entorno (constant-time). */
    public static function tokenValid(?string $provided): bool
    {
        if (!self::enabled() || !is_string($provided) || $provided === '') {
            return false;
        }
        $expected = (string) Env::get('MIGRATE_TOKEN', '');
        return hash_equals($expected, $provided);
    }
}
