<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\GlobalSearch;
use Core\Auth;
use Core\Request;
use Core\Response;

/**
 * Búsqueda global a través de los módulos registrados.
 */
final class SearchController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        return $this->view('admin/search', [
            'user'   => Auth::user(),
            'q'      => $q,
            'groups' => $q === '' ? [] : GlobalSearch::search($q),
        ], 'layouts/admin');
    }
}
