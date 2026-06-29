<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Controlador base del backend: exige sesión de administrador.
 * Cualquier controlador que extienda este queda protegido.
 */
abstract class AdminController extends Controller
{
    public function __construct()
    {
        if (!Auth::check()) {
            Session::flash('error', 'Iniciá sesión para continuar.');
            Response::redirect(Url::to('/admin/login'))->send();
            exit;
        }
    }

    /** Valida el token CSRF del request; aborta si no coincide. */
    protected function verifyCsrf(\Core\Request $request): void
    {
        if (!Session::verifyCsrf((string) $request->input('_csrf', ''))) {
            Response::html('<h1>419 — Token inválido</h1><p>Recargá la página e intentá de nuevo.</p>', 419)->send();
            exit;
        }
    }

    /** Exige un permiso RBAC; si no lo tiene, corta con 403. */
    protected function requirePermission(string $permission): void
    {
        if (!\App\Services\Rbac::can($permission)) {
            \Core\View::render('errors/403', [], 'layouts/error', 403)->send();
            exit;
        }
    }
}
