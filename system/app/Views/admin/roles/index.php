<?php
/** @var array $user @var array $roles @var array $catalog @var array $matrix @var string|null $success */
use Core\View;
use Core\Url;
use Core\Session;

$has = static fn (string $role, string $perm): bool =>
    in_array('*', $matrix[$role] ?? [], true) || in_array($perm, $matrix[$role] ?? [], true);
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Roles y permisos</h1>
    <p class="text-sm text-slate-500">Editá qué puede hacer cada rol. <code>superadmin</code> siempre tiene todo.</p>
</div>


<form method="post" action="<?= View::e(Url::to('/admin/roles')) ?>">
    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Permiso</th>
                    <?php foreach ($roles as $role): ?>
                        <th class="px-4 py-3 text-center font-medium"><?= View::e($role) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($catalog as $perm => $label): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-700"><?= View::e($label) ?></span>
                            <span class="block font-mono text-xs text-slate-400"><?= View::e($perm) ?></span>
                        </td>
                        <?php foreach ($roles as $role): ?>
                            <td class="px-4 py-3 text-center">
                                <?php if ($role === 'superadmin'): ?>
                                    <input type="checkbox" checked disabled class="rounded border-slate-300 text-slate-400">
                                <?php else: ?>
                                    <input type="checkbox" name="perms[<?= View::e($role) ?>][]" value="<?= View::e($perm) ?>"
                                           <?= $has($role, $perm) ? 'checked' : '' ?>
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar permisos</button>
    </div>
</form>
