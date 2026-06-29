<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Throwable;

/**
 * Resuelve opciones de un campo de relación (FK): id => etiqueta legible.
 * Usado por los módulos generados con relaciones `belongsTo`.
 */
final class RelationOptions
{
    /** Columnas candidatas a "etiqueta" del registro relacionado, por prioridad. */
    private const LABEL_CANDIDATES = ['nombre', 'name', 'titulo', 'title', 'razon_social', 'descripcion', 'email'];

    private static function safeTable(string $table): bool
    {
        return preg_match('/^[a-z0-9_]+$/', $table) === 1;
    }

    public static function labelColumn(string $table): string
    {
        if (!self::safeTable($table)) {
            return 'id';
        }
        try {
            $rows = Database::select("SHOW COLUMNS FROM `{$table}`");
        } catch (Throwable) {
            return 'id';
        }
        $cols = array_map(static fn (array $r): string => (string) $r['Field'], $rows);
        foreach (self::LABEL_CANDIDATES as $candidate) {
            if (in_array($candidate, $cols, true)) {
                return $candidate;
            }
        }
        return 'id';
    }

    /** @return array<int,string> id => etiqueta */
    public static function forTable(string $table): array
    {
        if (!self::safeTable($table)) {
            return [];
        }
        $label = self::labelColumn($table);
        $expr = $label === 'id' ? 'id AS label' : "`{$label}` AS label";
        try {
            $rows = Database::select("SELECT id, {$expr} FROM `{$table}` ORDER BY label ASC LIMIT 1000");
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = (string) ($row['label'] ?? $row['id']);
        }
        return $out;
    }
}
