<?php
/** @var array $user @var array $target @var array $catalog @var array $rolePerms @var array $overrides @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;

$roleHas = static fn (string $perm): bool =>
    in_array('*', $rolePerms, true) || in_array($perm, $rolePerms, true);
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Permisos de <?= View::e($target['name'] ?? $target['email']) ?></h1>
    <p class="text-sm text-slate-500">Rol: <strong><?= View::e($target['role']) ?></strong>. Los overrides pesan sobre lo que da el rol.</p>
    <a href="<?= View::e(Url::to('/admin/users')) ?>" class="text-sm text-indigo-600 hover:underline">← Volver</a>
</div>


<form method="post" action="<?= View::e(Url::to('/admin/users/' . $target['id'] . '/permissions')) ?>">
    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Permiso</th>
                    <th class="px-4 py-3 font-medium">Por rol</th>
                    <th class="px-4 py-3 font-medium">Override</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($catalog as $perm => $label): ?>
                    <?php $cur = array_key_exists($perm, $overrides) ? ($overrides[$perm] ? 'allow' : 'deny') : 'inherit'; ?>
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-700"><?= View::e($label) ?></span>
                            <span class="block font-mono text-xs text-slate-400"><?= View::e($perm) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($roleHas($perm)): ?>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Sí</span>
                            <?php else: ?>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <select name="eff[<?= View::e($perm) ?>]" class="rounded-lg border border-slate-300 px-2 py-1 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="inherit" <?= $cur === 'inherit' ? 'selected' : '' ?>>Heredar del rol</option>
                                <option value="allow" <?= $cur === 'allow' ? 'selected' : '' ?>>Permitir</option>
                                <option value="deny" <?= $cur === 'deny' ? 'selected' : '' ?>>Denegar</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar overrides</button>
    </div>
</form>
