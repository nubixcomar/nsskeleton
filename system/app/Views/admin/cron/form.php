<?php
/** @var array $user @var array|null $editing @var array $runs @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;

$isEdit = $editing !== null;
$action = $isEdit ? Url::to('/admin/cron/' . $editing['id']) : Url::to('/admin/cron');
$runs = $runs ?? [];
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= $isEdit ? 'Editar tarea' : 'Nueva tarea' ?></h1>
    <a href="<?= View::e(Url::to('/admin/cron')) ?>" class="text-sm text-indigo-600 hover:underline">← Volver</a>
</div>


<div class="grid gap-6 lg:grid-cols-3">
    <form method="post" action="<?= View::e($action) ?>"
          class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
            <input type="text" name="name" required value="<?= View::e($editing['name'] ?? '') ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Comando</label>
            <input type="text" name="command" required value="<?= View::e($editing['command'] ?? '') ?>"
                   placeholder="php system/backup/run.php  ó  job:demo:ping" list="cronjobs"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <datalist id="cronjobs">
                <?php foreach (\App\Services\Jobs::names() as $job): ?>
                    <option value="job:<?= View::e($job) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <p class="mt-1 text-xs text-slate-400">Comando de shell (capturamos salida + exit code) o un job interno: <code>job:&lt;nombre&gt;</code>.</p>
        </div>
        <div x-data="cronBuilder('<?= View::e($editing['schedule'] ?? '*/5 * * * *') ?>')">
            <label class="mb-1 block text-sm font-medium text-slate-700">Horario</label>
            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-slate-50 p-3 ring-1 ring-slate-200">
                <select x-model="type" @change="build()" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="minutes">Cada N minutos</option>
                    <option value="hourly">Cada hora</option>
                    <option value="daily">Todos los días</option>
                    <option value="weekly">Semanal</option>
                    <option value="monthly">Mensual</option>
                </select>
                <template x-if="type === 'minutes'">
                    <div class="flex items-center gap-1 text-sm">cada <input type="number" min="1" max="59" x-model.number="every" @input="build()" class="w-16 rounded-lg border border-slate-300 px-2 py-1.5"> min</div>
                </template>
                <template x-if="type === 'weekly'">
                    <select x-model.number="dow" @change="build()" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="1">Lunes</option><option value="2">Martes</option><option value="3">Miércoles</option><option value="4">Jueves</option><option value="5">Viernes</option><option value="6">Sábado</option><option value="0">Domingo</option>
                    </select>
                </template>
                <template x-if="type === 'monthly'">
                    <div class="flex items-center gap-1 text-sm">día <input type="number" min="1" max="31" x-model.number="dom" @input="build()" class="w-16 rounded-lg border border-slate-300 px-2 py-1.5"></div>
                </template>
                <template x-if="['hourly','daily','weekly','monthly'].includes(type)">
                    <div class="flex items-center gap-1 text-sm">
                        <template x-if="type !== 'hourly'"><span>a las</span></template>
                        <template x-if="type !== 'hourly'"><input type="number" min="0" max="23" x-model.number="hour" @input="build()" class="w-16 rounded-lg border border-slate-300 px-2 py-1.5"></template>
                        <template x-if="type === 'hourly'"><span>minuto</span></template>
                        <span x-show="type !== 'hourly'">:</span>
                        <input type="number" min="0" max="59" x-model.number="minute" @input="build()" class="w-16 rounded-lg border border-slate-300 px-2 py-1.5">
                    </div>
                </template>
            </div>
            <label class="mb-1 mt-3 block text-sm font-medium text-slate-700">Expresión cron (5 campos)</label>
            <input type="text" name="schedule" required x-model="expr"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-400">Editable para avanzados. <span class="font-medium text-indigo-600" x-text="'→ ' + describe(expr)"></span></p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Prioridad</label>
                <input type="number" name="priority" value="<?= (int) ($editing['priority'] ?? 0) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-slate-400">Mayor se ejecuta primero.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Timeout (seg)</label>
                <input type="number" name="timeout" min="0" value="<?= (int) ($editing['timeout'] ?? 0) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-slate-400">0 = sin límite.</p>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="active" value="1" <?= (!$isEdit || (int) ($editing['active'] ?? 1) === 1) ? 'checked' : '' ?>
                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            Activa
        </label>

        <div class="pt-2">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
                <?= $isEdit ? 'Guardar cambios' : 'Crear' ?>
            </button>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Últimas ejecuciones</h2>
            <?php if (empty($runs)): ?>
                <p class="text-sm text-slate-400">Sin ejecuciones todavía.</p>
            <?php else: ?>
                <ul class="space-y-2 text-xs">
                    <?php foreach ($runs as $r): ?>
                        <li class="flex items-center justify-between">
                            <span class="text-slate-500"><?= View::e($r['started_at']) ?></span>
                            <span class="<?= $r['status'] === 'success' ? 'text-emerald-600' : 'text-red-600' ?>">
                                <?= View::e($r['status']) ?> (<?= (int) $r['exit_code'] ?>)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($editing['last_output'])): ?>
                <h3 class="mt-4 mb-1 text-xs font-semibold text-slate-600">Última salida</h3>
                <pre class="max-h-48 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-emerald-300"><?= View::e($editing['last_output']) ?></pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function cronBuilder(initial) {
    return {
        type: 'minutes', every: 5, minute: 0, hour: 0, dow: 1, dom: 1, expr: initial,
        init() { this.parse(initial); },
        parse(e) {
            const p = (e || '').trim().split(/\s+/);
            if (p.length !== 5) return;
            const [m, h, dom, mon, dowF] = p;
            const num = v => /^\d+$/.test(v);
            if (/^\*\/(\d+)$/.test(m) && h === '*' && dom === '*' && dowF === '*') { this.type = 'minutes'; this.every = parseInt(m.slice(2)); }
            else if (num(m) && h === '*' && dom === '*' && dowF === '*') { this.type = 'hourly'; this.minute = +m; }
            else if (num(m) && num(h) && dom === '*' && mon === '*' && num(dowF)) { this.type = 'weekly'; this.minute = +m; this.hour = +h; this.dow = +dowF; }
            else if (num(m) && num(h) && num(dom) && mon === '*' && dowF === '*') { this.type = 'monthly'; this.minute = +m; this.hour = +h; this.dom = +dom; }
            else if (num(m) && num(h) && dom === '*' && mon === '*' && dowF === '*') { this.type = 'daily'; this.minute = +m; this.hour = +h; }
        },
        build() {
            const m = Math.max(0, Math.min(59, this.minute || 0));
            const h = Math.max(0, Math.min(23, this.hour || 0));
            if (this.type === 'minutes') this.expr = '*/' + Math.max(1, Math.min(59, this.every || 5)) + ' * * * *';
            else if (this.type === 'hourly') this.expr = m + ' * * * *';
            else if (this.type === 'daily') this.expr = m + ' ' + h + ' * * *';
            else if (this.type === 'weekly') this.expr = m + ' ' + h + ' * * ' + (this.dow ?? 1);
            else if (this.type === 'monthly') this.expr = m + ' ' + h + ' ' + Math.max(1, Math.min(31, this.dom || 1)) + ' * *';
        },
        describe(e) {
            const p = (e || '').trim().split(/\s+/);
            if (p.length !== 5) return e;
            const [m, h, dom, mon, dowF] = p, num = v => /^\d+$/.test(v);
            const days = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
            const t = () => String(num(h) ? h : 0).padStart(2,'0') + ':' + String(num(m) ? m : 0).padStart(2,'0');
            if (m === '*' && h === '*' && dom === '*' && mon === '*' && dowF === '*') return 'Cada minuto';
            const mm = m.match(/^\*\/(\d+)$/);
            if (mm && h === '*' && dom === '*' && dowF === '*') return 'Cada ' + mm[1] + ' minutos';
            if (num(m) && h === '*' && dom === '*' && dowF === '*') return 'Cada hora al minuto ' + m;
            if (num(m) && num(h) && dom === '*' && mon === '*' && dowF === '*') return 'Todos los días a las ' + t();
            if (num(m) && num(h) && dom === '*' && mon === '*' && num(dowF)) return 'Los ' + (days[+dowF] || dowF) + ' a las ' + t();
            if (num(m) && num(h) && num(dom) && mon === '*' && dowF === '*') return 'El día ' + dom + ' de cada mes a las ' + t();
            return e;
        }
    };
}
</script>
