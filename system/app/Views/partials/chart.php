<?php
/**
 * Partial reutilizable de gráfico (Chart.js).
 * @var array $chart  Config con: id, type, title, labels, datasets, options.
 */
use Core\View;

$config = [
    'type'    => $chart['type'],
    'data'    => ['labels' => $chart['labels'], 'datasets' => $chart['datasets']],
    'options' => $chart['options'] ?? ['responsive' => true],
];
$id = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $chart['id']);
?>
<div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h3 class="mb-3 text-sm font-semibold text-slate-700"><?= View::e($chart['title'] ?? '') ?></h3>
    <canvas id="<?= $id ?>" height="160"></canvas>
</div>
<script>
(function () {
    const el = document.getElementById('<?= $id ?>');
    if (el && window.Chart) {
        new Chart(el, <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
    }
})();
</script>
