<?php

declare(strict_types=1);

/**
 * Motor de actualización de core (Fase 4). No requiere DB ni red: arma un árbol
 * sintético "instalado" + "source nuevo" y verifica plan/apply/rollback.
 */

use App\Services\CoreUpdater;

group('CoreUpdater (motor de actualización)');

$sha = static fn (string $s): string => hash('sha256', $s);

$rrmdir = static function (string $dir) use (&$rrmdir): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $e) {
        if ($e === '.' || $e === '..') {
            continue;
        }
        $p = $dir . '/' . $e;
        is_dir($p) ? $rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
};

$put = static function (string $path, string $content): void {
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, $content);
};

/** Crea el escenario y devuelve [install, source, backup, oldLock, newLock]. */
$scenario = static function () use ($put, $sha): array {
    $base = sys_get_temp_dir() . '/nsupd_' . bin2hex(random_bytes(4));
    $install = $base . '/install';
    $source  = $base . '/source';
    $backup  = $base . '/backup';

    // Árbol instalado.
    $put($install . '/a.txt', 'A1');     // core sin modificar → update
    $put($install . '/b.txt', 'B1');     // core modificado por la app → conflict
    $put($install . '/c.txt', 'C1');     // core lo elimina, sin modificar → delete
    $put($install . '/d.txt', 'D-app');  // core lo elimina pero la app lo editó → delete_modified
    $put($install . '/e.txt', 'E-app');  // la app creó e.txt; el core nuevo trae e.txt → conflict_add
    // f.txt no existe → add

    // Source (core nuevo).
    $put($source . '/a.txt', 'A2');
    $put($source . '/b.txt', 'B2');
    $put($source . '/e.txt', 'E-new');
    $put($source . '/f.txt', 'F-new');

    $oldLock = [
        'a.txt' => $sha('A1'),
        'b.txt' => $sha('B-orig'),   // la app cambió b (B1 ≠ B-orig)
        'c.txt' => $sha('C1'),
        'd.txt' => $sha('D-orig'),   // la app cambió d
    ];
    $newLock = [
        'a.txt' => $sha('A2'),
        'b.txt' => $sha('B2'),
        'e.txt' => $sha('E-new'),
        'f.txt' => $sha('F-new'),
    ];

    return [$base, $install, $source, $backup, $oldLock, $newLock];
};

it('plan clasifica add/update/conflict/conflict_add/delete/delete_modified', function () use ($scenario, $rrmdir) {
    [$base, $install, $source, , $oldLock, $newLock] = $scenario();
    try {
        $plan = CoreUpdater::plan($oldLock, $newLock, $install, $source);
        $by = [];
        foreach ($plan as $e) {
            $by[$e['path']] = $e['action'];
        }
        assertEquals(CoreUpdater::UPDATE, $by['a.txt']);
        assertEquals(CoreUpdater::CONFLICT, $by['b.txt']);
        assertEquals(CoreUpdater::CONFLICT_ADD, $by['e.txt']);
        assertEquals(CoreUpdater::ADD, $by['f.txt']);
        assertEquals(CoreUpdater::DELETE, $by['c.txt']);
        assertEquals(CoreUpdater::DELETE_MODIFIED, $by['d.txt']);
    } finally {
        $rrmdir($base);
    }
});

it('skip cuando el archivo local ya es igual al nuevo', function () use ($sha, $rrmdir) {
    $base = sys_get_temp_dir() . '/nsupd_' . bin2hex(random_bytes(4));
    @mkdir($base . '/i', 0775, true);
    file_put_contents($base . '/i/x.txt', 'SAME');
    try {
        $plan = CoreUpdater::plan(['x.txt' => $sha('OLD')], ['x.txt' => $sha('SAME')], $base . '/i', $base . '/s');
        assertEquals(CoreUpdater::SKIP, $plan[0]['action']);
    } finally {
        $rrmdir($base);
    }
});

it('apply pisa update, deja .new en conflictos, agrega y borra', function () use ($scenario, $rrmdir) {
    [$base, $install, $source, $backup, $oldLock, $newLock] = $scenario();
    try {
        $plan = CoreUpdater::plan($oldLock, $newLock, $install, $source);
        $res = CoreUpdater::apply($plan, $install, $source, $backup);

        assertEquals('A2', file_get_contents($install . '/a.txt'));        // update pisó
        assertEquals('B1', file_get_contents($install . '/b.txt'));        // conflicto: local intacto
        assertEquals('B2', file_get_contents($install . '/b.txt.new'));    // y el nuevo como .new
        assertEquals('E-app', file_get_contents($install . '/e.txt'));     // conflict_add: local intacto
        assertEquals('E-new', file_get_contents($install . '/e.txt.new'));
        assertEquals('F-new', file_get_contents($install . '/f.txt'));     // add
        assertFalse(is_file($install . '/c.txt'));                         // delete
        assertEquals('D-app', file_get_contents($install . '/d.txt'));     // delete_modified: se conserva

        assertEquals('A1', file_get_contents($backup . '/files/a.txt'));   // backup del pisado
        assertEquals('C1', file_get_contents($backup . '/files/c.txt'));   // backup del borrado
        assertTrue(is_file($backup . '/applied.json'));
        assertEquals([], $res['errors']);
        assertTrue(in_array('b.txt', $res['conflicts'], true));
    } finally {
        $rrmdir($base);
    }
});

it('rollback restaura el estado previo', function () use ($scenario, $rrmdir) {
    [$base, $install, $source, $backup, $oldLock, $newLock] = $scenario();
    try {
        $plan = CoreUpdater::plan($oldLock, $newLock, $install, $source);
        CoreUpdater::apply($plan, $install, $source, $backup);
        $r = CoreUpdater::rollback($backup, $install);

        assertEquals('A1', file_get_contents($install . '/a.txt'));   // restaurado
        assertEquals('C1', file_get_contents($install . '/c.txt'));   // restaurado (borrado revertido)
        assertFalse(is_file($install . '/f.txt'));                    // add revertido
        assertFalse(is_file($install . '/b.txt.new'));                // .new limpiado
        assertFalse(is_file($install . '/e.txt.new'));
        assertEquals('B1', file_get_contents($install . '/b.txt'));   // local nunca tocado
        assertEquals([], $r['errors']);
    } finally {
        $rrmdir($base);
    }
});

it('loadLockFiles lee el mapa files del lock', function () use ($rrmdir) {
    $base = sys_get_temp_dir() . '/nsupd_' . bin2hex(random_bytes(4));
    @mkdir($base, 0775, true);
    file_put_contents($base . '/core-lock.json', json_encode(['core_version' => '9.9.9', 'count' => 1, 'files' => ['z.php' => 'abc']]));
    try {
        $files = CoreUpdater::loadLockFiles($base . '/core-lock.json');
        assertEquals('abc', $files['z.php']);
    } finally {
        $rrmdir($base);
    }
});
