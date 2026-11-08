<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Core\Auth;
use Core\ErrorLog;
use Core\Request;
use Core\Response;

/**
 * Visor de logs de errores (solo lectura) para /admin.
 *
 * Hallazgo 1.8 de la auditoría de nsCheckout: diagnosticar un 500 en producción obligaba a
 * prender APP_DEBUG (riesgoso). Con `Core\ErrorLog` escribiendo siempre a
 * `storage/logs/error.log`, este visor permite leerlo desde el panel sin shell ni debug.
 */
final class LogViewerController extends AdminController
{
    protected function requiredPermission(): string
    {
        return 'logs.view';
    }

    public function index(Request $request): Response
    {
        $lines = max(20, min(1000, (int) $request->input('lines', 200)));
        $entries = ErrorLog::tail($lines);
        // Más recientes arriba.
        $entries = array_reverse($entries);

        return $this->view('admin/logs/index', [
            'user'    => Auth::user(),
            'entries' => $entries,
            'lines'   => $lines,
            'path'    => ErrorLog::path(),
        ], 'layouts/admin');
    }
}
