<?php
/** @var array $user @var array $admins @var array $pagination @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;
use Core\Auth;
?>
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900">Perfiles de administrador</h1>
    <a href="<?= View::e(Url::to('/admin/users/create')) ?>"
       class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Nuevo</a>
</div>

<div class="mb-4">
    <?= View::partial('partials/search', ['action' => Url::to('/admin/users'), 'search' => $pagination['search'] ?? '']) ?>
</div>


<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Nombre</th>
                <th class="px-4 py-3 font-medium">Usuario</th>
                <th class="px-4 py-3 font-medium">Email</th>
                <th class="px-4 py-3 font-medium">Tipo</th>
                <th class="px-4 py-3 font-medium">Rol</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-800"><?= View::e($a['name']) ?></td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= View::e($a['username'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= View::e($a['email']) ?></td>
                    <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"><?= View::e(\App\Services\UserTypes::label($a['user_type'] ?? 'admin')) ?></span></td>
                    <td class="px-4 py-3 text-slate-600"><?= View::e($a['role']) ?></td>
                    <td class="px-4 py-3">
                        <?php if ((int) $a['active'] === 1): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Activo</span>
                        <?php else: ?>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="<?= View::e(Url::to('/admin/users/' . $a['id'] . '/edit')) ?>"
                               class="rounded-md px-2 py-1 text-indigo-600 hover:bg-indigo-50">Editar</a>
                            <a href="<?= View::e(Url::to('/admin/users/' . $a['id'] . '/permissions')) ?>"
                               class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100">Permisos</a>
                            <?php if ((int) $a['id'] !== Auth::id()): ?>
                                <form method="post" action="<?= View::e(Url::to('/admin/users/' . $a['id'] . '/delete')) ?>"
                                      onsubmit="return confirm('¿Eliminar a <?= View::e($a['name']) ?>?');">
                                    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                    <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($admins)): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin resultados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= View::partial('partials/pagination', ['pagination' => $pagination ?? [], 'baseUrl' => Url::to('/admin/users')]) ?>
