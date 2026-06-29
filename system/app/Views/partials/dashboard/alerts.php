<?php
/** @var array $alerts */
use Core\View;
use Core\Url;

$alertStyle = [
    'danger' => 'bg-red-50 text-red-800 ring-red-200', 'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
    'info' => 'bg-sky-50 text-sky-800 ring-sky-200',
];
if (empty($alerts)) {
    return;
}
?>
<div class="mb-6 space-y-2">
    <?php foreach ($alerts as $a): ?>
        <a href="<?= View::e(Url::to($a['url'] ?? '/admin')) ?>"
           class="flex items-start gap-3 rounded-xl px-4 py-3 ring-1 <?= $alertStyle[$a['severity'] ?? 'info'] ?? $alertStyle['info'] ?> hover:opacity-90">
            <span class="text-lg leading-none"><?= View::e($a['icon'] ?? '🔔') ?></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold"><?= View::e($a['title'] ?? '') ?></span>
                <?php if (!empty($a['detail'])): ?><span class="block text-xs opacity-80"><?= View::e($a['detail']) ?></span><?php endif; ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>
