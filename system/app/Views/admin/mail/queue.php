<?php
/** @var array $user @var array $rows @var array $counts @var ?string $success */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Cola de emails</h1>
        <p class="text-sm text-slate-500">
            Pendientes: <strong><?= (int) $counts['pending'] ?></strong> ·
            Enviados: <strong class="text-emerald-600"><?= (int) $counts['sent'] ?></strong> ·
            Fallidos: <strong class="text-red-600"><?= (int) $counts['failed'] ?></strong>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= View::e(Url::to('/admin/mail')) ?>" class="text-sm text-indigo-600 hover:underline">← Configuración</a>
        <form method="post" action="<?= View::e(Url::to('/admin/mail/queue/process')) ?>">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Procesar ahora</button>
        </form>
    </div>
</div>


<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Creado</th>
                <th class="px-4 py-3 font-medium">Destino</th>
                <th class="px-4 py-3 font-medium">Asunto</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium">Intentos</th>
                <th class="px-4 py-3 font-medium">Error</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $r): ?>
                <?php
                $badge = match ($r['status']) {
                    'sent'   => 'bg-emerald-100 text-emerald-700',
                    'failed' => 'bg-red-100 text-red-700',
                    default  => 'bg-amber-100 text-amber-700',
                };
                ?>
                <tr>
                    <td class="px-4 py-3 text-slate-500"><?= View::e($r['created_at']) ?></td>
                    <td class="px-4 py-3 text-slate-700"><?= View::e($r['to_address']) ?></td>
                    <td class="px-4 py-3 text-slate-700"><?= View::e($r['subject']) ?></td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $badge ?>"><?= View::e($r['status']) ?></span></td>
                    <td class="px-4 py-3 text-slate-500"><?= (int) $r['attempts'] ?></td>
                    <td class="px-4 py-3 text-xs text-slate-400"><?= View::e($r['error'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Cola vacía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<p class="mt-4 text-xs text-slate-400">Tip: programá el job <code>job:email:queue</code> en el cron para drenar la cola automáticamente.</p>
