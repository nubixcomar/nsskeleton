<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Mailer;
use App\Services\PasswordReset;
use Core\Controller;
use Core\Env;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Recuperación de contraseña (público, sin sesión).
 */
final class ForgotPasswordController extends Controller
{
    public function showForgot(): Response
    {
        return $this->view('admin/forgot', [
            'success' => Session::getFlash('success'),
            'error'   => Session::getFlash('error'),
            'devLink' => Session::getFlash('dev_link'),
        ], 'layouts/auth');
    }

    public function sendReset(Request $request): Response
    {
        if (!Session::verifyCsrf((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Token inválido. Reintentá.');
            return $this->redirect(Url::to('/admin/forgot'));
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $token = PasswordReset::createToken($email);

        if ($token !== null) {
            $abs = rtrim((string) Env::get('APP_URL', ''), '/')
                . '/admin/reset?email=' . rawurlencode($email) . '&token=' . $token;

            Mailer::send(
                $email,
                'Restablecer contraseña · ' . Env::get('APP_NAME', 'nsSkeleton'),
                '<p>Recibimos un pedido para restablecer tu contraseña.</p>'
                . '<p>Usá este enlace (válido 1 hora):</p><p><a href="' . $abs . '">' . $abs . '</a></p>'
                . '<p>Si no fuiste vos, ignorá este mensaje.</p>'
            );

            // Ayuda de desarrollo: si APP_DEBUG, mostramos el enlace en pantalla.
            if (Env::get('APP_DEBUG', false)) {
                Session::flash('dev_link', $abs);
            }
        }

        // Mensaje neutral (no revela si el email existe).
        Session::flash('success', 'Si el email existe, te enviamos instrucciones para restablecer la contraseña.');
        return $this->redirect(Url::to('/admin/forgot'));
    }

    public function showReset(Request $request): Response
    {
        return $this->view('admin/reset', [
            'email' => (string) $request->query('email', ''),
            'token' => (string) $request->query('token', ''),
            'error' => Session::getFlash('error'),
        ], 'layouts/auth');
    }

    public function doReset(Request $request): Response
    {
        if (!Session::verifyCsrf((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Token inválido. Reintentá.');
            return $this->redirect(Url::to('/admin/forgot'));
        }

        $email = (string) $request->input('email', '');
        $token = (string) $request->input('token', '');
        $new = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');

        $backToReset = Url::to('/admin/reset') . '?email=' . rawurlencode($email) . '&token=' . rawurlencode($token);

        if (strlen($new) < 8) {
            Session::flash('error', 'La contraseña debe tener al menos 8 caracteres.');
            return $this->redirect($backToReset);
        }
        if ($new !== $confirm) {
            Session::flash('error', 'La confirmación no coincide.');
            return $this->redirect($backToReset);
        }

        if (!PasswordReset::consume($email, $token, $new)) {
            Session::flash('error', 'El enlace es inválido o expiró. Pedí uno nuevo.');
            return $this->redirect(Url::to('/admin/forgot'));
        }

        Session::flash('error', ''); // limpia
        Session::flash('success', 'Contraseña actualizada. Ya podés ingresar.');
        return $this->redirect(Url::to('/admin/login'));
    }
}
