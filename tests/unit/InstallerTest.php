<?php

declare(strict_types=1);

use App\Services\Installer;

group('Installer (parte mecánica)');

it('buildEnv aplica las respuestas de base de datos', function () {
    $env = Installer::buildEnv([
        'project_name' => 'Mi Empresa',
        'db_host' => '127.0.0.1',
        'db_port' => '3307',
        'db_name' => 'midb',
        'db_user' => 'root',
        'db_pass' => '',
    ]);
    assertContains('DB_PORT=3307', $env);
    assertContains('DB_NAME=midb', $env);
    assertContains('APP_NAME="Mi Empresa"', $env); // con espacio → entre comillas
});

it('buildEnv no rompe si faltan respuestas (usa el template)', function () {
    $env = Installer::buildEnv([]);
    assertContains('DB_HOST=', $env);
    assertContains('APP_NAME=', $env);
});

it('stackDoc refleja el stack elegido', function () {
    $md = Installer::stackDoc([
        'language' => 'php-mvc',
        'database' => 'mysql',
        'frontend' => 'tailwind-alpine',
        'ai_target' => 'claude-code',
    ]);
    assertContains('php-mvc', $md);
    assertContains('mysql', $md);
    assertContains('claude-code', $md);
});

it('summary lista las acciones', function () {
    $s = Installer::summary(['adapter' => 'php-mvc', 'install_system_base' => true]);
    assertTrue(count($s) >= 4);
});
