<?php
/** @var array $user @var string $rel @var array $breadcrumb @var array $dirs @var array $files @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;

$link = static fn (string $r): string => Url::to('/admin/files') . ($r !== '' ? '?path=' . rawurlencode($r) : '');
$fmtSize = static function (int $b): string {
    $u = ['B', 'KB', 'MB', 'GB']; $i = 0; $n = (float) $b;
    while ($n >= 1024 && $i < 3) { $n /= 1024; $i++; }
    return round($n, 1) . ' ' . $u[$i];
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Archivos</h1>
    <nav class="mt-1 flex flex-wrap items-center gap-1 text-sm text-slate-500">
        <?php foreach ($breadcrumb as $i => $c): ?>
            <?php if ($i > 0): ?><span class="text-slate-300">/</span><?php endif; ?>
            <a href="<?= View::e($link($c['rel'])) ?>" class="hover:text-indigo-600"><?= View::e($c['label']) ?></a>
        <?php endforeach; ?>
    </nav>
</div>


<div class="mb-6 flex flex-wrap gap-3">
    <form method="post" action="<?= View::e(Url::to('/admin/files/upload')) ?>" enctype="multipart/form-data"
          class="flex items-center gap-2 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <input type="hidden" name="path" value="<?= View::e($rel) ?>">
        <input type="file" name="file" required class="text-sm">
        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Subir</button>
    </form>

    <form method="post" action="<?= View::e(Url::to('/admin/files/mkdir')) ?>"
          class="flex items-center gap-2 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <input type="hidden" name="path" value="<?= View::e($rel) ?>">
        <input type="text" name="name" required placeholder="Nueva carpeta"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-900">Crear</button>
    </form>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Nombre</th>
                <th class="px-4 py-3 font-medium">Tamaño</th>
                <th class="px-4 py-3 font-medium">Modificado</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($dirs as $d): ?>
                <tr>
                    <td class="px-4 py-3">
                        <a href="<?= View::e($link($d['rel'])) ?>" class="font-medium text-indigo-600 hover:underline">📁 <?= View::e($d['name']) ?></a>
                    </td>
                    <td class="px-4 py-3 text-slate-400">—</td>
                    <td class="px-4 py-3 text-slate-500"><?= date('Y-m-d H:i', $d['mtime']) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <form method="post" action="<?= View::e(Url::to('/admin/files/rename')) ?>" class="inline"
                                  data-cur="<?= View::e($d['name']) ?>"
                                  onsubmit="var v=prompt('Nuevo nombre:', this.dataset.cur); if(!v) return false; this.querySelector('[name=name]').value=v; return true;">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <input type="hidden" name="path" value="<?= View::e($d['rel']) ?>">
                                <input type="hidden" name="name" value="">
                                <input type="hidden" name="parent" value="<?= View::e($rel) ?>">
                                <button class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100">Renombrar</button>
                            </form>
                            <form method="post" action="<?= View::e(Url::to('/admin/files/move')) ?>" class="inline"
                                  onsubmit="var v=prompt('Carpeta destino (relativa a la raíz; vacío = raíz):', ''); if(v===null) return false; this.querySelector('[name=dest]').value=v; return true;">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <input type="hidden" name="path" value="<?= View::e($d['rel']) ?>">
                                <input type="hidden" name="dest" value="">
                                <input type="hidden" name="parent" value="<?= View::e($rel) ?>">
                                <button class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100">Mover</button>
                            </form>
                            <form method="post" action="<?= View::e(Url::to('/admin/files/delete')) ?>"
                                  onsubmit="return confirm('¿Eliminar la carpeta y su contenido?');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <input type="hidden" name="path" value="<?= View::e($d['rel']) ?>">
                                <input type="hidden" name="parent" value="<?= View::e($rel) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td class="px-4 py-3 text-slate-700">
                        <?php if (\App\Services\FileManager::isImage($f['name'])): ?>
                            <img src="<?= View::e(Url::to('/admin/files/raw') . '?path=' . rawurlencode($f['rel'])) ?>" alt=""
                                 class="mr-1 inline-block h-8 w-8 rounded object-cover align-middle ring-1 ring-slate-200">
                        <?php else: ?>
                            📄
                        <?php endif; ?>
                        <?= View::e($f['name']) ?>
                        <?php if (isset($shares[$f['rel']])): ?>
                            <a href="<?= View::e(Url::to('/a/' . $shares[$f['rel']])) ?>" target="_blank"
                               class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-700" title="Link público activo">🔗 público</a>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= $fmtSize($f['size']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= date('Y-m-d H:i', $f['mtime']) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= View::e(Url::to('/admin/files/download') . '?path=' . rawurlencode($f['rel'])) ?>"
                               class="rounded-md px-2 py-1 text-indigo-600 hover:bg-indigo-50">Descargar</a>
                            <?php if (isset($shares[$f['rel']])): ?>
                                <form method="post" action="<?= View::e(Url::to('/admin/files/unshare')) ?>" class="inline">
                                    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                    <input type="hidden" name="path" value="<?= View::e($f['rel']) ?>">
                                    <input type="hidden" name="parent" value="<?= View::e($rel) ?>">
                                    <button class="rounded-md px-2 py-1 text-amber-600 hover:bg-amber-50">No compartir</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= View::e(Url::to('/admin/files/share')) ?>" class="inline">
                                    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                    <input type="hidden" name="path" value="<?= View::e($f['rel']) ?>">
                                    <input type="hidden" name="parent" value="<?= View::e($rel) ?>">
                                    <button class="rounded-md px-2 py-1 text-emerald-600 hover:bg-emerald-50">Compartir</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= View::e(Url::to('/admin/files/rename')) ?>" class="inline"
                                  data-cur="<?= View::e($f['name']) ?>"
                                  onsubmit="var v=prompt('Nuevo nombre:', this.dataset.cur); if(!v) return false; this.querySelector('[name=name]').value=v; return true;">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <input type="hidden" name="path" value="<?= View::e($f['rel']) ?>">
                                <input type="hidden" name="name" value="">
                                <input type="hidden" name="parent" value="<?= View::e($rel) ?>">
                                <button class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100">Renombrar</button>
                            </form>
                            <form method="post" action="<?= View::e(Url::to('/admin/files/move')) ?>" class="inline"
                                  onsubmit="var v=prompt('Carpeta destino (relativa a la raíz; vacío = raíz):', ''); if(v===null) return false; this.querySelector('[name=dest]').value=v; return true;">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <input type="hidden" name="path" value="<?= View::e($f['rel']) ?>">
                                <input type="hidden" name="dest" value="">
                                <input type="hidden" name="parent" value="<?= View::e($rel) ?>">
                                <button class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100">Mover</button>
                            </form>
                            <form method="post" action="<?= View::e(Url::to('/admin/files/delete')) ?>"
                                  onsubmit="return confirm('¿Eliminar el archivo?');">
                                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                                <input type="hidden" name="path" value="<?= View::e($f['rel']) ?>">
                                <input type="hidden" name="parent" value="<?= View::e($rel) ?>">
                                <button class="rounded-md px-2 py-1 text-red-600 hover:bg-red-50">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($dirs) && empty($files)): ?>
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Carpeta vacía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
