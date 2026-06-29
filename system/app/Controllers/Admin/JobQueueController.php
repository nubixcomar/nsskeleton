<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Audit;
use App\Services\JobQueue;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Panel de monitoreo de la cola de jobs.
 */
final class JobQueueController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('cron.manage');
    }

    public function index(): Response
    {
        return $this->view('admin/jobs/index', [
            'user'    => Auth::user(),
            'stats'   => JobQueue::stats(),
            'jobs'    => JobQueue::recent(40),
            'success' => Session::getFlash('success'),
        ], 'layouts/admin');
    }

    public function retry(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);
        JobQueue::retry((int) $id);
        Audit::log('job.retry', $id);
        Session::flash('success', 'Job reencolado.');
        return $this->redirect(Url::to('/admin/jobs'));
    }

    public function forget(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);
        JobQueue::forget((int) $id);
        Audit::log('job.forget', $id);
        Session::flash('success', 'Job eliminado.');
        return $this->redirect(Url::to('/admin/jobs'));
    }

    public function runNow(Request $request): Response
    {
        $this->verifyCsrf($request);
        $r = JobQueue::work(25);
        Session::flash('success', "Cola procesada: {$r['done']} ok, {$r['retried']} reintentar, {$r['failed']} fallidos.");
        return $this->redirect(Url::to('/admin/jobs'));
    }
}
