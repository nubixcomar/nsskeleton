<?php
/** @var string $active  tasks|master|history */
use Core\Url;
use Core\View;

$tabs = [
    'tasks'   => ['/admin/cron',         'Tareas'],
    'master'  => ['/admin/cron/master',  'Cron master'],
    'history' => ['/admin/cron/history', 'Historial'],
];
?>
<nav class="mb-6 flex gap-1 border-b border-slate-200">
    <?php foreach ($tabs as $key => [$path, $label]): ?>
        <?php $on = ($active ?? 'tasks') === $key; ?>
        <a href="<?= View::e(Url::to($path)) ?>"
           class="-mb-px border-b-2 px-4 py-2 text-sm font-medium <?= $on
               ? 'border-indigo-600 text-indigo-700'
               : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <?= View::e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>
