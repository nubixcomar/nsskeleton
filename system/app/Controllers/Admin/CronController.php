<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\CronTask;
use App\Services\CronExpression;
use App\Services\CronRunner;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Cronmaster: gestión de tareas programadas.
 */
final class CronController extends AdminController
{
    public function index(): Response
    {
        return $this->view('admin/cron/index', [
            'user'    => Auth::user(),
            'tasks'   => CronTask::all('name ASC'),
            'success' => Session::getFlash('success'),
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return $this->view('admin/cron/form', [
            'user'    => Auth::user(),
            'editing' => null,
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        $this->verifyCsrf($request);

        $data = $this->validate($request);
        if (is_string($data)) {
            Session::flash('error', $data);
            return $this->redirect(Url::to('/admin/cron/create'));
        }

        $next = CronExpression::nextRunAfter($data['schedule'], new \DateTimeImmutable('now'));
        CronTask::create([
            'name'        => $data['name'],
            'command'     => $data['command'],
            'schedule'    => $data['schedule'],
            'active'      => $data['active'],
            'priority'    => $data['priority'],
            'timeout'     => $data['timeout'],
            'next_run_at' => $next?->format('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Tarea creada.');
        return $this->redirect(Url::to('/admin/cron'));
    }

    public function edit(Request $request, string $id): Response
    {
        $task = CronTask::find((int) $id);
        if ($task === null) {
            return $this->abort(404, 'Tarea no encontrada.');
        }

        return $this->view('admin/cron/form', [
            'user'    => Auth::user(),
            'editing' => $task,
            'breadcrumbExtra' => $task['name'] ?? null,
            'runs'    => CronTask::recentRuns((int) $id, 8),
            'error'   => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function update(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);

        if (CronTask::find((int) $id) === null) {
            return $this->abort(404, 'Tarea no encontrada.');
        }

        $data = $this->validate($request);
        if (is_string($data)) {
            Session::flash('error', $data);
            return $this->redirect(Url::to('/admin/cron/' . $id . '/edit'));
        }

        $next = CronExpression::nextRunAfter($data['schedule'], new \DateTimeImmutable('now'));
        CronTask::update((int) $id, [
            'name'        => $data['name'],
            'command'     => $data['command'],
            'schedule'    => $data['schedule'],
            'active'      => $data['active'],
            'priority'    => $data['priority'],
            'timeout'     => $data['timeout'],
            'next_run_at' => $next?->format('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Tarea actualizada.');
        return $this->redirect(Url::to('/admin/cron'));
    }

    public function toggle(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);

        $task = CronTask::find((int) $id);
        if ($task !== null) {
            CronTask::update((int) $id, ['active' => (int) $task['active'] === 1 ? 0 : 1]);
        }
        return $this->redirect(Url::to('/admin/cron'));
    }

    public function runNow(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);

        $task = CronTask::find((int) $id);
        if ($task === null) {
            return $this->abort(404, 'Tarea no encontrada.');
        }

        $result = CronRunner::runTask($task);
        Session::flash(
            $result['status'] === 'success' ? 'success' : 'error',
            "Ejecución manual de «{$task['name']}»: {$result['status']} (exit {$result['code']})."
        );
        return $this->redirect(Url::to('/admin/cron/' . $id . '/edit'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->verifyCsrf($request);
        CronTask::delete((int) $id);
        Session::flash('success', 'Tarea eliminada.');
        return $this->redirect(Url::to('/admin/cron'));
    }

    /**
     * @return array<string,mixed>|string
     */
    private function validate(Request $request): array|string
    {
        $name = trim((string) $request->input('name', ''));
        $command = trim((string) $request->input('command', ''));
        $schedule = trim((string) $request->input('schedule', ''));
        $active = $request->input('active') ? 1 : 0;
        $priority = (int) $request->input('priority', 0);
        $timeout = max(0, (int) $request->input('timeout', 0));

        if ($name === '' || $command === '' || $schedule === '') {
            return 'Nombre, comando y expresión cron son obligatorios.';
        }
        if (!CronExpression::isValid($schedule)) {
            return 'La expresión cron no es válida (deben ser 5 campos: m h dom mon dow).';
        }

        return compact('name', 'command', 'schedule', 'active', 'priority', 'timeout');
    }
}
