<?php
/**
 * @var array $user @var string $installed @var string $latestUrl @var array|null $check
 * @var array|null $pending @var array|null $inspect @var bool $merge @var array $base
 * @var array $editedNoBase @var array $history @var array|null $lastResult
 */
use App\Services\CoreUpdater;
use Core\Session;
use Core\Url;
use Core\View;

$csrf = View::e(Session::csrf());
$latest = $check['latest'] ?? null;
$hasUpdate = !empty($check['ok']) && !empty($check['update_available']);

$actionLabel = [
    CoreUpdater::ADD => ['Agregar', 'bg-emerald-100 text-emerald-700'],
    CoreUpdater::UPDATE => ['Actualizar', 'bg-indigo-100 text-indigo-700'],
    CoreUpdater::DELETE => ['Eliminar', 'bg-slate-200 text-slate-700'],
    CoreUpdater::CONFLICT => ['Editado por el proyecto', 'bg-amber-100 text-amber-800'],
    CoreUpdater::CONFLICT_ADD => ['Conflicto (archivo nuevo)', 'bg-amber-100 text-amber-800'],
    CoreUpdater::DELETE_MODIFIED => ['Eliminado pero editado (se conserva)', 'bg-slate-100 text-slate-600'],
    CoreUpdater::SKIP => ['Sin cambios', 'bg-slate-100 text-slate-500'],
];
$mergeLabel = [
    'clean'    => ['Se combina solo', 'bg-emerald-100 text-emerald-700'],
    'conflict' => ['Con choques → .merge + .new', 'bg-red-100 text-red-700'],
    'no_base'  => ['Sin original → .new', 'bg-amber-100 text-amber-800'],
    'binary'   => ['Binario → .new', 'bg-slate-100 text-slate-600'],
];
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Actualizar core</h1>
    <p class="text-sm text-slate-500">
        Trae la versión nueva de nsSkeleton sin tocar lo del proyecto. Los archivos del core que editaste
        se combinan con un merge 3-way cuando se puede.
    </p>
</div>

<!-- Estado -->
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Core instalado</p>
        <p class="mt-1 text-lg font-bold text-slate-900">v<?= View::e($installed ?: '?') ?></p>
    </div>
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Última publicada</p>
        <p class="mt-1 text-lg font-bold <?= $hasUpdate ? 'text-indigo-600' : 'text-slate-900' ?>">
            <?= $latest ? 'v' . View::e((string) $latest) : '—' ?>
        </p>
        <?php if (!empty($check['at'])): ?>
            <p class="text-xs text-slate-400">consultado <?= View::e(substr((string) $check['at'], 0, 16)) ?></p>
        <?php endif; ?>
    </div>
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Originales guardados (para merge)</p>
        <p class="mt-1 text-lg font-bold <?= $base['valid'] === $base['total'] ? 'text-emerald-600' : 'text-slate-900' ?>">
            <?= (int) $base['valid'] ?> / <?= (int) $base['total'] ?>
        </p>
    </div>
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Archivos del core editados sin original</p>
        <p class="mt-1 text-lg font-bold <?= $editedNoBase === [] ? 'text-emerald-600' : 'text-amber-600' ?>"><?= count($editedNoBase) ?></p>
    </div>
</div>

<?php if ($latestUrl === ''): ?>
    <div class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
        Falta configurar de dónde bajar las versiones. Agregá en el <code>.env</code>:
        <code class="ml-1 rounded bg-white px-1">CORE_UPDATE_URL=https://tu-landing/downloads/latest.json</code>
    </div>
<?php elseif (!empty($check['insecure'])): ?>
    <div class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
        <code>latest.json</code> se está sirviendo por <strong>http</strong>. Usá https para que nadie pueda alterar el índice en el camino.
    </div>
<?php endif; ?>

<?php if ($editedNoBase !== []): ?>
    <div class="mb-6 rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-slate-200">
        <p class="font-medium text-slate-800">Hay archivos del core editados cuyo original no está guardado</p>
        <p class="mt-1 text-slate-500">
            Si el core nuevo también los cambia, no se van a poder combinar solos: quedarán como <code>.new</code> para revisar a mano.
            Podés completar los originales bajando el paquete de tu versión (v<?= View::e($installed) ?>) del landing.
        </p>
        <ul class="mt-2 list-inside list-disc font-mono text-xs text-slate-600">
            <?php foreach (array_slice($editedNoBase, 0, 8) as $p): ?>
                <li><?= View::e($p) ?></li>
            <?php endforeach; ?>
            <?php if (count($editedNoBase) > 8): ?><li>… y <?= count($editedNoBase) - 8 ?> más</li><?php endif; ?>
        </ul>
        <?php if ($latestUrl !== ''): ?>
            <form method="post" action="<?= View::e(Url::to('/admin/core-update/seed-base')) ?>" class="mt-3">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-900">Completar originales desde el landing</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Paso 1: buscar / preparar -->
<?php if ($pending === null): ?>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <form method="post" action="<?= View::e(Url::to('/admin/core-update/check')) ?>">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                <?= $latestUrl === '' ? 'disabled' : '' ?>>Buscar versión nueva</button>
        </form>
        <?php if ($hasUpdate): ?>
            <form method="post" action="<?= View::e(Url::to('/admin/core-update/prepare')) ?>">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Preparar actualización a v<?= View::e((string) $latest) ?>
                </button>
            </form>
            <span class="text-xs text-slate-500">Descarga el paquete, verifica su sha256 y muestra el plan. Todavía no cambia nada.</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Paso 2: plan + aplicar -->
<?php if ($pending !== null && $inspect !== null): ?>
    <?php
    $sum = $inspect['summary'];
    $isAppEdit = static fn (array $e): bool => $e['action'] === CoreUpdater::SKIP && $e['reason'] === CoreUpdater::REASON_APP_EDIT;
    $flagged = array_values(array_filter($inspect['plan'], static fn ($e) => $isAppEdit($e)
        || in_array($e['action'], [CoreUpdater::CONFLICT, CoreUpdater::CONFLICT_ADD, CoreUpdater::DELETE_MODIFIED], true)));
    ?>
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-lg font-semibold text-slate-900">Plan: v<?= View::e($inspect['installed']) ?> → v<?= View::e($inspect['new']) ?></h2>
            <span class="font-mono text-xs text-slate-400">sha256 <?= View::e(substr((string) $pending['sha256'], 0, 16)) ?>… verificado</span>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <?php foreach ($actionLabel as $action => [$label, $cls]): ?>
                <?php if (!empty($sum[$action])): ?>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium <?= $cls ?>"><?= View::e($label) ?>: <?= (int) $sum[$action] ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($flagged !== []): ?>
            <div class="mb-4 overflow-x-auto rounded-lg ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Archivo</th>
                            <th class="px-3 py-2 font-medium">Situación</th>
                            <th class="px-3 py-2 font-medium">Qué va a pasar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($flagged as $e): ?>
                            <?php
                            [$al, $ac] = $isAppEdit($e)
                                ? ['Editado por el proyecto (el core no lo cambió)', 'bg-slate-100 text-slate-600']
                                : $actionLabel[$e['action']];
                            $m = $inspect['merge'][$e['path']] ?? null;
                            if ($isAppEdit($e)) {
                                [$ml, $mc] = ['Se conserva tu versión', 'bg-slate-100 text-slate-600'];
                            } elseif ($e['action'] === CoreUpdater::CONFLICT && $m !== null) {
                                [$ml, $mc] = $mergeLabel[$m['status']] ?? [$m['status'], 'bg-slate-100 text-slate-600'];
                                if ($m['status'] === 'conflict') {
                                    $ml .= " ({$m['conflicts']})";
                                }
                            } elseif ($e['action'] === CoreUpdater::DELETE_MODIFIED) {
                                [$ml, $mc] = ['Se conserva tu versión', 'bg-slate-100 text-slate-600'];
                            } else {
                                [$ml, $mc] = ['Se deja .new para revisar', 'bg-amber-100 text-amber-800'];
                            }
                            ?>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-slate-700"><?= View::e($e['path']) ?></td>
                                <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $ac ?>"><?= View::e($al) ?></span></td>
                                <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $mc ?>"><?= View::e($ml) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($inspect['unwritable'] !== []): ?>
            <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200">
                El servidor no puede escribir <?= count($inspect['unwritable']) ?> ruta(s) que el update necesita
                (por ejemplo <code><?= View::e($inspect['unwritable'][0]) ?></code>). Corregí los permisos o actualizá por consola.
            </div>
        <?php endif; ?>

        <?php if ($inspect['changes'] === 0): ?>
            <p class="mb-4 text-sm text-slate-600">No hay nada para aplicar: el core ya coincide con esta versión.</p>
        <?php elseif ($inspect['unwritable'] === []): ?>
            <form method="post" action="<?= View::e(Url::to('/admin/core-update/apply')) ?>" class="flex flex-wrap items-end gap-3"
                  onsubmit="return confirm('Se va a actualizar el core. Se hace backup y se puede revertir. ¿Continuar?');">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <label class="text-sm text-slate-600">
                    Para confirmar, escribí <strong class="font-mono"><?= View::e($pending['version']) ?></strong>
                    <input name="confirm" autocomplete="off" required
                           class="mt-1 block w-40 rounded-lg border-slate-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Aplicar actualización</button>
            </form>
            <p class="mt-2 text-xs text-slate-400">
                Se respaldan los archivos tocados, se corren las migraciones del core y queda disponible el rollback.
                <?= $merge ? '' : 'El merge automático está desactivado (config core_update.merge).' ?>
            </p>
        <?php endif; ?>

        <form method="post" action="<?= View::e(Url::to('/admin/core-update/discard')) ?>" class="mt-4">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <button class="text-xs text-slate-500 underline hover:text-slate-700">Descartar este paquete</button>
        </form>
    </div>
<?php endif; ?>

<!-- Resultado del último update -->
<?php if ($lastResult !== null && ($lastResult['backup_dir'] ?? '') !== '' && ($lastResult['merged'] !== [] || $lastResult['conflicts'] !== [])): ?>
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-2 text-sm font-semibold text-slate-700">Último update (v<?= View::e((string) $lastResult['from']) ?> → v<?= View::e((string) $lastResult['to']) ?>): para revisar</h2>
        <?php if ($lastResult['merged'] !== []): ?>
            <p class="text-sm text-slate-600">Combinados automáticamente (conviene darles una mirada y correr los tests):</p>
            <ul class="mb-3 mt-1 list-inside list-disc font-mono text-xs text-slate-600">
                <?php foreach ($lastResult['merged'] as $p): ?><li><?= View::e((string) $p) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($lastResult['conflicts'] !== []): ?>
            <p class="text-sm text-slate-600">Conflictos: tu archivo quedó intacto. Mergeá y borrá los <code>.new</code> / <code>.merge</code>:</p>
            <ul class="mt-1 list-inside list-disc font-mono text-xs text-slate-600">
                <?php foreach ($lastResult['conflicts'] as $p): ?>
                    <li><?= View::e((string) $p) ?>.new<?= in_array($p, (array) $lastResult['merge_conflicts'], true) ? ' · propuesta con marcadores en ' . View::e((string) $p) . '.merge' : '' ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Historial -->
<h2 class="mb-2 text-sm font-semibold text-slate-700">Updates aplicados</h2>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Versión</th>
                <th class="px-4 py-3 font-medium">Resultado</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($history as $h): ?>
                <tr>
                    <td class="px-4 py-3 text-slate-500"><?= View::e(str_replace('T', ' ', substr($h['at'], 0, 16))) ?></td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-700">v<?= View::e($h['from']) ?> → v<?= View::e($h['to']) ?></td>
                    <td class="px-4 py-3 text-xs">
                        <?php if ($h['rolled_back']): ?>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Revertido</span>
                        <?php elseif ($h['ok']): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-700">OK</span>
                        <?php else: ?>
                            <span class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-700">Con errores</span>
                        <?php endif; ?>
                        <span class="ml-2 text-slate-500"><?= (int) $h['merged'] ?> combinados · <?= (int) $h['conflicts'] ?> conflictos</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if (!$h['rolled_back']): ?>
                            <form method="post" action="<?= View::e(Url::to('/admin/core-update/rollback')) ?>"
                                  onsubmit="return confirm('Revertir este update restaura los archivos del core anteriores. ¿Continuar?');">
                                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="backup" value="<?= View::e($h['name']) ?>">
                                <button class="rounded-md px-2 py-1 text-amber-700 hover:bg-amber-50">Revertir</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($history === []): ?>
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Todavía no se aplicó ningún update.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<p class="mt-6 text-xs text-slate-400">
    Por consola hace lo mismo: <code>php system/console/core-update.php --check</code> ·
    <code>--latest</code> · <code>--latest --apply</code> · <code>--rollback=&lt;carpeta&gt;</code>.
    En hostings con límite de tiempo corto o sin permisos de escritura, conviene la consola.
</p>
