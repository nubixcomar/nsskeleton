<?php

declare(strict_types=1);

use Core\Assets;

group('Tema (dark mode)');

it('la hoja de tema existe', function () {
    assertTrue(is_file(BASE_PATH . '/public/assets/css/theme.css'));
});

it('la hoja de tema define overrides .dark', function () {
    $css = file_get_contents(BASE_PATH . '/public/assets/css/theme.css') ?: '';
    assertContains('.dark .bg-white', $css);
    assertContains('.dark body', $css);
});

it('Assets::head incluye theme.css y el init anti-flash', function () {
    unset($_ENV['ASSETS_MODE']);
    $head = Assets::head();
    assertContains('assets/css/theme.css', $head);
    assertContains('classList.add("dark")', $head);
});

it('el init lee la preferencia de localStorage', function () {
    $head = Assets::head();
    assertContains("localStorage.getItem(\"theme\")", $head);
});
