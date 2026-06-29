<?php
/** @var string $email @var string $token @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-lg ring-1 ring-slate-200">
    <h1 class="text-xl font-bold text-slate-900">Nueva contraseña</h1>
    <p class="mt-1 text-sm text-slate-500">Elegí una contraseña nueva para tu cuenta.</p>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200"><?= View::e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= View::e(Url::to('/admin/reset')) ?>" class="mt-5 space-y-4">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <input type="hidden" name="email" value="<?= View::e($email) ?>">
        <input type="hidden" name="token" value="<?= View::e($token) ?>">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Nueva contraseña</label>
            <input type="password" name="new_password" required minlength="8" autofocus autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Confirmar contraseña</label>
            <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Guardar contraseña</button>
    </form>
</div>
