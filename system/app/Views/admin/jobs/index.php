<?php
/** @var array $user @var array $stats @var array $jobs @var string|null $success */
use Core\View;
use Core\Url;
use Core\Session;

$badge = [
    'pending'    => 'bg-amber-100 text-amber-700',
    'processing' => 'bg-blue-100 text-blue-700',
    'done'       => 'bg-emerald-100 text-emerald-700',
    'failed'     => 'bg-red-100 text-red-700',
];
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Cola de jobs</h1>
        <p class="text-sm text-slate-500">Procesos en segundo plano con reintentos.</p>
    </div>
    <form method="post" action="<?= View::e(Url::to('/admin/jobs/run')) ?>">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Procesar ahora</button>
    </form>
</div>


<div class="mb-6 grid gap-4 sm:grid-cols-4">
    <?php foreach (['pending' => 'Pendientes', 'processing' => 'En curso', 'done' => 'Hechos', 'failed' => 'Fallidos'] as $k => $label): ?>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm text-slate-500"><?= View::e($label) ?></p>
            <p class="mt-1 text-2xl font-bold text-slate-900"><?= (int) ($stats[$k] ?? 0) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">#</th>
                <th class="px-4 py-3 font-medium">Handler</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium">Intentos</th>
                <th class="px-4 py-3 font-medium">Error</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($jobs as $j): ?>
                <tr>
                    <td class="px-4 py-3 text-slate-400"><?= (int) $j['id'] ?></td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= View::e($j['handler']) ?></td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $badge[$j['status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= View::e($j['status']) ?></span></td>
                    <td class="px-4 py-3 text-slate-600"><?= (int) $j['attempts'] ?>/<?= (int) $j['max_attempts'] ?></td>
                    <td class="px-4 py-3 text-xs text-red-600"><?= View::e((string) ($j['error'] ?? '')) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <?php if ($j['status'] === 'failed'): ?>
                                <form method="post" action="<?= View::e(Url::to('/admin/jobs/' . $j['id'] . '/retry')) ?>">
                                    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                    <button class="rounded-md px-2 py-1 text-indigo-600 hover:bg-indigo-50">Reintentar</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= View::e(Url::to('/admin/jobs/' . $j['id'] . '/forget')) ?>" onsubmit="return confirm('¿Eliminar job?');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($jobs)): ?>
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">No hay jobs.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
