<?php
/** @var array $user @var array $health @var array $flags */
use Core\View;
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Estado del sistema</h1>
    <p class="text-sm text-slate-500">Métricas de salud y feature flags. Endpoint público: <code>/health</code>.</p>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php
    $cards = [
        ['Estado', $health['status'], $health['status'] === 'ok' ? 'text-emerald-600' : 'text-red-600'],
        ['Versión App', 'v' . ($health['app_version'] ?? '?'), 'text-indigo-600'],
        ['Core nsSkeleton', 'v' . ($health['core'] ?? $health['version'] ?? '?'), 'text-slate-900'],
        ['Base de datos', $health['db'] ? 'conectada' : 'caída', $health['db'] ? 'text-emerald-600' : 'text-red-600'],
        ['PHP', $health['php'], 'text-slate-900'],
        ['Cola de emails (pend.)', (string) $health['email_queue_pending'], 'text-slate-900'],
        ['Disco libre', $health['disk_free_mb'] !== null ? number_format($health['disk_free_mb']) . ' MB' : '—', 'text-slate-900'],
        ['Último backup', $health['last_backup'] ?? '—', 'text-slate-900'],
    ];
    foreach ($cards as [$label, $value, $cls]): ?>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm text-slate-500"><?= View::e($label) ?></p>
            <p class="mt-1 text-lg font-bold <?= $cls ?>"><?= View::e((string) $value) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="mb-2 text-sm font-semibold text-slate-700">Feature flags</h2>
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($flags as $name => $on): ?>
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= View::e($name) ?></td>
                    <td class="px-4 py-3 text-right">
                        <?php if ($on): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">ON</span>
                        <?php else: ?>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">OFF</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="mt-2 text-xs text-slate-400">Los flags se cambian en <strong>Configuración → Feature flags</strong>.</p>
