<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Audit;
use App\Services\LoginThrottle;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Login / logout del backend. NO extiende AdminController (no requiere sesión).
 */
final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        if (Auth::check()) {
            return $this->redirect(Url::to('/admin'));
        }

        return $this->view('admin/login', [
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        if (!Session::verifyCsrf((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Token inválido. Reintentá.');
            return $this->redirect(Url::to('/admin/login'));
        }

        // Acepta usuario o email (login unificado del sistema).
        $login = trim((string) $request->input('login', (string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        if (LoginThrottle::tooManyAttempts($login)) {
            $min = (int) ceil(LoginThrottle::secondsRemaining($login) / 60);
            Session::flash('error', "Demasiados intentos fallidos. Probá de nuevo en {$min} min.");
            return $this->redirect(Url::to('/admin/login'));
        }

        $user = Auth::verifyCredentials($login, $password);
        if ($user === null) {
            LoginThrottle::hit($login);
            Audit::log('login_failed', '', $login);
            Session::flash('error', 'Credenciales inválidas.');
            return $this->redirect(Url::to('/admin/login'));
        }

        LoginThrottle::clear($login);

        // Si tiene 2FA activado, no completamos el login: pedimos el código.
        if (!empty($user['totp_enabled'])) {
            Session::set('2fa_pending_user', (int) $user['id']);
            return $this->redirect(Url::to('/admin/2fa'));
        }

        Auth::login((int) $user['id']);
        Audit::log('login');
        return $this->redirect(Url::to('/admin'));
    }

    public function show2fa(): Response
    {
        if (Session::get('2fa_pending_user') === null) {
            return $this->redirect(Url::to('/admin/login'));
        }
        return $this->view('admin/2fa-challenge', [
            'error' => Session::getFlash('error'),
        ], 'layouts/auth');
    }

    public function verify2fa(Request $request): Response
    {
        if (!Session::verifyCsrf((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Token inválido. Reintentá.');
            return $this->redirect(Url::to('/admin/2fa'));
        }

        $pendingId = Session::get('2fa_pending_user');
        if ($pendingId === null) {
            return $this->redirect(Url::to('/admin/login'));
        }
        $pendingId = (int) $pendingId;

        $row = \Core\Database::selectOne('SELECT totp_secret FROM admin_users WHERE id = ? LIMIT 1', [$pendingId]);
        $secret = \Core\Crypto::maybeDecrypt($row['totp_secret'] ?? null) ?? '';
        $code = (string) $request->input('code', '');

        if ($secret === '' || !\App\Services\Totp::verify($secret, $code)) {
            Audit::log('2fa_failed', '', (string) $pendingId);
            Session::flash('error', 'Código incorrecto.');
            return $this->redirect(Url::to('/admin/2fa'));
        }

        Session::remove('2fa_pending_user');
        Auth::login($pendingId);
        Audit::log('login');
        return $this->redirect(Url::to('/admin'));
    }

    public function logout(Request $request): Response
    {
        if (Session::verifyCsrf((string) $request->input('_csrf', ''))) {
            Audit::log('logout');
            Auth::logout();
        }
        return $this->redirect(Url::to('/admin/login'));
    }
}
