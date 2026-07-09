<?php

declare(strict_types=1);

use App\Services\Deployer;

/**
 * v1.17 #2 — Paquete de deploy con allowlist (hallazgo 1.5).
 * El modo 'production' sube SOLO runtime (system/ + app/), no fuente/docs/tests/installer.
 */

group('Deploy · paquete de producción (allowlist)');

it('paquete full incluye docs/tests/installer', function () {
    $full = Deployer::filesToDeploy(false);
    $hasDocs = false; $hasTests = false; $hasInstaller = false;
    foreach ($full as $f) {
        if (str_starts_with($f, 'docs/')) $hasDocs = true;
        if (str_starts_with($f, 'tests/')) $hasTests = true;
        if (str_starts_with($f, 'installer/')) $hasInstaller = true;
    }
    assertTrue($hasDocs && $hasTests && $hasInstaller, 'full trae todo el árbol');
});

it('paquete production NO incluye docs/tests/installer/agentic', function () {
    $prod = Deployer::filesToDeploy(true);
    foreach ($prod as $f) {
        assertTrue(!str_starts_with($f, 'docs/'), "no debe subir docs/: {$f}");
        assertTrue(!str_starts_with($f, 'tests/'), "no debe subir tests/: {$f}");
        assertTrue(!str_starts_with($f, 'installer/'), "no debe subir installer/: {$f}");
        assertTrue(!str_starts_with($f, 'agentic/'), "no debe subir agentic/: {$f}");
        assertTrue(!str_starts_with($f, 'app-agentic/'), "no debe subir app-agentic/: {$f}");
    }
});

it('paquete production SÍ incluye system/ (runtime)', function () {
    $prod = Deployer::filesToDeploy(true);
    $hasSystem = false;
    foreach ($prod as $f) {
        if (str_starts_with($f, 'system/')) { $hasSystem = true; break; }
    }
    assertTrue($hasSystem, 'production debe incluir system/');
});

it('production es un subconjunto estricto de full', function () {
    $full = Deployer::filesToDeploy(false);
    $prod = Deployer::filesToDeploy(true);
    assertTrue(count($prod) < count($full), 'production sube menos archivos que full');
    foreach ($prod as $f) {
        assertTrue(in_array($f, $full, true), "{$f} debe estar también en full");
    }
});

it('ningún paquete sube el .env', function () {
    foreach ([Deployer::filesToDeploy(false), Deployer::filesToDeploy(true)] as $set) {
        foreach ($set as $f) {
            assertTrue(basename($f) !== '.env', 'nunca subir .env');
        }
    }
});
