<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Audit;
use App\Services\Webhook;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Gestión de webhooks salientes.
 */
final class WebhookController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('api.manage');
    }

    public function index(): Response
    {
        return $this->view('admin/webhooks/index', [
            'user'     => Auth::user(),
            'webhooks' => Webhook::all(),
            'events'   => Webhook::events(),
            'success'  => Session::getFlash('success'),
            'error'    => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function create(Request $request): Response
    {
        $this->verifyCsrf($request);
        $event = (string) $request->input('event', '');
        $url = trim((string) $request->input('url', ''));

        if (!array_key_exists($event, Webhook::events()) || !filter_var($url, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Evento o URL inválidos.');
            return $this->redirect(Url::to('/admin/webhooks'));
        }

        Webhook::subscribe($event, $url);
        Audit::log('webhook.create', $event . ' ' . $url);
        Session::flash('success', 'Webhook creado.');
        return $this->redirect(Url::to('/admin/webhooks'));
    }

    public function toggle(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);
        Webhook::toggle((int) $id);
        return $this->redirect(Url::to('/admin/webhooks'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);
        Webhook::delete((int) $id);
        Audit::log('webhook.delete', $id);
        Session::flash('success', 'Webhook eliminado.');
        return $this->redirect(Url::to('/admin/webhooks'));
    }

    public function test(Request $request): Response
    {
        $this->verifyCsrf($request);
        $n = Webhook::dispatch('ping', ['message' => 'Hola desde nsSkeleton', 'at' => date('c')]);
        Session::flash('success', "Evento 'ping' encolado para {$n} webhook(s). Se entregan con la cola de jobs.");
        return $this->redirect(Url::to('/admin/webhooks'));
    }
}
