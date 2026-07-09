<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Migrator;
use App\Services\RemoteMigrate;
use Core\ErrorLog;
use Core\Request;
use Core\Response;

/**
 * Endpoint web de migración remota, protegido por token (v1.18 #4).
 *
 *   GET  /_migrate?token=XXX   → estado de las migraciones (solo lectura)
 *   POST /_migrate?token=XXX   → aplica las migraciones pendientes
 *
 * Deshabilitado por defecto: solo responde si `MIGRATE_TOKEN` está en el `.env`. Sin token o
 * con token inválido devuelve 404 (no revela que existe). Ideal para provisionar una DB remota
 * sin shell y luego quitar el token.
 */
final class MigrateController
{
    public function handle(Request $request): Response
    {
        // Deshabilitado o token inválido → 404 (no filtra la existencia del endpoint).
        if (!RemoteMigrate::enabled() || !RemoteMigrate::tokenValid((string) $request->input('token', ''))) {
            return Response::json(['error' => 'not found'], 404);
        }

        try {
            if ($request->method() === 'POST') {
                $applied = Migrator::migrate();
                ErrorLog::write('INFO', 'Migración remota: aplicadas ' . count($applied) . ' → ' . implode(', ', $applied));
                return Response::json([
                    'ok'      => true,
                    'applied' => $applied,
                    'count'   => count($applied),
                ]);
            }

            // GET → estado.
            return Response::json([
                'ok'     => true,
                'status' => Migrator::status(),
            ]);
        } catch (\Throwable $e) {
            ErrorLog::exception($e);
            return Response::json(['ok' => false, 'error' => 'migration failed'], 500);
        }
    }
}
