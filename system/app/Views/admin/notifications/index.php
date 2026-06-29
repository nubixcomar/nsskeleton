<?php
/** @var array $user @var array $notifications @var string|null $success */
use Core\View;
use Core\Url;
use Core\Session;
?>
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900">Notificaciones</h1>
    <form method="post" action="<?= View::e(Url::to('/admin/notifications/read-all')) ?>">
        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
        <button class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Marcar todas como leídas</button>
    </form>
</div>


<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <ul class="divide-y divide-slate-100">
        <?php foreach ($notifications as $n): ?>
            <li class="flex items-start gap-3 px-5 py-4 <?= empty($n['read_at']) ? 'bg-indigo-50/40' : '' ?>">
                <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full <?= empty($n['read_at']) ? 'bg-indigo-500' : 'bg-slate-200' ?>"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-800"><?= View::e($n['title']) ?></p>
                    <?php if (!empty($n['body'])): ?>
                        <p class="text-sm text-slate-500"><?= View::e($n['body']) ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-xs text-slate-400"><?= View::e($n['created_at']) ?></p>
                </div>
                <div class="flex flex-shrink-0 items-center gap-2">
                    <?php if (!empty($n['url'])): ?>
                        <a href="<?= View::e(Url::to($n['url'])) ?>" class="text-xs text-indigo-600 hover:underline">Ver</a>
                    <?php endif; ?>
                    <?php if (empty($n['read_at'])): ?>
                        <form method="post" action="<?= View::e(Url::to('/admin/notifications/' . $n['id'] . '/read')) ?>">
                            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrf()) ?>">
                            <button class="text-xs text-slate-500 hover:underline">Marcar leída</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
        <?php if (empty($notifications)): ?>
            <li class="px-5 py-8 text-center text-sm text-slate-400">No tenés notificaciones.</li>
        <?php endif; ?>
    </ul>
</div>
