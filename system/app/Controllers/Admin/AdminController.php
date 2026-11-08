<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Core\Auth;
use Core\Config;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Controlador base del backend: exige sesión de administrador **y** un permiso.
 *
 * Cada controlador declara el permiso que exige sobrescribiendo `requiredPermission()`
 * (o devuelve `self::AUTH_ONLY` si solo pide sesión, a propósito: perfil, notificaciones…).
 * Un controlador que no lo declara cae en el default `security.admin_default_permission`
 * (`modules.manage`): así un controlador nuevo nunca queda abierto a cualquier usuario por
 * omisión. Ese default es configurable (`null` = solo sesión, el comportamiento previo a 1.20.3).
 */
abstract class AdminController extends Controller
{
    /** "Solo sesión": el controlador no exige permiso (cada usuario opera sobre lo suyo). */
    public const AUTH_ONLY = '';

    public function __construct()
    {
        if (!Auth::check()) {
            Session::flash('error', 'Iniciá sesión para continuar.');
            Response::redirect(Url::to('/admin/login'))->send();
            exit;
        }
        // La sesión existe pero el usuario fue desactivado o borrado: afuera en este mismo request.
        if (Auth::user() === null) {
            Auth::logout();
            Session::flash('error', 'Tu sesión terminó o tu cuenta está desactivada.');
            Response::redirect(Url::to('/admin/login'))->send();
            exit;
        }
        $perm = $this->requiredPermission();
        if ($perm !== self::AUTH_ONLY) {
            $this->requirePermission($perm);
        }
    }

    /**
     * Permiso RBAC que exige todo el controlador. Sobrescribilo en cada controlador.
     * Default: `security.admin_default_permission` (`modules.manage`); `null` = solo sesión.
     */
    protected function requiredPermission(): string
    {
        $default = Config::get('security', 'admin_default_permission', 'modules.manage');
        return is_string($default) ? $default : self::AUTH_ONLY;
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
