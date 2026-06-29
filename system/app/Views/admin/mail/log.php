<?php
/** @var array $user @var array $entries */
use Core\View;
use Core\Url;
?>
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900">Historial de emails</h1>
    <a href="<?= View::e(Url::to('/admin/mail')) ?>" class="text-sm text-indigo-600 hover:underline">← Configuración</a>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Destino</th>
                <th class="px-4 py-3 font-medium">Asunto</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium">Detalle</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($entries as $e): ?>
                <tr>
                    <td class="px-4 py-3 text-slate-500"><?= View::e($e['created_at']) ?></td>
                    <td class="px-4 py-3 text-slate-700"><?= View::e($e['to_address']) ?></td>
                    <td class="px-4 py-3 text-slate-700"><?= View::e($e['subject']) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($e['status'] === 'sent'): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Enviado</span>
                        <?php else: ?>
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Falló</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500"><?= View::e($e['error'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin envíos registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
