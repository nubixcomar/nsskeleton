<?php
/** @var string|null $error @var string|null $success */
use Core\View;
use Core\Session;
use Core\Url;
use Core\Env;
?>
<div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-lg ring-1 ring-slate-200">
    <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold text-white">ns</div>
        <h1 class="text-xl font-bold text-slate-900"><?= View::e(\App\Services\AppSettings::name()) ?></h1>
        <p class="text-sm text-slate-500">Panel de administración</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
            <?= View::e($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
            <?= View::e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= View::e(Url::to('/admin/login')) ?>" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Usuario o email</label>
            <input type="text" name="login" required autofocus autocomplete="username" placeholder="usuario o nombre@dominio.com"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Contraseña</label>
            <input type="password" name="password" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white transition hover:bg-indigo-700">
            Ingresar
        </button>
    </form>
    <p class="mt-4 text-center text-sm">
        <a href="<?= View::e(Url::to('/admin/forgot')) ?>" class="text-indigo-600 hover:underline">¿Olvidaste tu contraseña?</a>
    </p>
</div>
