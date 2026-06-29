<?php
/** @var array $user @var array $mail @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900">Emails</h1>
    <div class="flex items-center gap-3">
        <a href="<?= View::e(Url::to('/admin/mail/queue')) ?>" class="text-sm text-indigo-600 hover:underline">Ver cola →</a>
        <a href="<?= View::e(Url::to('/admin/mail/log')) ?>" class="text-sm text-indigo-600 hover:underline">Ver historial →</a>
    </div>
</div>


<div class="grid gap-6 lg:grid-cols-3">
    <form method="post" action="<?= View::e(Url::to('/admin/mail')) ?>"
          class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <h2 class="text-sm font-semibold text-slate-700">Servidor SMTP</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Host</label>
                <input type="text" name="host" value="<?= View::e($mail['host']) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Puerto</label>
                <input type="number" name="port" value="<?= View::e($mail['port']) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Usuario</label>
                <input type="text" name="user" value="<?= View::e($mail['user']) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Contraseña <?= $mail['has_pass'] ? '<span class="text-xs text-slate-400">(guardada — dejar vacío para no cambiar)</span>' : '' ?>
                </label>
                <input type="password" name="pass" autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Encriptación</label>
                <select name="encryption" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Ninguna'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $mail['encryption'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h2 class="pt-2 text-sm font-semibold text-slate-700">Remitente</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email (from)</label>
                <input type="email" name="from_address" value="<?= View::e($mail['from_address']) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre (from)</label>
                <input type="text" name="from_name" value="<?= View::e($mail['from_name']) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Guardar</button>
        </div>
    </form>

    <form method="post" action="<?= View::e(Url::to('/admin/mail/test')) ?>"
          class="h-fit space-y-3 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <h2 class="text-sm font-semibold text-slate-700">Enviar email de prueba</h2>
        <input type="email" name="test_email" required placeholder="destino@ejemplo.com"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        <button type="submit" class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Enviar prueba</button>
        <p class="text-xs text-slate-400">Guardá la configuración antes de probar.</p>
    </form>
</div>
