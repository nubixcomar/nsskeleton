<?php

declare(strict_types=1);

/**
 * Verifica que la capa agéntica tenga las piezas esperadas (estructura + front-matter).
 * No requiere DB. Usa PROJECT_PATH (definido en bootstrap).
 */

group('Estructura agéntica');

$root = PROJECT_PATH;

$hasFrontMatter = static function (string $path): bool {
    if (!is_file($path)) {
        return false;
    }
    $c = file_get_contents($path) ?: '';
    return str_starts_with(ltrim($c), '---') && str_contains($c, 'name:');
};

it('AGENTS.md (arranque agnóstico) existe', function () use ($root) {
    assertTrue(is_file($root . '/AGENTS.md'));
});

it('comandos clave existen con front-matter', function () use ($root, $hasFrontMatter) {
    foreach (['bug', 'fix', 'audit', 'test', 'nuevo-modulo', 'sprint', 'release'] as $cmd) {
        assertTrue($hasFrontMatter($root . "/agentic/commands/{$cmd}.md"), "comando {$cmd} debe existir con front-matter");
    }
});

it('skills de gestión existen', function () use ($root, $hasFrontMatter) {
    assertTrue($hasFrontMatter($root . '/agentic/skills/sprint-manager/SKILL.md'));
    assertTrue($hasFrontMatter($root . '/agentic/skills/release-manager/SKILL.md'));
    assertTrue($hasFrontMatter($root . '/agentic/skills/module-generator/SKILL.md'));
});

it('agentes pm existen', function () use ($root, $hasFrontMatter) {
    assertTrue($hasFrontMatter($root . '/agentic/agents/pm/sprint-manager.md'));
    assertTrue($hasFrontMatter($root . '/agentic/agents/pm/release-manager.md'));
});

it('plantillas de sprint y release existen', function () use ($root) {
    assertTrue(is_file($root . '/agentic/templates/sprint.template.md'));
    assertTrue(is_file($root . '/agentic/templates/release-notes.template.md'));
});

it('metodología completa presente', function () use ($root) {
    foreach (['logging', 'bug-tracking', 'sprints'] as $m) {
        assertTrue(is_file($root . "/agentic/methodology/{$m}.md"));
    }
});
