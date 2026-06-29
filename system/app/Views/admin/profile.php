<?php
/** @var array $user @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Mi perfil</h1>
        <p class="text-sm text-slate-500">Actualizá tus datos y tu contraseña.</p>
    </div>
    <a href="<?= View::e(Url::to('/admin/security/2fa')) ?>" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">🔐 2FA</a>
</div>


<div class="grid gap-6 lg:grid-cols-2">
    <form method="post" action="<?= View::e(Url::to('/admin/profile')) ?>"
          class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <h2 class="text-sm font-semibold text-slate-700">Datos</h2>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
            <input type="text" name="name" required value="<?= View::e($user['name'] ?? '') ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" required value="<?= View::e($user['email'] ?? '') ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Guardar datos</button>
    </form>

    <form method="post" action="<?= View::e(Url::to('/admin/profile/password')) ?>"
          class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <h2 class="text-sm font-semibold text-slate-700">Cambiar contraseña</h2>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Contraseña actual</label>
            <input type="password" name="current_password" required autocomplete="current-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Nueva contraseña</label>
            <input type="password" name="new_password" required minlength="8" autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Confirmar nueva contraseña</label>
            <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 font-medium text-white hover:bg-slate-900">Cambiar contraseña</button>
    </form>
</div>
