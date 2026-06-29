<?php
/**
 * Layout base del backend.
 * Variables esperadas: $title (opcional), $content (inyectado por View::render).
 *
 * Nota: usa Tailwind y Alpine vía CDN para arrancar sin build. En producción,
 * reemplazar por el binario standalone de Tailwind (ver docs/stack.md).
 */
use Core\View;

$title = $title ?? (\Core\Env::get('APP_NAME', 'nsSkeleton'));
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title) ?></title>
    <?= \Core\Assets::head() ?>
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
    <div class="min-h-full">
        <?= $content ?? '' ?>
    </div>
</body>
</html>
