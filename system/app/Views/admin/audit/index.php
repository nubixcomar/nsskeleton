<?php
/** @var array $user @var array $rows @var array $pagination */
use Core\View;
use Core\Url;
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Auditoría</h1>
        <p class="text-sm text-slate-500">Acciones realizadas por administradores.</p>
    </div>
    <?= View::partial('partials/search', ['action' => Url::to('/admin/audit'), 'search' => $pagination['search'] ?? '']) ?>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Admin</th>
                <th class="px-4 py-3 font-medium">Acción</th>
                <th class="px-4 py-3 font-medium">Objetivo</th>
                <th class="px-4 py-3 font-medium">Detalle</th>
                <th class="px-4 py-3 font-medium">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="px-4 py-3 text-slate-500"><?= View::e($row['created_at']) ?></td>
                    <td class="px-4 py-3 text-slate-700"><?= View::e($row['admin_name'] ?? '—') ?></td>
                    <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"><?= View::e($row['action']) ?></span></td>
                    <td class="px-4 py-3 text-slate-600"><?= View::e($row['target'] ?? '') ?></td>
                    <td class="px-4 py-3 text-slate-500">
                        <?php
                        $changes = !empty($row['changes']) ? json_decode((string) $row['changes'], true) : null;
                        if (is_array($changes) && $changes !== []):
                        ?>
                            <div class="space-y-1">
                                <?php foreach ($changes as $field => $ch): ?>
                                    <div class="text-xs">
                                        <span class="font-medium text-slate-600"><?= View::e($field) ?>:</span>
                                        <span class="rounded bg-red-50 px-1 text-red-700 line-through"><?= View::e((string) ($ch['old'] ?? '∅')) ?></span>
                                        <span class="text-slate-400">→</span>
                                        <span class="rounded bg-emerald-50 px-1 text-emerald-700"><?= View::e((string) ($ch['new'] ?? '∅')) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?= View::e($row['details'] ?? '') ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-400"><?= View::e($row['ip'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Sin registros.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= View::partial('partials/pagination', ['pagination' => $pagination ?? [], 'baseUrl' => Url::to('/admin/audit')]) ?>
