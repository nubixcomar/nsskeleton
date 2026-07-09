<?php

declare(strict_types=1);

use Core\Config;
use Core\View;

/**
 * v1.17 #6 — Visor de logs de errores en /admin (hallazgo 1.8).
 */

group('Visor de logs (/admin/logs)');

it('el permiso logs.view está en el catálogo y lo tiene admin', function () {
    $perms = Config::load('permissions');
    assertTrue(isset($perms['catalog']['logs.view']), 'logs.view en el catálogo');
    assertTrue(in_array('logs.view', $perms['roles']['admin'] ?? [], true), 'admin tiene logs.view');
});

it('la vista admin/logs/index existe', function () {
    assertTrue(View::exists('admin/logs/index'));
});

it('la vista renderiza las entradas con coloreo por nivel', function () {
    $html = View::partial('admin/logs/index', [
        'user'    => ['name' => 'x'],
        'entries' => [
            '[2026-07-09 10:00:00] [ERROR] RuntimeException: boom',
            '[2026-07-09 10:00:01] [INFO] arranque ok',
        ],
        'lines'   => 200,
        'path'    => 'C:/x/system/storage/logs/error.log',
    ]);
    assertContains('RuntimeException: boom', $html);
    assertContains('text-red-400', $html);   // ERROR en rojo
    assertContains('text-sky-300', $html);    // INFO en celeste
});

it('estado vacío muestra el mensaje "No hay errores"', function () {
    $html = View::partial('admin/logs/index', [
        'user'    => ['name' => 'x'],
        'entries' => [],
        'lines'   => 200,
        'path'    => 'C:/x/error.log',
    ]);
    assertContains('No hay errores', $html);
});

it('el controller LogViewerController existe y es del admin', function () {
    assertTrue(class_exists('App\\Controllers\\Admin\\LogViewerController'));
    $parents = class_parents('App\\Controllers\\Admin\\LogViewerController') ?: [];
    assertTrue(in_array('App\\Controllers\\Admin\\AdminController', $parents, true), 'extiende AdminController (guard)');
});
