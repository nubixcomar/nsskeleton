<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CronMaster;
use App\Services\CronRunner;
use Core\ErrorLog;
use Core\Request;
use Core\Response;
use DateTimeImmutable;

/**
 * Endpoint público del cron master (v1.19 fase 1): un disparador externo (EasyCron o `curl`
 * del servidor) pega a `/cron/run?key=SECRETO` (o header `X-Cron-Key`) y corre las tareas
 * vencidas. Protegido por toggle + secreto (constant-time) + allowlist de IP.
 *
 *   GET|POST /cron/run?key=<secreto>
 */
final class CronRunController
{
    public function run(Request $request): Response
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        // 1) Toggle global.
        if (!CronMaster::enabled()) {
            return Response::json(['ok' => false, 'error' => 'cron_master_disabled'], 503);
        }

        // 2) Secreto por ?key= o header X-Cron-Key (constant-time).
        $provided = (string) $request->query('key', $request->header('X-Cron-Key', ''));
        if (!CronMaster::verifyKey($provided)) {
            ErrorLog::write('WARN', "cron master: acceso denegado (bad_key) desde ip={$ip}");
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        // 3) Allowlist de IP (si está configurada).
        if (!CronMaster::ipAllowed($ip)) {
            ErrorLog::write('WARN', "cron master: acceso denegado (ip_blocked) desde ip={$ip}");
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        // 4) Ejecutar las tareas vencidas (trigger = master). El propio runner aplica
        //    lock anti-solapamiento + throttle min_interval.
        $ran = CronRunner::runDue(new DateTimeImmutable('now'), 'master');

        $errors = 0;
        foreach ($ran as $r) {
            if (($r['status'] ?? '') !== 'success') {
                $errors++;
            }
        }

        return Response::json([
            'ok'     => true,
            'ran'    => count($ran),
            'errors' => $errors,
            'tasks'  => $ran,
        ]);
    }
}
