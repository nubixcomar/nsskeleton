<?php
/** @var array $user @var array $tokens @var ?string $newToken @var ?string $success */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Tokens de API</h1>
        <p class="text-sm text-slate-500">Autenticación Bearer para <code>/api/&lt;recurso&gt;</code>.</p>
    </div>
    <a href="<?= View::e(Url::to('/api/docs')) ?>" target="_blank" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">📖 Documentación API</a>
</div>

<?php if (!empty($newToken)): ?>
    <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 ring-1 ring-amber-200">
        <p class="mb-1 text-xs font-semibold uppercase text-amber-700">Token (copialo ahora)</p>
        <code class="break-all text-sm text-amber-900"><?= View::e($newToken) ?></code>
    </div>
<?php endif; ?>

<form method="post" action="<?= View::e(Url::to('/admin/api-tokens')) ?>" class="mb-6 flex items-end gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Nombre del token</label>
        <input type="text" name="name" placeholder="Integración X" required
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Permisos</label>
        <select name="scopes" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="read,write">Lectura + escritura</option>
            <option value="read">Solo lectura</option>
        </select>
    </div>
    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Crear token</button>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Nombre</th>
                <th class="px-4 py-3 font-medium">Admin</th>
                <th class="px-4 py-3 font-medium">Último uso</th>
                <th class="px-4 py-3 font-medium">Creado</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($tokens as $t): ?>
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-800"><?= View::e($t['name']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= View::e($t['admin_name'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= View::e($t['last_used_at'] ?? 'nunca') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= View::e($t['created_at']) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end">
                            <form method="post" action="<?= View::e(Url::to('/admin/api-tokens/' . $t['id'] . '/revoke')) ?>" onsubmit="return confirm('¿Revocar este token?');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Revocar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tokens)): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin tokens.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
