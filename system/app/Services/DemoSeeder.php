<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminUser;
use Core\Auth;
use Core\Database;
use Throwable;

/**
 * Datos de ejemplo para arrancar un proyecto con contenido y ver el dashboard "vivo".
 * Idempotente: seed() primero limpia los datos demo y los vuelve a crear (refresca fechas).
 * undo() los elimina. Todo lo demo lleva marcadores claros ([demo], @demo.local, etc.).
 */
final class DemoSeeder
{
    private const ADMINS = [
        ['Editor Demo', 'editor', 'editor@demo.local', 'editor', 'admin'],
        ['Viewer Demo', 'viewer', 'viewer@demo.local', 'viewer', 'admin'],
        ['Cliente Demo', 'cliente1', 'cliente@demo.local', 'viewer', 'cliente'],
        ['Vendedor Demo', 'vendedor1', 'vendedor@demo.local', 'editor', 'vendedor'],
    ];

    private const CRON = [
        ['[demo] Limpiar caché', 'job:cache:clear', '0 4 * * *'],
        ['[demo] Ping interno', 'job:demo:ping', '*/30 * * * *'],
        ['[demo] Procesar cola', 'job:queue:work', '*/5 * * * *'],
    ];

    public static function isSeeded(): bool
    {
        try {
            $row = Database::selectOne("SELECT COUNT(*) AS c FROM admin_users WHERE email LIKE '%@demo.local'");
            return (int) ($row['c'] ?? 0) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** Crea datos demo. Idempotente: si ya están sembrados, no hace nada. @return array<string,int> */
    public static function seed(): array
    {
        $n = ['admins' => 0, 'cron' => 0, 'emails' => 0, 'jobs' => 0, 'audit' => 0, 'notifs' => 0, 'webhooks' => 0, 'tokens' => 0];
        if (self::isSeeded()) {
            return $n; // ya sembrado: no duplica (para refrescar: --undo y volver a sembrar)
        }

        // Admins (con tipos de usuario variados, para el gráfico)
        foreach (self::ADMINS as [$name, $username, $email, $role, $type]) {
            AdminUser::create(['name' => $name, 'username' => $username, 'email' => $email, 'password' => Auth::hash('demo1234'), 'role' => $role, 'user_type' => $type, 'active' => 1]);
            $n['admins']++;
        }
        $adminId = (int) (Database::selectOne("SELECT id FROM admin_users ORDER BY id ASC LIMIT 1")['id'] ?? 1);

        // Cron
        foreach (self::CRON as [$cname, $cmd, $sched]) {
            Database::insert('INSERT INTO cron_tasks (name, command, schedule, active, last_status) VALUES (?, ?, ?, ?, ?)',
                [$cname, $cmd, $sched, 1, 'success']);
            $n['cron']++;
        }

        // Emails (últimos ~7 días, mezcla enviados/fallidos)
        $asuntos = ['Bienvenida', 'Resumen semanal', 'Factura disponible', 'Recuperar contraseña', 'Novedades', 'Alerta de stock'];
        for ($i = 0; $i < 42; $i++) {
            $failed = $i % 6 === 0;
            Database::insert('INSERT INTO email_log (to_address, subject, status, error, created_at) VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))',
                ['user' . ($i % 8) . '@demo.local', '[demo] ' . $asuntos[$i % count($asuntos)], $failed ? 'failed' : 'sent', $failed ? 'SMTP timeout (demo)' : null, $i * 4]);
            $n['emails']++;
        }

        // Jobs (mezcla de estados)
        for ($i = 0; $i < 28; $i++) {
            $st = $i % 5 === 0 ? 'failed' : ($i % 3 === 0 ? 'pending' : 'done');
            $payload = json_encode(['_demo' => true, 'message' => 'tarea ' . $i], JSON_UNESCAPED_UNICODE);
            $done = $st === 'done' ? 'DATE_SUB(NOW(), INTERVAL ' . ($i * 2) . ' HOUR)' : 'NULL';
            Database::run("INSERT INTO jobs (handler, payload, status, attempts, max_attempts, available_at, error, created_at, completed_at)
                           VALUES (?, ?, ?, ?, 3, NOW(), ?, DATE_SUB(NOW(), INTERVAL ? HOUR), {$done})",
                ['demo:log', $payload, $st, $st === 'done' ? 1 : ($st === 'failed' ? 3 : 0), $st === 'failed' ? 'Handler error (demo)' : null, $i * 3]);
            $n['jobs']++;
        }

        // Auditoría (últimos 7 días)
        $acciones = ['login', 'settings.update', 'admin.update', 'job.retry', 'webhook.create', 'roles.update', 'backup', 'login_failed'];
        for ($i = 0; $i < 32; $i++) {
            Database::insert('INSERT INTO audit_log (admin_id, admin_name, action, target, ip, created_at) VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))',
                [$adminId, 'Demo', $acciones[$i % count($acciones)], 'demo-' . $i, '127.0.0.1', $i * 5]);
            $n['audit']++;
        }

        // Notificaciones (para el primer admin)
        $notis = [['[demo] Backup completado', 'El backup diario terminó OK.', '/admin/backup'], ['[demo] Nuevo administrador', 'Se registró un administrador.', '/admin/users'], ['[demo] Job fallido', 'Un job de la cola falló.', '/admin/jobs'], ['[demo] Bienvenido', 'Explorá el dashboard de demo.', '/admin']];
        foreach ($notis as $k => [$title, $body, $url]) {
            Database::insert('INSERT INTO notifications (user_id, title, body, url, read_at, created_at) VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))',
                [$adminId, $title, $body, $url, $k > 1 ? date('Y-m-d H:i:s') : null, $k * 6]);
            $n['notifs']++;
        }

        // Webhooks
        foreach ([['ping', 'https://demo.local/webhook/ping'], ['admin.created', 'https://demo.local/webhook/users']] as [$ev, $url]) {
            Database::insert('INSERT INTO webhooks (event, url, secret, active) VALUES (?, ?, ?, 1)', [$ev, $url, bin2hex(random_bytes(8))]);
            $n['webhooks']++;
        }

        // Tokens API
        foreach ([['[demo] Integración lectura', 'read'], ['[demo] Integración total', 'read,write']] as [$tn, $sc]) {
            Database::insert('INSERT INTO api_tokens (admin_id, name, scopes, token_hash) VALUES (?, ?, ?, ?)',
                [$adminId, $tn, $sc, hash('sha256', 'demo_' . $tn . random_bytes(4))]);
            $n['tokens']++;
        }

        return $n;
    }

    /** Elimina todos los datos demo. @return array<string,int> */
    public static function undo(): array
    {
        $d = static fn (string $sql, array $p = []): int => (function () use ($sql, $p) {
            try { return Database::run($sql, $p)->rowCount(); } catch (Throwable) { return 0; }
        })();

        return [
            'admins'   => $d("DELETE FROM admin_users WHERE email LIKE '%@demo.local'"),
            'cron'     => $d("DELETE FROM cron_tasks WHERE name LIKE '[demo]%'"),
            'emails'   => $d("DELETE FROM email_log WHERE subject LIKE '[demo]%'"),
            'jobs'     => $d("DELETE FROM jobs WHERE handler = 'demo:log' AND payload LIKE '%\"_demo\":true%'"),
            'audit'    => $d("DELETE FROM audit_log WHERE admin_name = 'Demo'"),
            'notifs'   => $d("DELETE FROM notifications WHERE title LIKE '[demo]%'"),
            'webhooks' => $d("DELETE FROM webhooks WHERE url LIKE '%demo.local%'"),
            'tokens'   => $d("DELETE FROM api_tokens WHERE name LIKE '[demo]%'"),
        ];
    }
}
