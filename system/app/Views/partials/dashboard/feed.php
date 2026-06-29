<?php
/** @var array $novedades @var array $health */
use Core\View;

$tag = [
    'emerald' => 'bg-emerald-100 text-emerald-700', 'indigo' => 'bg-indigo-100 text-indigo-700',
    'rose' => 'bg-rose-100 text-rose-700', 'sky' => 'bg-sky-100 text-sky-700',
];
?>
<div class="mb-8 grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">📣 Novedades</h2>
        <ul class="space-y-3">
            <?php foreach ($novedades as $n): ?>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $tag[$n['color']] ?? $tag['indigo'] ?>"><?= View::e($n['tag']) ?></span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800"><?= View::e($n['title']) ?></p>
                        <p class="text-xs text-slate-500"><?= View::e($n['text']) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">🩺 Estado del sistema</h2>
        <dl class="space-y-2 text-sm">
            <?php
            $rows = [
                ['Base de datos', $health['db'] ? 'conectada' : 'caída', $health['db'] ? 'text-emerald-600' : 'text-red-600'],
                ['PHP', $health['php'] ?? '?', 'text-slate-700'],
                ['Disco libre', $health['disk_free_mb'] !== null ? number_format((int) $health['disk_free_mb']) . ' MB' : '—', 'text-slate-700'],
                ['Cola de emails', (string) ($health['email_queue_pending'] ?? 0) . ' pend.', 'text-slate-700'],
                ['Último backup', $health['last_backup'] ?? '—', 'text-slate-700'],
                ['Versión App', 'v' . ($health['app_version'] ?? '?'), 'text-indigo-600'],
                ['Core nsSkeleton', 'v' . ($health['core'] ?? '?'), 'text-slate-700'],
            ];
            foreach ($rows as [$l, $v, $c]): ?>
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                    <dt class="text-slate-500"><?= View::e($l) ?></dt>
                    <dd class="font-medium <?= $c ?>"><?= View::e((string) $v) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>
</div>
