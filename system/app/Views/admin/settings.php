<?php
/** @var array $user @var string $name @var string $tagline @var string $timezone @var ?string $logo @var array $timezones @var ?string $success @var ?string $error */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Configuración general</h1>
    <p class="text-sm text-slate-500">Nombre del sistema, zona horaria y branding.</p>
</div>


<form method="post" action="<?= View::e(Url::to('/admin/settings')) ?>" enctype="multipart/form-data"
      class="max-w-xl space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Nombre del sistema</label>
        <input type="text" name="name" value="<?= View::e($name) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Tagline / descripción corta</label>
        <input type="text" name="tagline" value="<?= View::e($tagline) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Preset de dashboard</label>
        <select name="dashboard_preset" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500 sm:w-72">
            <?php foreach (($dashPresets ?? []) as $key => $p): ?>
                <option value="<?= View::e($key) ?>" <?= ($dashActive ?? '') === $key ? 'selected' : '' ?>><?= View::e($p['label'] ?? $key) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-slate-400">Combinación de bloques del dashboard (Completo, Operativo, Showcase, Mínimo).</p>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Versión de la app</label>
        <div class="flex items-center gap-2">
            <input type="text" name="app_version" value="<?= View::e($appVersion ?? '') ?>" placeholder="1.0.0"
                   class="w-40 rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
            <span class="text-xs text-slate-400">Core: nsSkeleton v<?= View::e($coreVersion ?? '') ?> (no editable)</span>
        </div>
        <p class="mt-1 text-xs text-slate-400">La versión de tu app; el core nsSkeleton se versiona aparte.</p>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Zona horaria</label>
        <select name="timezone" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
            <?php foreach ($timezones as $tz): ?>
                <option value="<?= View::e($tz) ?>" <?= $tz === $timezone ? 'selected' : '' ?>><?= View::e($tz) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Logo</label>
        <?php if (!empty($logo)): ?>
            <div class="mb-2 flex items-center gap-3">
                <img src="<?= View::e(Url::to('/' . $logo)) ?>" alt="logo" class="h-10 w-10 rounded-lg object-contain ring-1 ring-slate-200">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-indigo-600">
                    Quitar logo
                </label>
            </div>
        <?php endif; ?>
        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg" class="text-sm">
        <p class="mt-1 text-xs text-slate-400">png / jpg / webp / svg · máx 1 MB.</p>
    </div>

    <div>
        <h2 class="mb-2 text-sm font-semibold text-slate-700">Feature flags</h2>
        <div class="space-y-2">
            <?php foreach (($flags ?? []) as $name => $on): ?>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="flag_<?= View::e($name) ?>" value="1" <?= $on ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="font-mono text-xs"><?= View::e($name) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="pt-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Guardar</button>
    </div>
</form>
