<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AppSettings;
use App\Services\Audit;
use App\Services\Totp;
use Core\Auth;
use Core\Crypto;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Configuración de 2FA (TOTP) del propio administrador.
 */
final class TwoFactorController extends AdminController
{
    public function setup(): Response
    {
        $user = Auth::user() ?? [];
        $enabled = !empty($user['totp_enabled']);

        $secret = '';
        if (!$enabled) {
            // reusa el secreto pendiente; solo genera uno nuevo si no hay (estable entre recargas)
            $row = Database::selectOne('SELECT totp_secret FROM admin_users WHERE id = ? LIMIT 1', [(int) $user['id']]);
            $secret = Crypto::maybeDecrypt($row['totp_secret'] ?? null) ?? '';
            if ($secret === '') {
                $secret = Totp::generateSecret();
                Database::run('UPDATE admin_users SET totp_secret = ? WHERE id = ?', [Crypto::encrypt($secret), (int) $user['id']]);
            }
        }

        return $this->view('admin/security/2fa', [
            'user'    => $user,
            'enabled' => $enabled,
            'secret'  => $secret,
            'uri'     => $secret !== '' ? Totp::uri($secret, (string) ($user['email'] ?? 'admin'), AppSettings::name()) : '',
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ], 'layouts/admin');
    }

    public function enable(Request $request): Response
    {
        $this->verifyCsrf($request);
        $user = Auth::user() ?? [];

        $row = Database::selectOne('SELECT totp_secret FROM admin_users WHERE id = ? LIMIT 1', [(int) $user['id']]);
        $secret = Crypto::maybeDecrypt($row['totp_secret'] ?? null) ?? '';

        if ($secret === '' || !Totp::verify($secret, (string) $request->input('code', ''))) {
            Session::flash('error', 'Código incorrecto. Probá de nuevo (revisá la hora del dispositivo).');
            return $this->redirect(Url::to('/admin/security/2fa'));
        }

        Database::run('UPDATE admin_users SET totp_enabled = 1 WHERE id = ?', [(int) $user['id']]);
        Audit::log('2fa_enabled');
        Session::flash('success', '2FA activado. Te pediremos el código en el próximo login.');
        return $this->redirect(Url::to('/admin/security/2fa'));
    }

    public function disable(Request $request): Response
    {
        $this->verifyCsrf($request);
        $user = Auth::user() ?? [];

        Database::run('UPDATE admin_users SET totp_enabled = 0, totp_secret = NULL WHERE id = ?', [(int) $user['id']]);
        Audit::log('2fa_disabled');
        Session::flash('success', '2FA desactivado.');
        return $this->redirect(Url::to('/admin/security/2fa'));
    }
}
