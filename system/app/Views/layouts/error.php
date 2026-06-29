<?php
/** Layout minimal para páginas de error (sin dependencias de sesión/DB). */
use Core\View;

$title = $title ?? 'Error';
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title) ?></title>
    <?= \Core\Assets::head(withAlpine: false) ?>
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased">
    <main class="flex min-h-screen items-center justify-center p-6">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
