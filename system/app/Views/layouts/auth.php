<?php
/** Layout minimal centrado para pantallas de autenticación. */
use Core\View;
use Core\Env;

$title = ($title ?? 'Ingresar') . ' · ' . \App\Services\AppSettings::name();
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title) ?></title>
    <?= \Core\Assets::head(withAlpine: false) ?>
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
    <main class="flex min-h-screen items-center justify-center p-6">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
