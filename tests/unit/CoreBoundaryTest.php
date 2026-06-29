<?php

declare(strict_types=1);

/**
 * Frontera core/app (Fase 1) y split agéntico core/proyecto (Fase 2).
 * No requiere DB. Usa PROJECT_PATH (definido en bootstrap).
 */

group('Frontera core/app + split agéntico');

$root = PROJECT_PATH;

// ── Fase 1: manifiesto + lock ────────────────────────────────────────────────

it('core-manifest.json existe y tiene core_paths/app_paths/exclude', function () use ($root) {
    $m = json_decode((string) file_get_contents($root . '/core-manifest.json'), true);
    assertTrue(is_array($m));
    foreach (['core_paths', 'app_paths', 'exclude', 'core_version'] as $k) {
        assertTrue(array_key_exists($k, $m), "falta {$k} en core-manifest.json");
    }
});

it('core-lock.json existe y lista archivos core con checksum', function () use ($root) {
    $l = json_decode((string) file_get_contents($root . '/core-lock.json'), true);
    assertTrue(is_array($l) && isset($l['files']) && is_array($l['files']));
    assertTrue($l['count'] === count($l['files']));
    assertTrue($l['count'] > 100, 'el lock debería tener cientos de archivos core');
});

it('el showcase Clientes es APP (no está en el lock)', function () use ($root) {
    $l = json_decode((string) file_get_contents($root . '/core-lock.json'), true);
    $files = $l['files'] ?? [];
    foreach ([
        'system/app/Models/Cliente.php',
        'system/app/Controllers/Admin/ClienteController.php',
        'system/config/routes/clientes.php',
    ] as $appFile) {
        assertFalse(array_key_exists($appFile, $files), "{$appFile} no debería estar en el lock (es app)");
    }
});

it('archivos core SÍ están en el lock', function () use ($root) {
    $l = json_decode((string) file_get_contents($root . '/core-lock.json'), true);
    $files = $l['files'] ?? [];
    foreach (['AGENTS.md', 'agentic/rules/core-rules.md', 'system/app/Core/autoload.php'] as $coreFile) {
        assertTrue(array_key_exists($coreFile, $files), "{$coreFile} debería estar en el lock (es core)");
    }
});

it('app-agentic/ NO está en el lock (es del proyecto)', function () use ($root) {
    $l = json_decode((string) file_get_contents($root . '/core-lock.json'), true);
    foreach (array_keys($l['files'] ?? []) as $f) {
        assertFalse(str_starts_with($f, 'app-agentic/'), "app-agentic no debería estar en el lock: {$f}");
    }
});

it('el lock no tiene drift contra el árbol (--check)', function () use ($root) {
    if (!function_exists('exec')) {
        return 'skip';
    }
    $php = PHP_BINARY;
    $script = $root . '/system/console/core-manifest.php';
    exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --check 2>&1', $out, $code);
    assertEquals(0, $code, 'core-lock.json tiene drift; regenerar con core-manifest.php. ' . implode(' ', $out));
});

// ── Fase 2: split agéntico ───────────────────────────────────────────────────

it('core-rules.md existe y project-rules.md ya NO', function () use ($root) {
    assertTrue(is_file($root . '/agentic/rules/core-rules.md'));
    assertFalse(is_file($root . '/agentic/rules/project-rules.md'));
});

it('app-agentic/ tiene la estructura del proyecto', function () use ($root) {
    assertTrue(is_file($root . '/app-agentic/README.md'));
    assertTrue(is_file($root . '/app-agentic/rules/app-rules.md'));
    foreach (['agents', 'skills', 'knowledge', 'templates', 'modules'] as $d) {
        assertTrue(is_dir($root . '/app-agentic/' . $d), "falta app-agentic/{$d}");
    }
});

it('rules.md carga core→app con precedencia app>core', function () use ($root) {
    $c = (string) file_get_contents($root . '/agentic/rules/rules.md');
    assertContains('core-rules.md', $c);
    assertContains('app-rules.md', $c);
    assertContains('app > core', $c);
});

it('no quedan referencias colgadas a project-rules.md', function () use ($root) {
    foreach ([
        '/AGENTS.md',
        '/agentic/rules/rules.md',
        '/agentic/skills/dev-backend/SKILL.md',
        '/agentic/skills/architect/SKILL.md',
        '/agentic/skills/installer/SKILL.md',
    ] as $f) {
        $c = (string) file_get_contents($root . $f);
        assertFalse(str_contains($c, 'project-rules.md'), "{$f} aún referencia project-rules.md");
    }
});

it('Fase 5: comando, skill, agente y doc del actualizador existen', function () use ($root) {
    $fm = static function (string $p): bool {
        $c = is_file($p) ? (file_get_contents($p) ?: '') : '';
        return str_starts_with(ltrim($c), '---') && str_contains($c, 'name:');
    };
    assertTrue($fm($root . '/agentic/commands/actualizar-core.md'), 'falta el comando');
    assertTrue($fm($root . '/agentic/skills/core-updater/SKILL.md'), 'falta el skill');
    assertTrue($fm($root . '/agentic/agents/pm/core-updater.md'), 'falta el agente');
    assertTrue(is_file($root . '/docs/CORE-UPDATE.md'), 'falta docs/CORE-UPDATE.md');
});
