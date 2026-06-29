<?php
/** @var array $user @var array $backups @var array $history @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;

$fmtSize = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float) $bytes;
    while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
    return round($n, 1) . ' ' . $units[$i];
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Backups</h1>
    <p class="text-sm text-slate-500">Copias del sistema (archivos) y de la base de datos.</p>
</div>


<div class="mb-6 flex flex-wrap gap-3">
    <?php foreach ([
        ['/admin/backup/db', 'Backup de base de datos', 'bg-indigo-600 hover:bg-indigo-700'],
        ['/admin/backup/files', 'Backup de archivos', 'bg-slate-800 hover:bg-slate-900'],
        ['/admin/backup/full', 'Backup completo', 'bg-emerald-600 hover:bg-emerald-700'],
    ] as [$action, $label, $cls]): ?>
        <form method="post" action="<?= View::e(Url::to($action)) ?>">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
            <button class="rounded-lg px-4 py-2 text-sm font-medium text-white <?= $cls ?>"><?= View::e($label) ?></button>
        </form>
    <?php endforeach; ?>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Archivo</th>
                <th class="px-4 py-3 font-medium">Tipo</th>
                <th class="px-4 py-3 font-medium">Tamaño</th>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($backups as $b): ?>
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= View::e($b['name']) ?></td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $b['type'] === 'db' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' ?>">
                            <?= $b['type'] === 'db' ? 'Base de datos' : 'Archivos' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= $fmtSize($b['size']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= date('Y-m-d H:i', $b['mtime']) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= View::e(Url::to('/admin/backup/download/' . rawurlencode($b['name']))) ?>"
                               class="rounded-md px-2 py-1 text-indigo-600 hover:bg-indigo-50">Descargar</a>
                            <?php if ($b['type'] === 'db'): ?>
                                <form method="post" action="<?= View::e(Url::to('/admin/backup/restore')) ?>"
                                      onsubmit="return confirm('⚠️ Esto SOBRESCRIBE la base de datos actual con el contenido del backup. ¿Continuar?');">
                                    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                    <input type="hidden" name="file" value="<?= View::e($b['name']) ?>">
                                    <button class="rounded-md px-2 py-1 text-amber-700 hover:bg-amber-50">Restaurar</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= View::e(Url::to('/admin/backup/delete')) ?>"
                                  onsubmit="return confirm('¿Eliminar este backup?');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <input type="hidden" name="file" value="<?= View::e($b['name']) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($backups)): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin backups todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($history)): ?>
    <h2 class="mb-2 mt-8 text-sm font-semibold text-slate-700">Actividad reciente</h2>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-xs">
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td class="px-4 py-2 text-slate-500"><?= View::e($h['created_at']) ?></td>
                        <td class="px-4 py-2 font-medium text-slate-700"><?= View::e($h['type']) ?></td>
                        <td class="px-4 py-2 text-slate-500"><?= View::e($h['file'] ?? '—') ?></td>
                        <td class="px-4 py-2 <?= $h['status'] === 'ok' ? 'text-emerald-600' : 'text-red-600' ?>"><?= View::e($h['status']) ?></td>
                        <td class="px-4 py-2 text-slate-400"><?= View::e($h['message'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p class="mt-6 text-xs text-slate-400">
    Para backups automáticos, programá <code>php system/backup/run.php</code> en el cronmaster
    o en el cron del SO. Retención configurable con <code>BACKUP_RETENTION_DAYS</code> en el .env.
</p>
