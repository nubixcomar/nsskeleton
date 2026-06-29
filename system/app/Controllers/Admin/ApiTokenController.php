<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\ApiToken;
use App\Services\Audit;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Gestión de tokens de API (crear / revocar).
 */
final class ApiTokenController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('api.manage');
    }

    public function index(): Response
    {
        return $this->view('admin/api/index', [
            'user'     => Auth::user(),
            'tokens'   => ApiToken::all(),
            'newToken' => Session::getFlash('new_token'),
            'success'  => Session::getFlash('success'),
        ], 'layouts/admin');
    }

    public function create(Request $request): Response
    {
        $this->verifyCsrf($request);
        $name = trim((string) $request->input('name', '')) ?: 'token';
        $scopes = (string) $request->input('scopes', 'read,write');
        $raw = ApiToken::generate((int) Auth::id(), $name, $scopes);

        Audit::log('api_token.create', $name);
        Session::flash('new_token', $raw);
        Session::flash('success', 'Token creado. Copialo ahora: no se vuelve a mostrar.');
        return $this->redirect(Url::to('/admin/api-tokens'));
    }

    public function revoke(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);
        ApiToken::revoke((int) $id);
        Audit::log('api_token.revoke', (string) $id);
        Session::flash('success', 'Token revocado.');
        return $this->redirect(Url::to('/admin/api-tokens'));
    }
}
