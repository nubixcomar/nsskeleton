<?php
/** @var array $user @var string $q @var array $groups */
use Core\View;
use Core\Url;
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Búsqueda global</h1>
    <p class="text-sm text-slate-500">Resultados para “<?= View::e($q) ?>” en todos los módulos.</p>
</div>

<form method="get" action="<?= View::e(Url::to('/admin/search')) ?>" class="mb-6 flex max-w-lg gap-2">
    <input type="search" name="q" value="<?= View::e($q) ?>" placeholder="Buscar…" autofocus
           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Buscar</button>
</form>

<?php if ($q !== '' && empty($groups)): ?>
    <div class="rounded-xl bg-white p-6 text-center text-slate-400 shadow-sm ring-1 ring-slate-200">
        Sin resultados para “<?= View::e($q) ?>”.
    </div>
<?php endif; ?>

<div class="space-y-5">
    <?php foreach ($groups as $group): ?>
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-700"><?= View::e($group['label']) ?></h2>
                <a href="<?= View::e(Url::to($group['path'])) ?>" class="text-xs text-indigo-600 hover:underline">Ver módulo</a>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($group['matches'] as $m): ?>
                    <li>
                        <a href="<?= View::e(Url::to($m['url'])) ?>" class="flex items-center justify-between px-5 py-3 text-sm hover:bg-slate-50">
                            <span class="text-slate-700"><?= View::e($m['label']) ?></span>
                            <span class="text-xs text-slate-400">#<?= (int) $m['id'] ?> · editar →</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
