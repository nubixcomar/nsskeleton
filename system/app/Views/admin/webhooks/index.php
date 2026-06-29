<?php
/** @var array $user @var array $webhooks @var array $events @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Webhooks salientes</h1>
        <p class="text-sm text-slate-500">Eventos del sistema → URLs externas (POST firmado HMAC-SHA256, header <code>X-NS-Signature</code>).</p>
    </div>
    <form method="post" action="<?= View::e(Url::to('/admin/webhooks/test')) ?>">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <button class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Enviar ping de prueba</button>
    </form>
</div>


<form method="post" action="<?= View::e(Url::to('/admin/webhooks')) ?>" class="mb-6 flex flex-wrap items-end gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Evento</label>
        <select name="event" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <?php foreach ($events as $key => $label): ?>
                <option value="<?= View::e($key) ?>"><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex-1">
        <label class="mb-1 block text-sm font-medium text-slate-700">URL destino</label>
        <input type="url" name="url" placeholder="https://ejemplo.com/webhook" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>
    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Suscribir</button>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">#</th>
                <th class="px-4 py-3 font-medium">Evento</th>
                <th class="px-4 py-3 font-medium">URL</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($webhooks as $w): ?>
                <tr>
                    <td class="px-4 py-3 text-slate-400"><?= (int) $w['id'] ?></td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= View::e($w['event']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= View::e($w['url']) ?></td>
                    <td class="px-4 py-3">
                        <?php if ((int) $w['active'] === 1): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">activo</span>
                        <?php else: ?>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <form method="post" action="<?= View::e(Url::to('/admin/webhooks/' . $w['id'] . '/toggle')) ?>">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100"><?= (int) $w['active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <form method="post" action="<?= View::e(Url::to('/admin/webhooks/' . $w['id'] . '/delete')) ?>" onsubmit="return confirm('¿Eliminar webhook?');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($webhooks)): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No hay webhooks suscriptos.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
