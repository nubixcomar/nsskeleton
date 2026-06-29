<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\FeatureFlags;
use App\Services\Health;
use Core\Auth;
use Core\Response;

/**
 * Panel de estado del sistema (métricas) + feature flags (solo lectura).
 */
final class HealthController extends AdminController
{
    public function index(): Response
    {
        return $this->view('admin/health/index', [
            'user'   => Auth::user(),
            'health' => Health::full(),
            'flags'  => FeatureFlags::all(),
        ], 'layouts/admin');
    }
}
