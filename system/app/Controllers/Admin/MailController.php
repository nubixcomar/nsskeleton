<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\EmailLog;
use App\Services\EmailQueue;
use App\Services\Mailer;
use App\Services\Settings;
use Core\Auth;
use Core\Env;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Configuración SMTP y envío de prueba de emails.
 */
final class MailController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('mail.manage');
    }

    public function queue(): Response
    {
        return $this->view('admin/mail/queue', [
            'user'    => Auth::user(),
            'rows'    => EmailQueue::recent(100),
            'counts'  => EmailQueue::counts(),
            'success' => Session::getFlash('success'),
        ], 'layouts/admin');
    }

    public function processQueue(Request $request): Response
    {
        $this->verifyCsrf($request);
        $r = EmailQueue::process(50);
        Session::flash('success', "Cola procesada: {$r['sent']} enviado(s), {$r['failed']} con error.");
        return $this->redirect(Url::to('/admin/mail/queue'));
    }

    public function settings(): Response
    {
        $mail = Settings::group('mail');

        return $this->view('admin/mail/settings', [
            'user'    => Auth::user(),
            'mail'    => [
                'host'         => $mail['host']         ?? Env::get('MAIL_HOST', ''),
                'port'         => $mail['port']         ?? Env::get('MAIL_PORT', '587'),
                'user'         => $mail['user']         ?? Env::get('MAIL_USER', ''),
                'encryption'   => $mail['encryption']   ?? Env::get('MAIL_ENCRYPTION', 'tls'),
                'from_address' => $mail['from_address'] ?? Env::get('MAIL_FROM_ADDRESS', ''),
                'from_name'    => $mail['from_name']    ?? Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'nsSkeleton')),
                'has_pass'     => !empty($mail['pass']),
            ],
            'success' => Session::getFlash('success'),
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function saveSettings(Request $request): Response
    {
        $this->verifyCsrf($request);

        Settings::set('mail.host', trim((string) $request->input('host', '')), 'mail');
        Settings::set('mail.port', (string) (int) $request->input('port', 587), 'mail');
        Settings::set('mail.user', trim((string) $request->input('user', '')), 'mail');
        Settings::set('mail.encryption', (string) $request->input('encryption', 'tls'), 'mail');
        Settings::set('mail.from_address', trim((string) $request->input('from_address', '')), 'mail');
        Settings::set('mail.from_name', trim((string) $request->input('from_name', '')), 'mail');

        // Solo actualiza la contraseña si se ingresó una nueva (cifrada en reposo).
        $pass = (string) $request->input('pass', '');
        if ($pass !== '') {
            Settings::setSecret('mail.pass', $pass, 'mail');
        }

        Session::flash('success', 'Configuración de email guardada.');
        return $this->redirect(Url::to('/admin/mail'));
    }

    public function test(Request $request): Response
    {
        $this->verifyCsrf($request);

        $to = trim((string) $request->input('test_email', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Ingresá un email de destino válido para la prueba.');
            return $this->redirect(Url::to('/admin/mail'));
        }

        $result = Mailer::send(
            $to,
            'Prueba de email · ' . Env::get('APP_NAME', 'nsSkeleton'),
            '<h2>Funciona ✔</h2><p>Este es un email de prueba enviado desde tu sistema.</p>'
        );

        if ($result['ok']) {
            Session::flash('success', "Email de prueba enviado a {$to}.");
        } else {
            Session::flash('error', 'No se pudo enviar: ' . $result['error']);
        }
        return $this->redirect(Url::to('/admin/mail'));
    }

    public function log(): Response
    {
        return $this->view('admin/mail/log', [
            'user'    => Auth::user(),
            'entries' => EmailLog::recent(50),
        ], 'layouts/admin');
    }
}
