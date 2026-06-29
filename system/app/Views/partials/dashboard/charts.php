<?php
/** @var array $charts */
use Core\View;
?>
<h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Métricas</h2>
<div class="mb-8 grid gap-4 lg:grid-cols-3">
    <?php foreach ($charts as $chart): ?>
        <?= View::partial('partials/chart', ['chart' => $chart]) ?>
    <?php endforeach; ?>
</div>
