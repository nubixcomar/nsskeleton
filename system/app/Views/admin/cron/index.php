<?php
/** @var array $user @var array $tasks @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Tareas programadas</h1>
        <p class="text-sm text-slate-500">Cronmaster · el despachador corre por minuto desde el SO.</p>
    </div>
    <a href="<?= View::e(Url::to('/admin/cron/create')) ?>"
       class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Nueva tarea</a>
</div>


<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Tarea</th>
                <th class="px-4 py-3 font-medium">Cron</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium">Últ. ejecución</th>
                <th class="px-4 py-3 font-medium">Próxima</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($tasks as $t): ?>
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-800">
                        <?= View::e($t['name']) ?>
                        <?php if ((int) ($t['priority'] ?? 0) !== 0): ?>
                            <span class="ml-1 rounded bg-slate-100 px-1.5 text-[10px] text-slate-500">prio <?= (int) $t['priority'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600">
                        <span class="text-slate-700"><?= View::e(\App\Services\ScheduleBuilder::describe((string) $t['schedule'])) ?></span>
                        <span class="block font-mono text-[10px] text-slate-400"><?= View::e($t['schedule']) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ((int) $t['active'] === 1): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Activa</span>
                        <?php else: ?>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Pausada</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        <?= View::e($t['last_run_at'] ?? '—') ?>
                        <?php if (!empty($t['last_status'])): ?>
                            <span class="ml-1 text-xs <?= $t['last_status'] === 'success' ? 'text-emerald-600' : 'text-red-600' ?>">
                                (<?= View::e($t['last_status']) ?>)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= View::e($t['next_run_at'] ?? '—') ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <form method="post" action="<?= View::e(Url::to('/admin/cron/' . $t['id'] . '/run')) ?>">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-emerald-700 hover:bg-emerald-50">Ejecutar</button>
                            </form>
                            <form method="post" action="<?= View::e(Url::to('/admin/cron/' . $t['id'] . '/toggle')) ?>">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100"><?= (int) $t['active'] === 1 ? 'Pausar' : 'Activar' ?></button>
                            </form>
                            <a href="<?= View::e(Url::to('/admin/cron/' . $t['id'] . '/edit')) ?>" class="rounded-md px-2 py-1 text-indigo-600 hover:bg-indigo-50">Editar</a>
                            <form method="post" action="<?= View::e(Url::to('/admin/cron/' . $t['id'] . '/delete')) ?>"
                                  onsubmit="return confirm('¿Eliminar la tarea?');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Sin tareas programadas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
