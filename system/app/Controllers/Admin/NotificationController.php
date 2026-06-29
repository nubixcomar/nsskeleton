<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Notifier;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Bandeja de notificaciones in-app del usuario.
 */
final class NotificationController extends AdminController
{
    public function index(): Response
    {
        $uid = (int) Auth::id();

        return $this->view('admin/notifications/index', [
            'user'          => Auth::user(),
            'notifications' => Notifier::forUser($uid),
            'success'       => Session::getFlash('success'),
        ], 'layouts/admin');
    }

    public function read(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);
        Notifier::markRead((int) $id, (int) Auth::id());
        return $this->redirect(Url::to('/admin/notifications'));
    }

    public function readAll(Request $request): Response
    {
        $this->verifyCsrf($request);
        Notifier::markAllRead((int) Auth::id());
        Session::flash('success', 'Notificaciones marcadas como leídas.');
        return $this->redirect(Url::to('/admin/notifications'));
    }
}
