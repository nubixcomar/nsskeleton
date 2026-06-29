<?php
/** Plantilla de notificación. Variables: $title, $body, $actionUrl?, $actionText? */
use Core\View;

$actionUrl = $actionUrl ?? '';
$actionText = $actionText ?? '';
?>
<h1 style="margin:0 0 12px;font-size:20px;color:#0f172a;"><?= View::e($title ?? '') ?></h1>
<div style="font-size:14px;line-height:1.6;color:#334155;"><?= $body ?? '' ?></div>
<?php if ($actionUrl !== ''): ?>
    <p style="margin:24px 0 0;">
        <a href="<?= View::e($actionUrl) ?>"
           style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;font-size:14px;">
            <?= View::e($actionText !== '' ? $actionText : 'Abrir') ?>
        </a>
    </p>
<?php endif; ?>
