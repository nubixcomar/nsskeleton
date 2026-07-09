<?php

declare(strict_types=1);

use App\Services\Installer;

/**
 * v1.18 #5 — git init en el installer (hallazgo menor de la auditoría).
 */

group('Installer · git init');

it('detecta que el skeleton ya es un repo git', function () {
    // El propio proyecto tiene .git.
    assertTrue(Installer::isGitRepo(PROJECT_PATH));
});

it('no reinicializa si ya es repo (done=false, sin comandos)', function () {
    $ran = [];
    $r = Installer::gitInit(PROJECT_PATH, static function (string $cmd) use (&$ran): int {
        $ran[] = $cmd;
        return 0;
    });
    assertTrue($r['ok']);
    assertFalse($r['done']);
    assertCount(0, $ran, 'no debe correr git si ya es repo');
});

it('en un dir sin git corre init + add + commit (runner inyectado)', function () {
    $fake = sys_get_temp_dir() . '/ns_no_git_' . getmypid();
    $ran = [];
    $r = Installer::gitInit($fake, static function (string $cmd) use (&$ran): int {
        $ran[] = $cmd;
        return 0;
    });
    assertTrue($r['done']);
    assertCount(3, $ran);
    assertContains('git init', $ran[0]);
    assertContains('git add', $ran[1]);
    assertContains('git commit', $ran[2]);
});

it('gitInitCommands incluye init, add y commit', function () {
    $cmds = Installer::gitInitCommands();
    assertContains('git init', implode("\n", $cmds));
    assertContains('git add -A', implode("\n", $cmds));
    assertTrue(str_contains(implode("\n", $cmds), 'git commit'));
});
