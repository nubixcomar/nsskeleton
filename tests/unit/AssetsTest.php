<?php

declare(strict_types=1);

use Core\Assets;
use Core\Security;

group('Assets locales');

it('useLocal es true cuando existe el CSS y el modo no es cdn', function () {
    unset($_ENV['ASSETS_MODE']);
    assertTrue(Assets::useLocal());
});

it('ASSETS_MODE=cdn fuerza el uso de CDN', function () {
    $_ENV['ASSETS_MODE'] = 'cdn';
    $r = Assets::useLocal();
    unset($_ENV['ASSETS_MODE']);
    assertFalse($r);
});

it('las rutas de assets apuntan a /assets/...', function () {
    assertContains('assets/css/app.css', Assets::css());
    assertContains('alpine', Assets::alpine());
    assertContains('chart', Assets::chart());
});

it('los assets vendorizados existen', function () {
    assertTrue(is_file(BASE_PATH . '/public/assets/css/app.css'));
    assertTrue(is_file(BASE_PATH . '/public/assets/js/alpine.min.js'));
    assertTrue(is_file(BASE_PATH . '/public/assets/js/chart.umd.min.js'));
});

it('head() local emite <link> al CSS; cdn emite el script de Tailwind', function () {
    unset($_ENV['ASSETS_MODE']);
    assertContains('assets/css/app.css', Assets::head());
    $_ENV['ASSETS_MODE'] = 'cdn';
    assertContains('cdn.tailwindcss.com', Assets::head());
    unset($_ENV['ASSETS_MODE']);
});

it('CSP local sin orígenes externos; CSP cdn los incluye', function () {
    unset($_ENV['ASSETS_MODE']);
    $local = Security::headers()['Content-Security-Policy'];
    assertTrue(!str_contains($local, 'cdn.jsdelivr.net'), 'CSP local no debe permitir jsdelivr');

    $_ENV['ASSETS_MODE'] = 'cdn';
    $cdn = Security::headers()['Content-Security-Policy'];
    unset($_ENV['ASSETS_MODE']);
    assertContains('cdn.jsdelivr.net', $cdn);
});
