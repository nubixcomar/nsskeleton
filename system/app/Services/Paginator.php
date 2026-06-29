<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Paginación + búsqueda reutilizable para listados.
 * meta() es matemática pura (testeable sin DB); paginate() consulta la base.
 */
final class Paginator
{
    public const PER_PAGE = 15;

    /**
     * Calcula los metadatos de paginación (sin tocar la base).
     * @return array{total:int,perPage:int,pages:int,page:int,from:int,to:int,hasPrev:bool,hasNext:bool}
     */
    public static function meta(int $total, int $page, int $perPage): array
    {
        $total = max(0, $total);
        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = min($page * $perPage, $total);

        return [
            'total'   => $total,
            'perPage' => $perPage,
            'pages'   => $pages,
            'page'    => $page,
            'from'    => $from,
            'to'      => $to,
            'hasPrev' => $page > 1,
            'hasNext' => $page < $pages,
        ];
    }

    /**
     * Pagina una tabla con búsqueda opcional (LIKE sobre columnas indicadas).
     *
     * @param array{page?:int,perPage?:int,order?:string,search?:string,searchable?:array<int,string>,filter?:string} $opts
     * @return array<string,mixed> meta() + ['rows'=>..., 'search'=>...]
     */
    public static function paginate(string $table, array $opts = []): array
    {
        $page = (int) ($opts['page'] ?? 1);
        $perPage = max(1, (int) ($opts['perPage'] ?? self::PER_PAGE));
        $order = $opts['order'] ?? 'id DESC';
        $search = trim((string) ($opts['search'] ?? ''));
        $searchable = $opts['searchable'] ?? [];
        $filter = trim((string) ($opts['filter'] ?? '')); // fragmento SQL fijo (no de usuario)

        $conds = [];
        $params = [];
        if ($search !== '' && $searchable !== []) {
            $likes = [];
            foreach ($searchable as $col) {
                $likes[] = "`{$col}` LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $conds[] = '(' . implode(' OR ', $likes) . ')';
        }
        if ($filter !== '') {
            $conds[] = $filter;
        }
        $where = $conds !== [] ? ' WHERE ' . implode(' AND ', $conds) : '';

        $totalRow = Database::selectOne("SELECT COUNT(*) AS c FROM `{$table}`{$where}", $params);
        $total = (int) ($totalRow['c'] ?? 0);

        $meta = self::meta($total, $page, $perPage);
        $offset = ($meta['page'] - 1) * $perPage;

        $rows = Database::select(
            "SELECT * FROM `{$table}`{$where} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return $meta + ['rows' => $rows, 'search' => $search];
    }
}
