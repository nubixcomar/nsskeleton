<?php
/** @var array $user @var array $providers @var string $provider @var string $model @var bool $has_key
 *  @var array $history @var string|null $aiPrompt @var string|null $aiResponse @var string|null $success @var string|null $error */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Conector de IA</h1>
    <p class="text-sm text-slate-500">Configurá credenciales de OpenAI o Deepseek. La lógica/prompts los pone cada proyecto.</p>
</div>


<div class="grid gap-6 lg:grid-cols-2">
    <form method="post" action="<?= View::e(Url::to('/admin/ai')) ?>"
          class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <h2 class="text-sm font-semibold text-slate-700">Credenciales</h2>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Proveedor</label>
            <select name="provider" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                <?php foreach ($providers as $p): ?>
                    <option value="<?= View::e($p) ?>" <?= $provider === $p ? 'selected' : '' ?>><?= View::e(ucfirst($p)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Modelo <span class="text-xs text-slate-400">(vacío = default del proveedor)</span></label>
            <input type="text" name="model" value="<?= View::e($model) ?>" placeholder="gpt-4o-mini / deepseek-chat / claude-haiku-4-5-20251001"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
                API Key <?= $has_key ? '<span class="text-xs text-slate-400">(guardada — vacío para no cambiar)</span>' : '' ?>
            </label>
            <input type="password" name="api_key" autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">System prompt <span class="text-xs text-slate-400">(opcional)</span></label>
            <textarea name="system_prompt" rows="3" placeholder="Sos un asistente de…"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"><?= View::e($systemPrompt ?? '') ?></textarea>
            <p class="mt-1 text-xs text-slate-400">Se antepone a cada conversación con la IA.</p>
        </div>

        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Guardar</button>
    </form>

    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-2 text-sm font-semibold text-slate-700">Librería de prompts</h2>
        <p class="mb-3 text-xs text-slate-400">Definidos en <code>config/prompts.php</code>. Variables con <code>{{var}}</code>.</p>
        <ul class="space-y-2">
            <?php foreach ($prompts as $name => $tpl): ?>
                <li>
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"><?= View::e($name) ?></span>
                    <p class="mt-1 whitespace-pre-wrap text-xs text-slate-500"><?= View::e(mb_strimwidth($tpl, 0, 140, '…')) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="space-y-4">
        <form method="post" action="<?= View::e(Url::to('/admin/ai/test')) ?>"
              class="space-y-3 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
            <h2 class="text-sm font-semibold text-slate-700">Probar chat</h2>
            <textarea name="prompt" rows="3" required placeholder="Escribí un mensaje…"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500"><?= View::e($aiPrompt ?? '') ?></textarea>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Enviar</button>
        </form>

        <?php if (!empty($aiResponse)): ?>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="mb-2 text-sm font-semibold text-slate-700">Respuesta</h3>
                <div class="whitespace-pre-wrap text-sm text-slate-700"><?= nl2br(View::e($aiResponse)) ?></div>
            </div>
        <?php endif; ?>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
             x-data="{ out: '', busy: false, run() {
                 const p = this.$refs.sp.value.trim();
                 if (!p) return;
                 this.out = ''; this.busy = true;
                 const es = new EventSource('<?= View::e(Url::to('/admin/ai/stream')) ?>?prompt=' + encodeURIComponent(p));
                 es.onmessage = e => { this.out += e.data.replace(/\\n/g, '\n'); };
                 es.addEventListener('error', e => { if (e.data) this.out += '\n[error] ' + e.data; this.busy = false; es.close(); });
                 es.addEventListener('done', () => { this.busy = false; es.close(); });
             } }">
            <h3 class="mb-2 text-sm font-semibold text-slate-700">Probar con streaming (SSE)</h3>
            <textarea x-ref="sp" rows="2" placeholder="Escribí un mensaje…"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <button type="button" @click="run()" :disabled="busy"
                    class="mt-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                <span x-show="!busy">Enviar en streaming</span><span x-show="busy">Recibiendo…</span>
            </button>
            <pre x-show="out" x-text="out" class="mt-3 max-h-60 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-900 p-3 text-sm text-emerald-300"></pre>
        </div>
    </div>
</div>

<?php if (!empty($history)): ?>
    <h2 class="mb-2 mt-8 text-sm font-semibold text-slate-700">Llamadas recientes</h2>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-xs">
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td class="px-4 py-2 text-slate-500"><?= View::e($h['created_at']) ?></td>
                        <td class="px-4 py-2 font-medium text-slate-700"><?= View::e($h['provider']) ?> · <?= View::e($h['model'] ?? '') ?></td>
                        <td class="px-4 py-2 <?= $h['status'] === 'ok' ? 'text-emerald-600' : 'text-red-600' ?>"><?= View::e($h['status']) ?></td>
                        <td class="px-4 py-2 text-slate-400"><?= (int) $h['prompt_chars'] ?>→<?= (int) $h['response_chars'] ?> chars</td>
                        <td class="px-4 py-2 text-slate-400"><?= View::e($h['error'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
