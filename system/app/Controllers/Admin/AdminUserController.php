<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminUser;
use App\Services\Audit;
use App\Services\Paginator;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Gestión de perfiles de administradores (CRUD).
 */
final class AdminUserController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('admins.manage');
    }

    public function index(Request $request): Response
    {
        $pagination = Paginator::paginate('admin_users', [
            'page'       => (int) $request->query('page', 1),
            'search'     => (string) $request->query('search', ''),
            'searchable' => ['name', 'email', 'role'],
            'order'      => 'name ASC',
        ]);

        return $this->view('admin/users/index', [
            'user'       => Auth::user(),
            'admins'     => $pagination['rows'],
            'pagination' => $pagination,
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return $this->view('admin/users/form', [
            'user'    => Auth::user(),
            'editing' => null,
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        $this->verifyCsrf($request);

        $data = $this->validate($request, null);
        if (is_string($data)) {
            Session::flash('error', $data);
            return $this->redirect(Url::to('/admin/users/create'));
        }

        AdminUser::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'username'  => $data['username'],
            'password'  => Auth::hash($data['password']),
            'role'      => $data['role'],
            'user_type' => $data['user_type'],
            'active'    => $data['active'],
        ]);

        Audit::log('admin.create', $data['email']);
        \App\Services\Notifier::notifyAll(
            'Nuevo administrador',
            ($data['name'] ?? $data['email']) . ' fue agregado al sistema.',
            '/admin/users',
            (int) Auth::id()
        );
        \App\Services\Webhook::dispatch('admin.created', ['email' => $data['email'], 'name' => $data['name'] ?? '']);
        Session::flash('success', 'Administrador creado.');
        return $this->redirect(Url::to('/admin/users'));
    }

    public function edit(Request $request, string $id): Response
    {
        $admin = AdminUser::find((int) $id);
        if ($admin === null) {
            return $this->abort(404, 'Administrador no encontrado.');
        }

        return $this->view('admin/users/form', [
            'user'    => Auth::user(),
            'editing' => $admin,
            'breadcrumbExtra' => $admin['name'] ?? $admin['email'] ?? null,
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function update(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);

        $admin = AdminUser::find((int) $id);
        if ($admin === null) {
            return $this->abort(404, 'Administrador no encontrado.');
        }

        $data = $this->validate($request, (int) $id);
        if (is_string($data)) {
            Session::flash('error', $data);
            return $this->redirect(Url::to('/admin/users/' . $id . '/edit'));
        }

        $fields = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'username'  => $data['username'],
            'role'      => $data['role'],
            'user_type' => $data['user_type'],
            'active'    => $data['active'],
        ];
        if ($data['password'] !== '') {
            $fields['password'] = Auth::hash($data['password']);
        }

        AdminUser::update((int) $id, $fields);

        // Auditoría con diff (antes/después), sin exponer el hash de contraseña.
        $after = ['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role'], 'active' => $data['active']];
        $before = ['name' => $admin['name'] ?? null, 'email' => $admin['email'] ?? null, 'role' => $admin['role'] ?? null, 'active' => $admin['active'] ?? null];
        Audit::logChange('admin.update', $data['email'], $before, $after);
        Session::flash('success', 'Administrador actualizado.');
        return $this->redirect(Url::to('/admin/users'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);

        if ((int) $id === Auth::id()) {
            Session::flash('error', 'No podés eliminar tu propio usuario.');
            return $this->redirect(Url::to('/admin/users'));
        }

        AdminUser::delete((int) $id);
        Audit::log('admin.delete', (string) $id);
        Session::flash('success', 'Administrador eliminado.');
        return $this->redirect(Url::to('/admin/users'));
    }

    /**
     * @return array<string,mixed>|string  Array validado, o string con el mensaje de error.
     */
    private function validate(Request $request, ?int $exceptId): array|string
    {
        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $username = trim((string) $request->input('username', ''));
        $username = $username !== '' ? $username : null;
        $password = (string) $request->input('password', '');
        $role = trim((string) $request->input('role', 'admin')) ?: 'admin';
        $userType = trim((string) $request->input('user_type', 'admin')) ?: 'admin';
        if (!\App\Services\UserTypes::isValid($userType)) {
            $userType = 'admin';
        }
        $active = $request->input('active') ? 1 : 0;

        if ($name === '' || $email === '') {
            return 'Nombre y email son obligatorios.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'El email no es válido.';
        }
        if ($username !== null && preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username) !== 1) {
            return 'El usuario debe tener 3-60 caracteres (letras, números, . _ -).';
        }
        if ($exceptId === null && strlen($password) < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($password !== '' && strlen($password) < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (AdminUser::emailTaken($email, $exceptId)) {
            return 'Ya existe un usuario con ese email.';
        }
        if ($username !== null) {
            $taken = \Core\Database::selectOne(
                'SELECT id FROM admin_users WHERE username = ?' . ($exceptId !== null ? ' AND id <> ?' : '') . ' LIMIT 1',
                $exceptId !== null ? [$username, $exceptId] : [$username]
            );
            if ($taken !== null) {
                return 'Ese nombre de usuario ya está tomado.';
            }
        }

        return [
            'name'      => $name,
            'email'     => $email,
            'username'  => $username,
            'password'  => $password,
            'role'      => $role,
            'user_type' => $userType,
            'active'    => $active,
        ];
    }
}
