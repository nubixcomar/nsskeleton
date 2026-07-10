<?php
/** @var array $user @var bool $enabled @var bool $hasSecret @var string $url @var string $curl
 *  @var int $minInterval @var string $ips @var string|null $success @var string|null $error */
use Core\Session;
use Core\Url;
use Core\View;
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Cron master</h1>
    <p class="text-sm text-slate-500">El disparador externo (servidor o EasyCron) que pega a esta URL cada pocos minutos.</p>
</div>

<?= View::partial('admin/cron/_subnav', ['active' => 'master']) ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <?php if ($hasSecret): ?>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">URL del cron master</p>
                <input readonly value="<?= View::e($url) ?>"
                       class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700"
                       onclick="this.select()">
                <p class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Comando para tu cron (servidor)</p>
                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><?= View::e($curl) ?></pre>
                <p class="mt-2 text-xs text-slate-500">
                    El secreto también se puede mandar por header <code>X-Cron-Key</code> en vez de <code>?key=</code>
                    (no queda en los access logs).
                </p>
            </div>
        <?php else: ?>
            <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                Todavía no hay secreto generado. Generalo a la derecha para obtener la URL protegida.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= View::e(Url::to('/admin/cron/master')) ?>"
              class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">

            <label class="flex items-center gap-3">
                <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>
                       class="h-5 w-9 rounded-full">
                <span class="text-sm font-semibold text-slate-800">Cron master habilitado</span>
            </label>
            <p class="mb-4 ml-12 text-xs text-slate-500">Si está apagado, el endpoint responde 503 y no corre ninguna tarea.</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600" for="min_interval">Intervalo mínimo (seg)</label>
                    <input id="min_interval" name="min_interval" type="number" min="0" value="<?= (int) $minInterval ?>"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Anti-martilleo: ticks más seguidos se ignoran.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600" for="ip_allowlist">Allowlist de IP (una por línea, opcional)</label>
                    <textarea id="ip_allowlist" name="ip_allowlist" rows="3"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs"><?= View::e($ips) ?></textarea>
                    <p class="mt-1 text-xs text-slate-500">Vacío = sin filtro de IP (solo el secreto protege).</p>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar</button>
            </div>
        </form>
    </div>

    <div class="space-y-4">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Secreto</p>
            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium <?= $hasSecret
                ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                <?= $hasSecret ? 'CONFIGURADO' : 'SIN GENERAR' ?>
            </span>
            <form method="post" action="<?= View::e(Url::to('/admin/cron/master/rotate')) ?>" class="mt-3"
                  onsubmit="return confirm('¿Generar un secreto nuevo? La URL anterior dejará de funcionar.')">
                <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                <button class="w-full rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                    <?= $hasSecret ? '↻ Rotar secreto' : '＋ Generar secreto' ?>
                </button>
            </form>
            <p class="mt-3 text-xs text-slate-500">
                El secreto se guarda cifrado en la base (no se sube por deploy). Cada entorno tiene el suyo:
                generá la URL de producción corriendo esta pantalla en prod.
            </p>
        </div>
    </div>
</div>
