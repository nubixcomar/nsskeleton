<?php
/** @var array $recentAudit @var array $recentJobs */
use Core\View;
use Core\Url;
?>
<div class="mb-8 grid gap-4 lg:grid-cols-2">
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-700">🕑 Actividad reciente</h2>
            <a href="<?= View::e(Url::to('/admin/audit')) ?>" class="text-xs text-indigo-600 hover:underline">Ver auditoría</a>
        </div>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($recentAudit as $r): ?>
                <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                    <span class="min-w-0">
                        <span class="font-mono text-xs text-slate-600"><?= View::e($r['action']) ?></span>
                        <span class="text-slate-400"> · <?= View::e($r['admin_name'] ?? 'sistema') ?></span>
                    </span>
                    <span class="shrink-0 text-xs text-slate-400"><?= View::e($r['created_at']) ?></span>
                </li>
            <?php endforeach; ?>
            <?php if (empty($recentAudit)): ?><li class="px-5 py-6 text-center text-sm text-slate-400">Sin actividad aún.</li><?php endif; ?>
        </ul>
    </div>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-700">⚙️ Jobs recientes</h2>
            <a href="<?= View::e(Url::to('/admin/jobs')) ?>" class="text-xs text-indigo-600 hover:underline">Ver cola</a>
        </div>
        <ul class="divide-y divide-slate-100">
            <?php
            $jb = ['pending' => 'bg-amber-100 text-amber-700', 'processing' => 'bg-blue-100 text-blue-700', 'done' => 'bg-emerald-100 text-emerald-700', 'failed' => 'bg-red-100 text-red-700'];
            foreach ($recentJobs as $j): ?>
                <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                    <span class="font-mono text-xs text-slate-600"><?= View::e($j['handler']) ?></span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $jb[$j['status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= View::e($j['status']) ?></span>
                </li>
            <?php endforeach; ?>
            <?php if (empty($recentJobs)): ?><li class="px-5 py-6 text-center text-sm text-slate-400">No hay jobs todavía.</li><?php endif; ?>
        </ul>
    </div>
</div>
