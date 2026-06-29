<?php
/** @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="w-full max-w-sm">
    <h1 class="mb-1 text-center text-xl font-bold text-slate-900">Verificación en dos pasos</h1>
    <p class="mb-6 text-center text-sm text-slate-500">Ingresá el código de 6 dígitos de tu app de autenticación.</p>

    <?php if (!empty($error)): ?>
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200"><?= View::e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= View::e(Url::to('/admin/2fa')) ?>" class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" autofocus
               placeholder="000000"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-2xl tracking-widest focus:border-indigo-500 focus:ring-indigo-500">
        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Verificar</button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="<?= View::e(Url::to('/admin/login')) ?>" class="text-slate-500 hover:underline">← Volver al login</a>
    </p>
</div>
