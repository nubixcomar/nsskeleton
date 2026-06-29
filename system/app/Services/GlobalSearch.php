<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Throwable;

/**
 * Búsqueda global: recorre los módulos registrados (config/modules_menu.php) y busca
 * el término en sus columnas de texto, agrupando los resultados por módulo.
 */
final class GlobalSearch
{
    /** Deriva la tabla del path del módulo: /admin/contactos → contactos. */
    public static function tableFromPath(string $path): string
    {
        $parts = explode('/', trim($path, '/'));
        return self::safe((string) end($parts)) ? (string) end($parts) : '';
    }

    /** @return array<int,string> columnas de texto buscables (varchar/char/text) */
    public static function textColumns(string $table): array
    {
        if (!self::safe($table)) {
            return [];
        }
        try {
            $rows = Database::select("SHOW COLUMNS FROM `{$table}`");
        } catch (Throwable) {
            return [];
        }
        $cols = [];
        foreach ($rows as $row) {
            $type = strtolower((string) ($row['Type'] ?? ''));
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '' && (str_starts_with($type, 'varchar') || str_starts_with($type, 'char') || str_contains($type, 'text'))) {
                $cols[] = $field;
            }
        }
        return $cols;
    }

    /**
     * @return array<int,array{label:string,path:string,matches:array<int,array{id:int,label:string,url:string}>}>
     */
    public static function search(string $query, int $perModule = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $menuFile = BASE_PATH . '/config/modules_menu.php';
        $modules = is_file($menuFile) ? (require $menuFile) : [];

        $groups = [];
        foreach ($modules as $module) {
            $path = (string) ($module['path'] ?? '');
            $table = self::tableFromPath($path);
            if ($table === '') {
                continue;
            }
            $cols = self::textColumns($table);
            if ($cols === []) {
                continue;
            }

            $likes = [];
            $params = [];
            foreach ($cols as $col) {
                $likes[] = "`{$col}` LIKE ?";
                $params[] = '%' . $query . '%';
            }
            $where = '(' . implode(' OR ', $likes) . ')';
            if (self::hasColumn($table, 'deleted_at')) {
                $where .= ' AND deleted_at IS NULL';
            }

            try {
                $rows = Database::select(
                    "SELECT * FROM `{$table}` WHERE {$where} ORDER BY id DESC LIMIT {$perModule}",
                    $params
                );
            } catch (Throwable) {
                continue;
            }
            if ($rows === []) {
                continue;
            }

            $labelCol = RelationOptions::labelColumn($table);
            $matches = [];
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $matches[] = [
                    'id'    => $id,
                    'label' => (string) ($row[$labelCol] ?? ('#' . $id)),
                    'url'   => $path . '/' . $id . '/edit',
                ];
            }
            $groups[] = [
                'label'   => (string) ($module['label'] ?? $table),
                'path'    => $path,
                'matches' => $matches,
            ];
        }
        return $groups;
    }

    private static function hasColumn(string $table, string $column): bool
    {
        return in_array($column, array_map(
            static fn (array $r): string => (string) $r['Field'],
            self::columnsRaw($table)
        ), true);
    }

    /** @return array<int,array<string,mixed>> */
    private static function columnsRaw(string $table): array
    {
        if (!self::safe($table)) {
            return [];
        }
        try {
            return Database::select("SHOW COLUMNS FROM `{$table}`");
        } catch (Throwable) {
            return [];
        }
    }

    private static function safe(string $name): bool
    {
        return preg_match('/^[a-z0-9_]+$/', $name) === 1;
    }
}
