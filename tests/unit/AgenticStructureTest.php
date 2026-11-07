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

it('metodología Roadmap → Brief → Plan presente y enlazada', function () use ($root) {
    assertTrue(is_file($root . '/agentic/methodology/roadmap-brief-plan.md'));
    $rules = file_get_contents($root . '/agentic/rules/rules.md') ?: '';
    assertTrue(str_contains($rules, 'roadmap-brief-plan.md'), 'rules.md debe cargar la metodología RM→BR→PL');
    $agents = file_get_contents($root . '/AGENTS.md') ?: '';
    assertTrue(str_contains($agents, 'roadmap-brief-plan.md'), 'AGENTS.md debe apuntar a la metodología RM→BR→PL');
});

it('comandos /roadmap, /brief y /plan existen con front-matter', function () use ($root, $hasFrontMatter) {
    foreach (['roadmap', 'brief', 'plan'] as $cmd) {
        assertTrue($hasFrontMatter($root . "/agentic/commands/{$cmd}.md"), "comando {$cmd} debe existir con front-matter");
    }
});

it('skills y agentes del flujo RM→BR→PL existen', function () use ($root, $hasFrontMatter) {
    $agents = [
        'product'  => ['brief-discovery', 'creative-explorer', 'devils-advocate'],
        'planning' => ['roadmap-manager', 'plan-architect', 'plan-executor'],
    ];
    foreach ($agents as $cat => $names) {
        foreach ($names as $n) {
            assertTrue($hasFrontMatter($root . "/agentic/skills/{$n}/SKILL.md"), "skill {$n} debe existir");
            assertTrue($hasFrontMatter($root . "/agentic/agents/{$cat}/{$n}.md"), "agente {$cat}/{$n} debe existir");
        }
    }
});

it('scaffold de planning/ completo (README + índices + plantillas RM/BR/PL)', function () use ($root) {
    $base = $root . '/agentic/templates/planning';
    foreach (['README.md', 'roadmap/INDEX.md', 'briefs/INDEX.md', 'plans/INDEX.md',
              'roadmap/RM.template.md', 'briefs/BR.template.md', 'plans/PL.template.md'] as $f) {
        assertTrue(is_file("{$base}/{$f}"), "falta agentic/templates/planning/{$f}");
    }
    foreach (['roadmap/RM.template.md' => 'RM-', 'briefs/BR.template.md' => 'BR-', 'plans/PL.template.md' => 'PL-'] as $f => $prefix) {
        $c = file_get_contents("{$base}/{$f}") ?: '';
        assertTrue(str_starts_with($c, '---') && str_contains($c, "code: {$prefix}"), "{$f} debe tener front-matter con code: {$prefix}");
    }
});

it('planning/ es del proyecto (app_paths), no del core', function () use ($root) {
    $m = json_decode(file_get_contents($root . '/core-manifest.json') ?: '{}', true);
    assertTrue(in_array('planning/', $m['app_paths'] ?? [], true), 'planning/ debe estar en app_paths');
});
