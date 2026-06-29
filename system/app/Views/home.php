<?php
/** @var string $appName @var string $env */
use Core\View;
?>
<main class="flex min-h-screen items-center justify-center p-6">
    <div class="w-full max-w-xl rounded-2xl bg-white p-8 shadow-lg ring-1 ring-slate-200"
         x-data="{ ok: null }">
        <div class="mb-6 flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-xl font-bold text-white">
                ns
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><?= View::e($appName) ?></h1>
                <p class="text-sm text-slate-500">Sistema base sobre nsSkeleton · entorno <?= View::e($env) ?></p>
            </div>
        </div>

        <p class="mb-6 text-slate-600">
            El núcleo MVC está funcionando. Desde acá se construyen el login de
            administrador, el cron, los emails, backups, gráficos, el file manager y el
            conector de IA.
        </p>

        <button
            class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white transition hover:bg-indigo-700"
            @click="ok = await (await fetch('health')).json()">
            Probar /health
        </button>

        <template x-if="ok">
            <pre class="mt-4 overflow-auto rounded-lg bg-slate-900 p-4 text-sm text-emerald-300"
                 x-text="JSON.stringify(ok, null, 2)"></pre>
        </template>
    </div>
</main>
