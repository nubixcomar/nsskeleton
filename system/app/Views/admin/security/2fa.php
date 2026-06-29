<?php
/** @var array $user @var bool $enabled @var string $secret @var string $uri @var string|null $error @var string|null $success */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Verificación en dos pasos (2FA)</h1>
    <p class="text-sm text-slate-500">Protegé tu cuenta con un código temporal (TOTP).</p>
</div>


<div class="max-w-lg rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?php if ($enabled): ?>
        <p class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-emerald-700">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> 2FA activado en tu cuenta.
        </p>
        <form method="post" action="<?= View::e(Url::to('/admin/security/2fa/disable')) ?>" onsubmit="return confirm('¿Desactivar 2FA?');">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
            <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Desactivar 2FA</button>
        </form>
    <?php else: ?>
        <ol class="mb-4 list-decimal space-y-2 pl-5 text-sm text-slate-600">
            <li>Abrí tu app de autenticación (Google Authenticator, Authy…).</li>
            <li>Agregá una cuenta con esta <strong>clave manual</strong>:</li>
        </ol>
        <div class="mb-4 rounded-lg bg-slate-50 px-4 py-3 font-mono text-sm tracking-wider text-slate-800 ring-1 ring-slate-200">
            <?= View::e(trim(chunk_split($secret, 4, ' '))) ?>
        </div>
        <p class="mb-4 break-all text-xs text-slate-400">URI: <?= View::e($uri) ?></p>

        <form method="post" action="<?= View::e(Url::to('/admin/security/2fa/enable')) ?>" class="space-y-3">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
            <label class="block text-sm font-medium text-slate-700">Confirmá con el código actual</label>
            <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="000000"
                   class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-widest focus:border-indigo-500 focus:ring-indigo-500">
            <div>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Activar 2FA</button>
            </div>
        </form>
    <?php endif; ?>
</div>
