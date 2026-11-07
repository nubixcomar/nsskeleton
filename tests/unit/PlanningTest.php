<?php

declare(strict_types=1);

use App\Services\Planning;

/**
 * Planning (RM → BR → PL): parser de front-matter, fases, preguntas bloqueantes, init,
 * next, lint y reindex sobre un proyecto temporal. Sin DB.
 */

group('Planning (Roadmap → Brief → Plan)');

$mkProject = static function (): string {
    $root = sys_get_temp_dir() . '/nss-planning-' . bin2hex(random_bytes(4));
    $src = PROJECT_PATH . '/agentic/templates/planning';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', $src)) + 1);
        $dst = "{$root}/agentic/templates/planning/{$rel}";
        if (!is_dir(dirname($dst))) {
            mkdir(dirname($dst), 0775, true);
        }
        copy($f->getPathname(), $dst);
    }
    return $root;
};
$put = static function (string $root, string $rel, string $content): void {
    $f = "{$root}/planning/{$rel}";
    if (!is_dir(dirname($f))) {
        mkdir(dirname($f), 0775, true);
    }
    file_put_contents($f, $content);
};
$rmrf = static function (string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
};

// Fixture coherente: RM-001 ← BR-001 ← PL-001 (en ejecución, 1/2 fases).
$seed = static function (string $root) use ($put): void {
    $put($root, 'roadmap/RM-001-turnos.md', "---\ncode: RM-001\ntitle: Turnos online\nstatus: 🟡 En curso\npriority: P2\nstart_tentative: 2026-11-01\ndue_tentative: 2026-12-15\nbriefs: [BR-001]\nplans: [PL-001]\nupdated: 2026-11-07\n---\n# RM-001\n");
    $put($root, 'briefs/BR-001-reprogramar.md', "---\ncode: BR-001\ntitle: Reprogramar turnos\nstatus: 🧭 Planificado\nrm: RM-001   # iniciativa\nplans: [PL-001]\nupdated: 2026-11-07\n---\n# BR-001\n\n## 17. Preguntas abiertas\n| # | Pregunta | ¿Bloqueante? | Respuesta | Fecha |\n|---|----------|--------------|-----------|-------|\n| 1 | ¿Hasta cuándo? | sí | 24 h antes | 2026-11-07 |\n| 2 | ¿Cobra multa? | no | | |\n\n## 18. Registro\n");
    $put($root, 'plans/PL-001-reprogramacion.md', "---\ncode: PL-001\ntitle: Reprogramación self-service\nstatus: ⚙️ En ejecución\nbrief: BR-001\nrm: RM-001\norigin: brief\nexception: —\nsize: M\nupdated: 2026-11-07\n---\n# PL-001\n\n### F1 — Migración · estado: ✅ hecha\n### F2 — Servicio · estado: 📋 pendiente\n");
};

it('parseFrontMatter: escalares, listas, comentarios y nulos', function () {
    $m = Planning::parseFrontMatter("---\ncode: PL-003\nrm: —   # opcional\nplans: [PL-001, PL-002]\nbriefs: []\ntitle: \"Hola: mundo\"\n---\nbody");
    assertEquals('PL-003', $m['code']);
    assertNull($m['rm']);
    assertEquals(['PL-001', 'PL-002'], $m['plans']);
    assertEquals([], $m['briefs']);
    assertEquals('Hola: mundo', $m['title']);
    assertNull(Planning::parseFrontMatter("# sin front-matter\n"));
});

it('normalizeStatus acepta con o sin emoji y rechaza estados de otro tipo', function () {
    assertEquals('🟡 En curso', Planning::normalizeStatus('rm', 'En curso'));
    assertEquals('⚙️ En ejecución', Planning::normalizeStatus('pl', '⚙️ en ejecución'));
    assertNull(Planning::normalizeStatus('rm', 'Aprobado'));
});

it('parsePhases cuenta hechas/omitidas/bloqueadas/pendientes', function () {
    $ph = Planning::parsePhases("### F1 — a · estado: ✅ hecha\n### F2 — b · estado: ⏭️ omitida (motivo)\n### F3 — c · estado: ⛔ bloqueada\n### F4 — d · estado: 📋 pendiente\n");
    assertEquals(4, $ph['total']);
    assertEquals(2, $ph['done']);
    assertEquals(1, $ph['blocked']);
    assertEquals(1, $ph['pending']);
});

it('countBlockingQuestions cuenta solo bloqueantes sin respuesta', function () {
    $c = "## 17. Preguntas abiertas\n| # | Pregunta | ¿Bloqueante? | Respuesta | Fecha |\n|---|---|---|---|---|\n| 1 | A | sí | | |\n| 2 | B | si | ok | |\n| 3 | C | no | | |\n| 4 |  | sí / no | | |\n\n## 18. Otra\n| 9 | Z | sí | | |\n";
    assertEquals(1, Planning::countBlockingQuestions($c));
});

it('init crea README + 3 índices y no pisa lo existente', function () use ($mkProject, $rmrf) {
    $root = $mkProject();
    $p = new Planning($root);
    $r = $p->init();
    assertCount(4, $r['created']);
    assertTrue(is_file("{$root}/planning/plans/INDEX.md"));
    assertFalse(is_file("{$root}/planning/plans/PL.template.md"), 'las plantillas de ítem quedan en el core');
    $r2 = $p->init();
    assertCount(0, $r2['created']);
    assertCount(4, $r2['skipped']);
    $rmrf($root);
});

it('nextCode usa el máximo entre archivos e índice', function () use ($mkProject, $rmrf, $put) {
    $root = $mkProject();
    $p = new Planning($root);
    $p->init();
    assertEquals('RM-001', $p->nextCode('rm'));
    $put($root, 'roadmap/RM-004-x.md', "---\ncode: RM-004\ntitle: X\nstatus: Idea\n---\n");
    assertEquals('RM-005', $p->nextCode('rm'));
    $rmrf($root);
});

it('fixture coherente: lint sin errores ni warnings tras reindex', function () use ($mkProject, $rmrf, $seed) {
    $root = $mkProject();
    $p = new Planning($root);
    $p->init();
    $seed($root);
    $written = $p->reindex();
    assertCount(3, $written);
    $r = $p->lint('2026-11-07');
    assertEquals([], $r['errors']);
    assertEquals([], $r['warnings']);
    assertTrue(in_array('PL-001: fases pendientes → /plan run PL-001', $r['suggestions'], true), implode('; ', $r['suggestions']));
    $idx = (string) file_get_contents("{$root}/planning/plans/INDEX.md");
    assertTrue(str_contains($idx, '| [PL-001](PL-001-reprogramacion.md) | Reprogramación self-service | ⚙️ En ejecución | BR-001 | RM-001 | brief | 1/2 | 2026-11-07 |'));
    assertTrue(str_contains($idx, 'Próximo código libre: **PL-002**'));
    $rmIdx = (string) file_get_contents("{$root}/planning/roadmap/INDEX.md");
    assertTrue(str_contains($rmIdx, '| BR-001 | PL-001 |'));
    assertEquals([], $p->reindex(), 'reindex es idempotente');
    $rmrf($root);
});

it('lint detecta código duplicado, vínculo roto, estado inválido y archivo mal nombrado', function () use ($mkProject, $rmrf, $seed, $put) {
    $root = $mkProject();
    $p = new Planning($root);
    $p->init();
    $seed($root);
    $put($root, 'plans/PL-002-a.md', "---\ncode: PL-002\ntitle: A\nstatus: Aprobado\nbrief: BR-009\nrm: RM-001\n---\n### F1 — x · estado: 📋 pendiente\n");
    $put($root, 'plans/PL-002-b.md', "---\ncode: PL-002\ntitle: B\nstatus: Aprobado\n---\n");
    $put($root, 'plans/PL-003-c.md', "---\ncode: PL-003\ntitle: C\nstatus: Terminado\n---\n");
    $put($root, 'plans/plan-suelto.md', "---\ncode: PL-010\n---\n");
    $errors = implode("\n", $p->lint('2026-11-07')['errors']);
    assertTrue(str_contains($errors, 'código duplicado'), $errors);
    assertTrue(str_contains($errors, 'apunta a BR-009, que no existe'), $errors);
    assertTrue(str_contains($errors, "estado inválido 'Terminado'"), $errors);
    assertTrue(str_contains($errors, 'nombre de archivo inválido'), $errors);
    $rmrf($root);
});

it('lint avisa propagación, vencimientos y vínculos de un solo sentido', function () use ($mkProject, $rmrf, $put) {
    $root = $mkProject();
    $p = new Planning($root);
    $p->init();
    $put($root, 'roadmap/RM-001-a.md', "---\ncode: RM-001\ntitle: A\nstatus: 📋 Planificado\ndue_tentative: 2026-10-01\nbriefs: []\nplans: []\n---\n");
    $put($root, 'briefs/BR-001-a.md', "---\ncode: BR-001\ntitle: A\nstatus: ✅ Listo\nrm: RM-001\n---\n");
    $put($root, 'plans/PL-001-a.md', "---\ncode: PL-001\ntitle: A\nstatus: ⚙️ En ejecución\nbrief: BR-001\nrm: RM-001\n---\n### F1 — x · estado: 📋 pendiente\n");
    $p->reindex();
    $w = implode("\n", $p->lint('2026-11-07')['warnings']);
    assertTrue(str_contains($w, "RM-001: debería estar '🟡 En curso'"), $w);
    assertTrue(str_contains($w, "BR-001: debería estar '🧭 Planificado'"), $w);
    assertTrue(str_contains($w, 'entrega tentativa vencida'), $w);
    assertTrue(str_contains($w, 'RM-001: no lista a BR-001'), $w);
    $rmrf($root);
});

it('lint avisa si el índice quedó desalineado con los archivos', function () use ($mkProject, $rmrf, $seed) {
    $root = $mkProject();
    $p = new Planning($root);
    $p->init();
    $seed($root);
    $w = implode("\n", $p->lint('2026-11-07')['warnings']);
    assertTrue(str_contains($w, 'plans/INDEX.md: falta PL-001'), $w);
    $rmrf($root);
});

it('el planning/ del propio repo (si existe) no tiene errores de estructura', function () {
    if (!is_dir(PROJECT_PATH . '/planning')) {
        return 'skip';
    }
    $r = (new Planning(PROJECT_PATH))->lint();
    assertEquals([], $r['errors'], implode('; ', $r['errors']));
});
