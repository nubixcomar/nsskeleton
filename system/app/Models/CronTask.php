<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Core\Model;

final class CronTask extends Model
{
    protected static string $table = 'cron_tasks';
    protected static array $fillable = [
        'name', 'command', 'schedule', 'active', 'priority', 'timeout',
        'last_run_at', 'last_status', 'last_output', 'next_run_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function recentRuns(int $taskId, int $limit = 10): array
    {
        return Database::select(
            'SELECT * FROM cron_runs WHERE task_id = ? ORDER BY id DESC LIMIT ' . (int) $limit,
            [$taskId]
        );
    }

    /**
     * Historial de ejecuciones (todas las tareas) con filtros y paginación.
     * Filtros: task_id (int|null), status (''|success|failed), q (busca en output/nombre).
     *
     * @param array{task_id?:?int,status?:string,q?:string} $filters
     * @return array{rows:array<int,array<string,mixed>>, total:int, page:int, pages:int, perPage:int}
     */
    public static function history(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['task_id'])) {
            $where[] = 'r.task_id = ?';
            $params[] = (int) $filters['task_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'r.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(r.output LIKE ? OR t.name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql = ' FROM cron_runs r LEFT JOIN cron_tasks t ON t.id = r.task_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $total = (int) (Database::selectOne('SELECT COUNT(*) c' . $sql, $params)['c'] ?? 0);
        $perPage = max(1, $perPage);
        $pages = (int) max(1, ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            'SELECT r.*, t.name AS task_name' . $sql . ' ORDER BY r.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** Nombres de tareas presentes en el historial, para el filtro. @return array<int,array<string,mixed>> */
    public static function tasksWithRuns(): array
    {
        return Database::select(
            'SELECT DISTINCT t.id, t.name FROM cron_runs r JOIN cron_tasks t ON t.id = r.task_id ORDER BY t.name ASC'
        );
    }

    /** Borra solo las ejecuciones exitosas (deja el log de errores). Devuelve cuántas. */
    public static function clearOkRuns(): int
    {
        return Database::affected("DELETE FROM cron_runs WHERE status = 'success'");
    }

    /** Vacía todo el historial de ejecuciones. Devuelve cuántas. */
    public static function clearAllRuns(): int
    {
        return Database::affected('DELETE FROM cron_runs');
    }
}
