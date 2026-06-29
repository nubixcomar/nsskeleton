<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AlertService;
use App\Services\Backup;
use App\Services\Charts;
use App\Services\Health;
use App\Services\Notifier;
use App\Services\Rbac;
use App\Services\UserTypes;
use Core\Auth;
use Core\Database;
use Core\Response;
use Throwable;

/**
 * Dashboard: resumen del sistema + demo/showcase de las funcionalidades del core.
 * Pensado como plantilla reutilizable (KPIs, gráficos, salud, novedades, actividad, módulos).
 */
final class DashboardController extends AdminController
{
    public function index(): Response
    {
        $stats = $this->gatherStats();
        $jobs = $stats['jobs'];

        $charts = [
            Charts::bar('chartResources', 'Recursos del sistema',
                ['Admins', 'Cron', 'Emails', 'Backups', 'Jobs', 'Webhooks'],
                [$stats['admins'], $stats['cronTasks'], $stats['emailsTotal'], $stats['backups'], $stats['jobsTotal'], $stats['webhooks']]),
            Charts::doughnut('chartEmails', 'Emails por estado',
                ['Enviados', 'Fallidos'], [$stats['emailsSent'], $stats['emailsFailed']]),
            Charts::line('chartEmails7d', 'Emails (últimos 7 días)',
                $stats['email7dLabels'], $stats['email7dData']),
            Charts::doughnut('chartJobs', 'Cola de jobs',
                ['Hechos', 'Pendientes', 'Fallidos'], [$jobs['done'], $jobs['pending'], $jobs['failed']]),
            Charts::doughnut('chartUserTypes', 'Usuarios por tipo',
                $stats['userTypeLabels'], $stats['userTypeData']),
            Charts::bar('chartActivity7d', 'Actividad (últimos 7 días)',
                $stats['activity7dLabels'], $stats['activity7dData']),
        ];

        $preset = \App\Services\Dashboard::active();

        return $this->view('admin/dashboard', [
            'user'        => Auth::user(),
            'blocks'      => \App\Services\Dashboard::blocks(),
            'presetLabel' => \App\Services\Dashboard::presets()[$preset]['label'] ?? 'Completo',
            'stats'       => $stats,
            'charts'      => $charts,
            'alerts'      => AlertService::all(),
            'health'      => Health::full(),
            'kpis'        => $this->kpis($stats),
            'novedades'   => $this->novedades(),
            'recentAudit' => $this->recentAudit(),
            'recentJobs'  => $this->recentJobs(),
            'modules'     => $this->moduleGallery(),
        ], 'layouts/admin');
    }

    /** @return array<string,mixed> */
    private function gatherStats(): array
    {
        [$labels, $data] = $this->emailsLast7Days();
        [$aLabels, $aData] = $this->activityLast7Days();

        $jobs = [
            'pending' => $this->count('jobs', "status = 'pending'"),
            'done'    => $this->count('jobs', "status = 'done'"),
            'failed'  => $this->count('jobs', "status = 'failed'"),
        ];

        [$utLabels, $utData] = $this->userTypeDistribution();

        return [
            'admins'        => $this->count('admin_users'),
            'adminsActive'  => $this->count('admin_users', 'active = 1'),
            'cronTasks'     => $this->count('cron_tasks'),
            'cronActive'    => $this->count('cron_tasks', 'active = 1'),
            'emailsTotal'   => $this->count('email_log'),
            'emailsSent'    => $this->count('email_log', "status = 'sent'"),
            'emailsFailed'  => $this->count('email_log', "status = 'failed'"),
            'emailsQueue'   => $this->count('email_queue', "status = 'pending'"),
            'backups'       => count(Backup::list()),
            'apiTokens'     => $this->count('api_tokens'),
            'webhooks'      => $this->count('webhooks', 'active = 1'),
            'notifications' => Notifier::unreadCount((int) Auth::id()),
            'auditEntries'  => $this->count('audit_log'),
            'jobs'          => $jobs,
            'jobsTotal'     => $jobs['pending'] + $jobs['done'] + $jobs['failed'],
            'email7dLabels' => $labels,
            'email7dData'   => $data,
            'activity7dLabels' => $aLabels,
            'activity7dData'   => $aData,
            'userTypeLabels' => $utLabels,
            'userTypeData'   => $utData,
        ];
    }

    /** Tarjetas KPI. @return array<int,array<string,mixed>> */
    private function kpis(array $s): array
    {
        return [
            ['label' => 'Administradores', 'value' => $s['admins'], 'sub' => $s['adminsActive'] . ' activos', 'icon' => 'user-circle', 'color' => 'indigo', 'url' => '/admin/users'],
            ['label' => 'Tareas cron', 'value' => $s['cronActive'] . '/' . $s['cronTasks'], 'sub' => 'activas / total', 'icon' => 'clock', 'color' => 'emerald', 'url' => '/admin/cron'],
            ['label' => 'Cola de jobs', 'value' => $s['jobs']['done'], 'sub' => $s['jobs']['pending'] . ' pend · ' . $s['jobs']['failed'] . ' fallidos', 'icon' => 'queue', 'color' => 'sky', 'url' => '/admin/jobs'],
            ['label' => 'Emails', 'value' => $s['emailsSent'], 'sub' => $s['emailsFailed'] . ' fallidos · ' . $s['emailsQueue'] . ' en cola', 'icon' => 'envelope', 'color' => 'amber', 'url' => '/admin/mail'],
            ['label' => 'Backups', 'value' => $s['backups'], 'sub' => 'copias guardadas', 'icon' => 'archive', 'color' => 'rose', 'url' => '/admin/backup'],
            ['label' => 'Webhooks', 'value' => $s['webhooks'], 'sub' => 'activos', 'icon' => 'bolt', 'color' => 'violet', 'url' => '/admin/webhooks'],
            ['label' => 'Tokens API', 'value' => $s['apiTokens'], 'sub' => 'integraciones', 'icon' => 'key', 'color' => 'cyan', 'url' => '/admin/api-tokens'],
            ['label' => 'Auditoría', 'value' => $s['auditEntries'], 'sub' => 'eventos registrados', 'icon' => 'list', 'color' => 'slate', 'url' => '/admin/audit'],
        ];
    }

    /** Novedades simuladas (feed de ejemplo). @return array<int,array<string,string>> */
    private function novedades(): array
    {
        return [
            ['tag' => 'Nuevo', 'color' => 'emerald', 'title' => 'Editor visual (WYSIWYG)', 'text' => 'Los campos de texto ahora tienen un editor enriquecido nativo.'],
            ['tag' => 'Mejora', 'color' => 'indigo', 'title' => 'Cronmaster v2', 'text' => 'Programá tareas con presets (diario, semanal…) sin saber sintaxis cron.'],
            ['tag' => 'Seguridad', 'color' => 'rose', 'title' => '2FA disponible', 'text' => 'Activá verificación en dos pasos (TOTP) desde Mi perfil.'],
            ['tag' => 'API', 'color' => 'sky', 'title' => 'Webhooks salientes', 'text' => 'Enviá eventos del sistema a URLs externas con firma HMAC.'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function recentAudit(): array
    {
        try {
            return Database::select('SELECT action, admin_name, target, created_at FROM audit_log ORDER BY id DESC LIMIT 6');
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function recentJobs(): array
    {
        try {
            return Database::select('SELECT handler, status, attempts, created_at FROM jobs ORDER BY id DESC LIMIT 6');
        } catch (Throwable) {
            return [];
        }
    }

    /** Galería de módulos del core (showcase). @return array<int,array<string,string>> */
    private function moduleGallery(): array
    {
        $all = [
            ['icon' => 'user-circle', 'title' => 'Usuarios y roles', 'desc' => 'RBAC editable, permisos por usuario, 2FA.', 'url' => '/admin/users', 'perm' => 'admins.manage'],
            ['icon' => 'list', 'title' => 'Auditoría', 'desc' => 'Registro de acciones con diff (antes/después).', 'url' => '/admin/audit', 'perm' => 'audit.view'],
            ['icon' => 'folder', 'title' => 'Archivos', 'desc' => 'Carpetas, subida, links públicos por token.', 'url' => '/admin/files', 'perm' => 'files.manage'],
            ['icon' => 'clock', 'title' => 'Tareas / Cron', 'desc' => 'Programador con presets, prioridad y reintentos.', 'url' => '/admin/cron', 'perm' => 'cron.manage'],
            ['icon' => 'queue', 'title' => 'Cola de jobs', 'desc' => 'Procesos en background con reintentos y panel.', 'url' => '/admin/jobs', 'perm' => 'cron.manage'],
            ['icon' => 'envelope', 'title' => 'Emails', 'desc' => 'SMTP propio, plantillas y cola de envío.', 'url' => '/admin/mail', 'perm' => 'mail.manage'],
            ['icon' => 'cpu', 'title' => 'Conector IA', 'desc' => 'OpenAI, Deepseek y Anthropic (Claude) + streaming.', 'url' => '/admin/ai', 'perm' => 'ai.manage'],
            ['icon' => 'key', 'title' => 'API REST', 'desc' => 'Tokens, scopes, rate-limit y OpenAPI.', 'url' => '/admin/api-tokens', 'perm' => 'api.manage'],
            ['icon' => 'bolt', 'title' => 'Webhooks', 'desc' => 'Eventos → URLs externas con firma HMAC.', 'url' => '/admin/webhooks', 'perm' => 'api.manage'],
            ['icon' => 'archive', 'title' => 'Backups', 'desc' => 'Copias de base y archivos + restauración.', 'url' => '/admin/backup', 'perm' => 'backup.manage'],
            ['icon' => 'heart', 'title' => 'Estado / Salud', 'desc' => 'Métricas, versiones y feature flags.', 'url' => '/admin/health', 'perm' => 'dashboard.view'],
            ['icon' => 'adjustments', 'title' => 'Configuración', 'desc' => 'Branding, zona horaria, versión de app.', 'url' => '/admin/settings', 'perm' => 'settings.manage'],
        ];
        return array_values(array_filter($all, static fn (array $m): bool => Rbac::can($m['perm'])));
    }

    private function count(string $table, string $where = ''): int
    {
        try {
            $sql = 'SELECT COUNT(*) AS n FROM `' . $table . '`' . ($where !== '' ? ' WHERE ' . $where : '');
            return (int) (Database::selectOne($sql)['n'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array{0:array<int,string>,1:array<int,int>} */
    private function emailsLast7Days(): array
    {
        return $this->seriesLast7Days('email_log');
    }

    /** @return array{0:array<int,string>,1:array<int,int>} */
    private function activityLast7Days(): array
    {
        return $this->seriesLast7Days('audit_log');
    }

    /** Serie diaria (últimos 7 días) de una tabla con created_at. @return array{0:array<int,string>,1:array<int,int>} */
    private function seriesLast7Days(string $table): array
    {
        $labels = [];
        $data = [];
        $map = [];
        try {
            $rows = Database::select(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM `{$table}`
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)"
            );
            foreach ($rows as $r) {
                $map[(string) $r['d']] = (int) $r['c'];
            }
        } catch (Throwable) {
            // tabla no migrada: queda en ceros
        }
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} day"));
            $labels[] = date('d/m', strtotime($day));
            $data[] = $map[$day] ?? 0;
        }
        return [$labels, $data];
    }

    /** @return array{0:array<int,string>,1:array<int,int>} */
    private function userTypeDistribution(): array
    {
        $labels = [];
        $data = [];
        try {
            $rows = Database::select('SELECT user_type, COUNT(*) AS c FROM admin_users GROUP BY user_type');
            foreach ($rows as $r) {
                $labels[] = UserTypes::label((string) $r['user_type']);
                $data[] = (int) $r['c'];
            }
        } catch (Throwable) {
            // sin columna: vacío
        }
        if ($labels === []) {
            $labels = ['Administrador'];
            $data = [1];
        }
        return [$labels, $data];
    }
}
