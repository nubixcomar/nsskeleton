<?php
/** @var array $user @var list<string> $entries @var int $lines @var ?string $path */
use Core\Url;
use Core\View;
?>
<div class="mb-6 flex items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Logs de errores</h1>
        <p class="text-sm text-slate-500">
            Últimas líneas de <code><?= View::e(str_replace('\\', '/', (string) $path)) ?></code>.
            Se registra siempre, incluso con <code>APP_DEBUG=false</code>.
        </p>
    </div>
    <form method="get" action="<?= View::e(Url::to('/admin/logs')) ?>" class="flex items-center gap-2">
        <label class="text-xs text-slate-500" for="lines">Líneas</label>
        <select id="lines" name="lines" onchange="this.form.submit()"
                class="rounded-lg border border-slate-300 px-2 py-1 text-sm">
            <?php foreach ([50, 100, 200, 500, 1000] as $n): ?>
                <option value="<?= $n ?>" <?= $n === $lines ? 'selected' : '' ?>><?= $n ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($entries === []): ?>
    <div class="rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">No hay errores registrados. 🎉</p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl bg-slate-900 p-4 shadow-sm ring-1 ring-slate-800">
        <pre class="text-xs leading-relaxed text-slate-100"><?php
        foreach ($entries as $line) {
            $cls = 'text-slate-300';
            if (str_contains($line, '[ERROR]')) {
                $cls = 'text-red-400';
            } elseif (str_contains($line, '[WARN]')) {
                $cls = 'text-amber-300';
            } elseif (str_contains($line, '[INFO]')) {
                $cls = 'text-sky-300';
            }
            echo '<span class="' . $cls . '">' . View::e($line) . "</span>\n";
        }
        ?></pre>
    </div>
<?php endif; ?>
