<?php
use Core\View;
use Core\Url;
?>
<div class="text-center">
    <p class="text-6xl font-extrabold text-amber-500">403</p>
    <h1 class="mt-3 text-2xl font-bold text-slate-900">Acceso denegado</h1>
    <p class="mt-2 text-slate-500">No tenés permiso para acceder a esta sección.</p>
    <a href="<?= View::e(Url::to('/admin')) ?>" class="mt-6 inline-block rounded-lg bg-indigo-600 px-5 py-2.5 font-medium text-white hover:bg-indigo-700">Ir al dashboard</a>
</div>
