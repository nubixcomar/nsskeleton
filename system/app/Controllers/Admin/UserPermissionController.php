<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Audit;
use App\Services\Rbac;
use Core\Auth;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Overrides de permisos por usuario (allow/deny sobre el rol).
 */
final class UserPermissionController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('admins.manage');
    }

    public function edit(Request $request, string $id): Response
    {
        $target = Database::selectOne('SELECT id, name, email, role FROM admin_users WHERE id = ? LIMIT 1', [(int) $id]);
        if ($target === null) {
            return $this->abort(404, 'Usuario no encontrado.');
        }

        $rolePerms = Rbac::permissionsFor((string) $target['role']);

        return $this->view('admin/users/permissions', [
            'user'      => Auth::user(),
            'target'    => $target,
            'breadcrumbExtra' => $target['name'] ?? $target['email'] ?? null,
            'catalog'   => Rbac::catalog(),
            'rolePerms' => $rolePerms,
            'overrides' => Rbac::userOverrides((int) $id),
            'error'     => Session::getFlash('error'),
            'success'   => Session::getFlash('success'),
        ], 'layouts/admin');
    }

    public function update(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);

        $target = Database::selectOne('SELECT id FROM admin_users WHERE id = ? LIMIT 1', [(int) $id]);
        if ($target === null) {
            return $this->abort(404, 'Usuario no encontrado.');
        }

        $eff = $request->input('eff');
        $eff = is_array($eff) ? $eff : [];

        foreach (array_keys(Rbac::catalog()) as $perm) {
            $value = (string) ($eff[$perm] ?? 'inherit');
            $effect = match ($value) {
                'allow' => true,
                'deny'  => false,
                default => null,
            };
            Rbac::setUserPermission((int) $id, $perm, $effect);
        }

        Audit::log('user.permissions.update', '', (string) $id);
        Session::flash('success', 'Permisos del usuario actualizados.');
        return $this->redirect(Url::to('/admin/users'));
    }
}
