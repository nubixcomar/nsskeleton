<?php
/** @var array $user @var array $hist @var array $filters @var array $tasks
 *  @var string|null $success @var string|null $error */
use Core\Session;
use Core\Url;
use Core\View;

$statusLabels = ['' => 'Todos', 'success' => 'OK', 'failed' => 'Error'];
?>
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Historial de ejecuciones</h1>
        <p class="text-sm text-slate-500"><?= (int) $hist['total'] ?> ejecución(es) registradas.</p>
    </div>
    <div class="flex gap-2">
        <form method="post" action="<?= View::e(Url::to('/admin/cron/history/clear-ok')) ?>"
              onsubmit="return confirm('¿Borrar solo las ejecuciones exitosas? Queda el log de errores.')">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Limpiar OK</button>
        </form>
        <form method="post" action="<?= View::e(Url::to('/admin/cron/history/clear-all')) ?>"
              onsubmit="return confirm('¿Vaciar TODO el historial? No se puede deshacer.')">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
            <button class="rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Vaciar registro</button>
        </form>
    </div>
</div>

<?= View::partial('admin/cron/_subnav', ['active' => 'history']) ?>

<form method="get" action="<?= View::e(Url::to('/admin/cron/history')) ?>"
      class="mb-4 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4">
    <div>
        <label class="mb-1 block text-xs text-slate-500">Tarea</label>
        <select name="task_id" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
            <option value="">Todas</option>
            <?php foreach ($tasks as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= (int) ($filters['task_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                    <?= View::e((string) $t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs text-slate-500">Estado</label>
        <select name="status" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
            <?php foreach ($statusLabels as $val => $lbl): ?>
                <option value="<?= View::e($val) ?>" <?= ($filters['status'] ?? '') === $val ? 'selected' : '' ?>><?= View::e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sm:col-span-1">
        <label class="mb-1 block text-xs text-slate-500">Buscar en mensaje</label>
        <input name="q" value="<?= View::e((string) ($filters['q'] ?? '')) ?>" placeholder="texto…"
               class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
    </div>
    <div class="flex items-end">
        <button class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filtrar</button>
    </div>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Fecha/hora</th>
                <th class="px-4 py-3 font-medium">Tarea</th>
                <th class="px-4 py-3 font-medium">Disparo</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium">Duración</th>
                <th class="px-4 py-3 font-medium">Mensaje</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($hist['rows'] as $r): ?>
                <?php $ok = ($r['status'] ?? '') === 'success'; ?>
                <tr class="<?= $ok ? '' : 'bg-red-50' ?>">
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600"><?= View::e((string) ($r['started_at'] ?? '')) ?></td>
                    <td class="px-4 py-3 font-medium text-slate-800"><?= View::e((string) ($r['task_name'] ?? '—')) ?></td>
                    <td class="px-4 py-3">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"><?= View::e(strtoupper((string) ($r['trigger_type'] ?? 'cron'))) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $ok
                            ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>">
                            <?= $ok ? 'OK' : 'ERROR' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-500"><?= $r['duration_ms'] !== null ? (int) $r['duration_ms'] . ' ms' : '—' ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= View::e(mb_strimwidth((string) ($r['output'] ?? ''), 0, 120, '…')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($hist['rows'] === []): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin ejecuciones registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (($hist['pages'] ?? 1) > 1): ?>
    <div class="mt-4 flex items-center justify-center gap-2 text-sm">
        <?php for ($p = 1; $p <= $hist['pages']; $p++): ?>
            <a href="<?= View::e(Url::to('/admin/cron/history?page=' . $p
                . '&task_id=' . (int) ($filters['task_id'] ?? 0)
                . '&status=' . urlencode((string) ($filters['status'] ?? ''))
                . '&q=' . urlencode((string) ($filters['q'] ?? '')))) ?>"
               class="rounded px-3 py-1 <?= $p === (int) $hist['page'] ? 'bg-slate-900 text-white' : 'bg-white ring-1 ring-slate-200 text-slate-600' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
