<?php
/** @var string|null $success @var string|null $error @var string|null $devLink */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-lg ring-1 ring-slate-200">
    <h1 class="text-xl font-bold text-slate-900">Recuperar contraseña</h1>
    <p class="mt-1 text-sm text-slate-500">Te enviamos un enlace para restablecerla.</p>

    <?php if (!empty($success)): ?>
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200"><?= View::e($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200"><?= View::e($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($devLink)): ?>
        <div class="mt-4 break-all rounded-lg bg-amber-50 px-4 py-3 text-xs text-amber-800 ring-1 ring-amber-200">
            <strong>DEV:</strong> <a class="underline" href="<?= View::e($devLink) ?>"><?= View::e($devLink) ?></a>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= View::e(Url::to('/admin/forgot')) ?>" class="mt-5 space-y-4">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" required autofocus
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Enviar enlace</button>
    </form>
    <p class="mt-4 text-center text-sm text-slate-500">
        <a href="<?= View::e(Url::to('/admin/login')) ?>" class="text-indigo-600 hover:underline">← Volver al ingreso</a>
    </p>
</div>
