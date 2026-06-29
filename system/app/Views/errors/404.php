<?php
/** @var string $path */
use Core\View;
use Core\Url;
?>
<div class="text-center">
    <p class="text-6xl font-extrabold text-indigo-600">404</p>
    <h1 class="mt-3 text-2xl font-bold text-slate-900">Página no encontrada</h1>
    <p class="mt-2 text-slate-500">No existe la ruta <code class="rounded bg-slate-100 px-1"><?= View::e($path ?? '') ?></code>.</p>
    <a href="<?= View::e(Url::to('/')) ?>" class="mt-6 inline-block rounded-lg bg-indigo-600 px-5 py-2.5 font-medium text-white hover:bg-indigo-700">Volver al inicio</a>
</div>
