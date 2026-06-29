<?php
/** @var bool $debug @var string $detail */
use Core\View;
use Core\Url;

$debug = $debug ?? false;
$detail = $detail ?? '';
?>
<div class="w-full max-w-2xl text-center">
    <p class="text-6xl font-extrabold text-red-600">500</p>
    <h1 class="mt-3 text-2xl font-bold text-slate-900">Error interno</h1>
    <p class="mt-2 text-slate-500">Ocurrió un problema procesando tu pedido. Intentá de nuevo más tarde.</p>
    <a href="<?= View::e(Url::to('/')) ?>" class="mt-6 inline-block rounded-lg bg-slate-800 px-5 py-2.5 font-medium text-white hover:bg-slate-900">Volver al inicio</a>

    <?php if ($debug && $detail !== ''): ?>
        <pre class="mt-6 max-h-80 overflow-auto rounded-lg bg-slate-900 p-4 text-left text-xs text-amber-300"><?= View::e($detail) ?></pre>
    <?php endif; ?>
</div>
