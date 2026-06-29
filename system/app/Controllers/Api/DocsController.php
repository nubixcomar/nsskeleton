<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\OpenApiGenerator;
use Core\Response;

/**
 * Documentación de la API: spec OpenAPI + visor self-contained (sin dependencias externas).
 */
final class DocsController
{
    public function openapi(): Response
    {
        return Response::json(OpenApiGenerator::spec());
    }

    public function ui(): Response
    {
        $spec = OpenApiGenerator::spec();
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $op) {
                $params = [];
                foreach ($op['parameters'] ?? [] as $p) {
                    $params[] = $p['name'] . ' (' . $p['in'] . ')';
                }
                $rows .= '<tr>'
                    . '<td><span class="m m-' . $e($method) . '">' . strtoupper($e($method)) . '</span></td>'
                    . '<td class="path">' . $e($path) . '</td>'
                    . '<td>' . $e($op['summary'] ?? '') . '</td>'
                    . '<td class="params">' . $e(implode(', ', $params)) . '</td>'
                    . '</tr>';
            }
        }

        $title = $e((string) $spec['info']['title']);
        $version = $e((string) $spec['info']['version']);

        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . ' — Docs</title>'
            . '<style>body{font-family:system-ui,Arial,sans-serif;margin:0;background:#f8fafc;color:#1e293b}'
            . 'header{background:#4f46e5;color:#fff;padding:20px 28px}h1{margin:0;font-size:20px}'
            . '.wrap{max-width:1000px;margin:24px auto;padding:0 20px}'
            . 'table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.06)}'
            . 'th,td{text-align:left;padding:10px 14px;border-bottom:1px solid #eef2f7;font-size:14px}th{background:#f1f5f9;color:#64748b}'
            . '.path{font-family:ui-monospace,monospace}.params{color:#64748b;font-size:12px}'
            . '.m{font-weight:700;font-size:11px;padding:2px 8px;border-radius:6px;color:#fff}'
            . '.m-get{background:#0ea5e9}.m-post{background:#22c55e}.m-put{background:#f59e0b}.m-delete{background:#ef4444}'
            . '.note{color:#64748b;font-size:13px;margin:12px 0}</style></head><body>'
            . '<header><h1>' . $title . ' <small style="opacity:.8">v' . $version . '</small></h1></header>'
            . '<div class="wrap">'
            . '<p class="note">Auth: <code>Authorization: Bearer &lt;token&gt;</code>. Spec: <a href="openapi">/api/openapi</a> (o <a href="openapi.json">openapi.json</a>) — importable en Swagger UI / Postman.</p>'
            . '<table><thead><tr><th>Método</th><th>Ruta</th><th>Descripción</th><th>Parámetros</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '</div></body></html>';

        return Response::html($html);
    }
}
