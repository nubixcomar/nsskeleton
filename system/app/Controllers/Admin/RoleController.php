<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Audit;
use App\Services\Rbac;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Matriz de roles y permisos, editable por panel.
 */
final class RoleController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('admins.manage');
    }

    public function index(): Response
    {
        $matrix = [];
        foreach (Rbac::roles() as $role) {
            $matrix[$role] = Rbac::permissionsFor($role);
        }

        return $this->view('admin/roles/index', [
            'user'    => Auth::user(),
            'roles'   => Rbac::roles(),
            'catalog' => Rbac::catalog(),
            'matrix'  => $matrix,
            'success' => Session::getFlash('success'),
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $this->verifyCsrf($request);

        $perms = $request->input('perms');
        $perms = is_array($perms) ? $perms : [];

        foreach (Rbac::roles() as $role) {
            if ($role === 'superadmin') {
                continue;
            }
            $selected = $perms[$role] ?? [];
            Rbac::setRolePermissions($role, is_array($selected) ? array_map('strval', $selected) : []);
        }

        Audit::log('roles.update');
        Session::flash('success', 'Permisos de roles actualizados.');
        return $this->redirect(Url::to('/admin/roles'));
    }
}
