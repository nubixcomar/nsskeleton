<?php
/** @var array $modules */
use Core\View;
use Core\Url;
use Core\Icons;
?>
<h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Módulos del core</h2>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($modules as $m): ?>
        <a href="<?= View::e(Url::to($m['url'])) ?>" class="flex items-start gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><?= Icons::render($m['icon'], 'h-5 w-5') ?></span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-slate-800"><?= View::e($m['title']) ?></span>
                <span class="block text-xs text-slate-500"><?= View::e($m['desc']) ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>
<p class="mt-6 text-center text-xs text-slate-400">Dashboard de ejemplo — reutilizá esta estructura (KPIs, gráficos, salud, novedades, actividad, módulos) en tu app.</p>
