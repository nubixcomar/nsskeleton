<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminUser;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * "Mi perfil": el admin logueado edita sus datos y cambia su contraseña.
 */
final class ProfileController extends AdminController
{
    public function show(): Response
    {
        return $this->view('admin/profile', [
            'user'    => Auth::user(),
            'success' => Session::getFlash('success'),
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $this->verifyCsrf($request);
        $id = (int) Auth::id();

        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));

        if ($name === '' || $email === '') {
            Session::flash('error', 'Nombre y email son obligatorios.');
            return $this->redirect(Url::to('/admin/profile'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El email no es válido.');
            return $this->redirect(Url::to('/admin/profile'));
        }
        if (AdminUser::emailTaken($email, $id)) {
            Session::flash('error', 'Ese email ya está en uso.');
            return $this->redirect(Url::to('/admin/profile'));
        }

        AdminUser::update($id, ['name' => $name, 'email' => $email]);
        Session::flash('success', 'Perfil actualizado.');
        return $this->redirect(Url::to('/admin/profile'));
    }

    public function password(Request $request): Response
    {
        $this->verifyCsrf($request);
        $id = (int) Auth::id();
        $admin = AdminUser::find($id);

        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');

        if ($admin === null || !password_verify($current, (string) $admin['password'])) {
            Session::flash('error', 'La contraseña actual es incorrecta.');
            return $this->redirect(Url::to('/admin/profile'));
        }
        if (strlen($new) < 8) {
            Session::flash('error', 'La nueva contraseña debe tener al menos 8 caracteres.');
            return $this->redirect(Url::to('/admin/profile'));
        }
        if ($new !== $confirm) {
            Session::flash('error', 'La confirmación no coincide.');
            return $this->redirect(Url::to('/admin/profile'));
        }

        AdminUser::update($id, ['password' => Auth::hash($new)]);
        \App\Services\Audit::log('password.change');
        Session::flash('success', 'Contraseña actualizada.');
        return $this->redirect(Url::to('/admin/profile'));
    }
}
