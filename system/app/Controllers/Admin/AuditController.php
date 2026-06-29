<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Paginator;
use Core\Auth;
use Core\Request;
use Core\Response;

/**
 * Visor de auditoría de acciones de administradores.
 */
final class AuditController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('audit.view');
    }

    public function index(Request $request): Response
    {
        $pagination = Paginator::paginate('audit_log', [
            'page'       => (int) $request->query('page', 1),
            'perPage'    => 30,
            'search'     => (string) $request->query('search', ''),
            'searchable' => ['admin_name', 'action', 'target', 'details', 'ip'],
            'order'      => 'id DESC',
        ]);

        return $this->view('admin/audit/index', [
            'user'       => Auth::user(),
            'rows'       => $pagination['rows'],
            'pagination' => $pagination,
        ], 'layouts/admin');
    }
}
