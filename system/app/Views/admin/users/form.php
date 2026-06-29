<?php
/** @var array $user @var array|null $editing @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;

$isEdit = $editing !== null;
$action = $isEdit ? Url::to('/admin/users/' . $editing['id']) : Url::to('/admin/users');
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= $isEdit ? 'Editar administrador' : 'Nuevo administrador' ?></h1>
    <a href="<?= View::e(Url::to('/admin/users')) ?>" class="text-sm text-indigo-600 hover:underline">← Volver</a>
</div>


<form method="post" action="<?= View::e($action) ?>"
      class="max-w-lg space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
        <input type="text" name="name" required value="<?= View::e($editing['name'] ?? '') ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Usuario <span class="text-xs text-slate-400">(opcional, para login)</span></label>
            <input type="text" name="username" value="<?= View::e($editing['username'] ?? '') ?>" placeholder="ej: jperez"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" required value="<?= View::e($editing['email'] ?? '') ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">
            Contraseña <?= $isEdit ? '<span class="text-xs text-slate-400">(dejar vacío para no cambiar)</span>' : '' ?>
        </label>
        <input type="password" name="password" <?= $isEdit ? '' : 'required' ?> minlength="8"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Rol (permisos)</label>
            <?php $currentRole = $editing['role'] ?? 'admin'; ?>
            <select name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                <?php foreach (\App\Services\Rbac::roles() as $r): ?>
                    <option value="<?= View::e($r) ?>" <?= $r === $currentRole ? 'selected' : '' ?>><?= View::e($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Tipo de usuario</label>
            <?php $currentType = $editing['user_type'] ?? 'admin'; ?>
            <select name="user_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                <?php foreach (\App\Services\UserTypes::all() as $key => $label): ?>
                    <option value="<?= View::e($key) ?>" <?= $key === $currentType ? 'selected' : '' ?>><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="mt-1 text-xs text-slate-400">Mismo login de sistema; el tipo identifica qué clase de usuario es.</p>
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="active" value="1" <?= (!$isEdit || (int) ($editing['active'] ?? 1) === 1) ? 'checked' : '' ?>
               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
        Activo
    </label>

    <div class="pt-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            <?= $isEdit ? 'Guardar cambios' : 'Crear' ?>
        </button>
    </div>
</form>
