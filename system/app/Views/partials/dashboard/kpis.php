<?php
/** @var array $kpis */
use Core\View;
use Core\Url;
use Core\Icons;

$badge = [
    'indigo' => 'bg-indigo-100 text-indigo-600', 'emerald' => 'bg-emerald-100 text-emerald-600',
    'sky' => 'bg-sky-100 text-sky-600', 'amber' => 'bg-amber-100 text-amber-600',
    'rose' => 'bg-rose-100 text-rose-600', 'violet' => 'bg-violet-100 text-violet-600',
    'cyan' => 'bg-cyan-100 text-cyan-600', 'slate' => 'bg-slate-100 text-slate-600',
];
?>
<h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Indicadores</h2>
<div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($kpis as $k): ?>
        <a href="<?= View::e(Url::to($k['url'])) ?>" class="group rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-center justify-between">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg <?= $badge[$k['color']] ?? $badge['slate'] ?>"><?= Icons::render($k['icon'], 'h-5 w-5') ?></span>
                <svg class="h-4 w-4 text-slate-300 transition group-hover:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
            <p class="mt-3 text-2xl font-bold text-slate-900"><?= View::e((string) $k['value']) ?></p>
            <p class="text-sm font-medium text-slate-600"><?= View::e($k['label']) ?></p>
            <p class="text-xs text-slate-400"><?= View::e($k['sub']) ?></p>
        </a>
    <?php endforeach; ?>
</div>
