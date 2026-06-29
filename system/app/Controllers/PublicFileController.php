<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\FileManager;
use App\Services\FileShare;
use Core\Request;
use Core\Response;

/**
 * Descarga pública de archivos compartidos por token (sin login). Ruta: /a/{token}.
 */
final class PublicFileController
{
    public function download(Request $request, string $token): Response
    {
        $share = FileShare::byToken($token);
        if ($share === null) {
            return Response::html('<h1>404</h1><p>Link inválido o revocado.</p>', 404);
        }

        $path = FileManager::resolve((string) $share['rel_path']);
        if ($path === null || !is_file($path)) {
            return Response::html('<h1>404</h1><p>El archivo ya no está disponible.</p>', 404);
        }

        FileShare::countDownload((int) $share['id']);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
