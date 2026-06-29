<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Exportación de listados a CSV, Excel (HTML/.xls) y vista imprimible (PDF por navegador).
 * Sin dependencias externas.
 */
final class Exporter
{
    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $columns  clave => etiqueta
     */
    public static function csv(array $rows, array $columns): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        // BOM para que Excel respete UTF-8.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_values($columns));
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $line[] = self::scalar($row[$key] ?? '');
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $out = stream_get_contents($fh) ?: '';
        fclose($fh);
        return $out;
    }

    /**
     * Tabla HTML que Excel abre como hoja de cálculo (se sirve como .xls).
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $columns
     */
    public static function excelHtml(array $rows, array $columns, string $title): string
    {
        return self::htmlTable($rows, $columns, $title, false);
    }

    /**
     * Vista imprimible (el navegador la exporta a PDF con "Imprimir → Guardar como PDF").
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $columns
     */
    public static function printableHtml(array $rows, array $columns, string $title): string
    {
        return self::htmlTable($rows, $columns, $title, true);
    }

    public static function filename(string $base, string $ext): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $base));
        $slug = trim($slug, '-') ?: 'export';
        return $slug . '-' . date('Ymd-His') . '.' . $ext;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $columns
     */
    private static function htmlTable(array $rows, array $columns, string $title, bool $print): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars(self::scalar($v), ENT_QUOTES, 'UTF-8');

        $head = '';
        foreach ($columns as $label) {
            $head .= '<th>' . $e($label) . '</th>';
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach (array_keys($columns) as $key) {
                $body .= '<td>' . $e($row[$key] ?? '') . '</td>';
            }
            $body .= '</tr>';
        }

        $autoprint = $print ? '<script>window.onload=function(){window.print();}</script>' : '';

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>' . $e($title) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:24px;color:#1e293b}'
            . 'h1{font-size:18px}table{border-collapse:collapse;width:100%;font-size:12px}'
            . 'th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}'
            . 'th{background:#f1f5f9}</style>' . $autoprint . '</head><body>'
            . '<h1>' . $e($title) . '</h1>'
            . '<table><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>'
            . '</body></html>';
    }

    private static function scalar(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'Sí' : 'No';
        }
        return $v === null ? '' : (string) $v;
    }
}
