<?php

declare(strict_types=1);

/**
 * Orquestador del update de core + base para merge 3-way + releases (v1.20).
 * Árboles sintéticos en temp; sin DB (migrate=false) y sin red (Http::setTransport).
 */

use App\Services\CoreRelease;
use App\Services\CoreUpdate;
use App\Services\CoreUpdater;
use App\Services\Http;

group('CoreUpdate (orquestador + base + merge 3-way)');

$sha = static fn (string $s): string => hash('sha256', $s);
$put = static function (string $path, string $content): void {
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, $content);
};

$B1 = "<?php\n// core b\nfunction uno() { return 1; }\n\nfunction dos() { return 2; }\n\nfunction tres() { return 3; }\n";
$B2 = "<?php\n// core b\nfunction uno() { return 1; }\n\nfunction dos() { return 2; }\n\nfunction tres() { return 33; } // v2\n";
$B_APP = "<?php\n// core b (editado por la app)\nfunction uno() { return 1; }\n\nfunction dos() { return 2; }\n\nfunction tres() { return 3; }\n";

/**
 * Instalación v1.0.0 (a.txt, b.php) + paquete v2.0.0 (a.txt nuevo, b.php nuevo, n.txt).
 * @return array{0:string,1:CoreUpdate,2:string,3:string} [base, cu, install, source]
 */
$scenario = static function (bool $capture) use ($put, $sha, $B1, $B2): array {
    $root = sys_get_temp_dir() . '/nscu_' . bin2hex(random_bytes(4));
    $install = $root . '/install';
    $source  = $root . '/source';

    $put($install . '/a.txt', "A1\n");
    $put($install . '/b.php', $B1);
    $put($install . '/VERSION', "1.0.0\n");
    $put($install . '/core-lock.json', json_encode(['core_version' => '1.0.0', 'files' => [
        'a.txt' => $sha("A1\n"), 'b.php' => $sha($B1),
    ]]));

    $put($source . '/a.txt', "A2\n");
    $put($source . '/b.php', $B2);
    $put($source . '/n.txt', "N\n");
    $put($source . '/VERSION', "2.0.0\n");
    $put($source . '/core-lock.json', json_encode(['core_version' => '2.0.0', 'files' => [
        'a.txt' => $sha("A2\n"), 'b.php' => $sha($B2), 'n.txt' => $sha("N\n"),
    ]]));

    $cu = new CoreUpdate($install, $root . '/storage');
    if ($capture) {
        $cu->captureBase(); // la base se captura ANTES de que la app edite
    }
    return [$root, $cu, $install, $source];
};

it('captureBase guarda solo archivos sin editar y baseStatus lo refleja', function () use ($scenario, $put) {
    [$root, $cu, $install] = $scenario(false);
    try {
        $put($install . '/b.php', "editado\n");
        $r = $cu->captureBase();
        assertEquals(1, $r['stored']);   // solo a.txt
        assertEquals(1, $r['missing']);  // b.php editado: no se toma como base
        $st = $cu->baseStatus();
        assertEquals(2, $st['total']);
        assertEquals(1, $st['valid']);
        assertEquals(['b.php'], $cu->editedWithoutBase());
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

it('conflicto con base → merge 3-way limpio, base rota a v2 y rollback completo', function () use ($scenario, $put, $B1, $B2, $B_APP, $sha) {
    [$root, $cu, $install, $source] = $scenario(true);
    try {
        $put($install . '/b.php', $B_APP);   // la app edita el comentario de arriba

        $info = $cu->inspect($source, $source . '/core-lock.json');
        assertEquals('clean', $info['merge']['b.php']['status']);
        assertEquals([], $info['unwritable']);

        $res = $cu->run($source, $source . '/core-lock.json', ['migrate' => false]);
        assertTrue($res['ok'], implode('; ', $res['errors']) . $res['error']);
        assertEquals(['b.php'], $res['merged']);
        assertEquals([], $res['conflicts']);

        $merged = (string) file_get_contents($install . '/b.php');
        assertTrue(str_contains($merged, '(editado por la app)'), 'conserva el cambio local');
        assertTrue(str_contains($merged, 'return 33; } // v2'), 'trae el cambio del core');
        assertEquals("A2\n", file_get_contents($install . '/a.txt'));
        assertEquals("N\n", file_get_contents($install . '/n.txt'));
        assertEquals('2.0.0', trim((string) file_get_contents($install . '/VERSION')));
        assertTrue(is_file($res['backup_dir'] . '/report.json'));

        // La base ahora es el core v2 completo.
        assertEquals($sha($B2), hash_file('sha256', $cu->baseDir() . '/b.php'));
        assertEquals(3, $cu->baseStatus()['valid']);

        // Historial.
        $h = $cu->history();
        assertCount(1, $h);
        assertEquals('1.0.0', $h[0]['from']);
        assertEquals('2.0.0', $h[0]['to']);
        assertEquals(1, $h[0]['merged']);

        // Rollback: vuelve el local editado, v1 y la base v1.
        $rb = $cu->rollback($res['backup_dir']);
        assertTrue($rb['ok'], implode('; ', $rb['errors']));
        assertEquals($B_APP, file_get_contents($install . '/b.php'));
        assertEquals("A1\n", file_get_contents($install . '/a.txt'));
        assertFalse(is_file($install . '/n.txt'));
        assertEquals('1.0.0', trim((string) file_get_contents($install . '/VERSION')));
        assertEquals($sha($B1), hash_file('sha256', $cu->baseDir() . '/b.php'));
        assertTrue($cu->history()[0]['rolled_back']);
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

it('choque en la misma línea → local intacto + .new + .merge con marcadores', function () use ($scenario, $put, $B2) {
    [$root, $cu, $install, $source] = $scenario(true);
    try {
        $local = str_replace('return 3;', 'return 300; // app', (string) file_get_contents($install . '/b.php'));
        $put($install . '/b.php', $local);

        $info = $cu->inspect($source, $source . '/core-lock.json');
        assertEquals('conflict', $info['merge']['b.php']['status']);

        $res = $cu->run($source, $source . '/core-lock.json', ['migrate' => false]);
        assertEquals(['b.php'], $res['conflicts']);
        assertEquals(['b.php'], $res['merge_conflicts']);
        assertEquals($local, file_get_contents($install . '/b.php'));
        assertEquals($B2, file_get_contents($install . '/b.php.new'));
        $m = (string) file_get_contents($install . '/b.php.merge');
        assertTrue(str_contains($m, '<<<<<<< proyecto'));
        assertTrue(str_contains($m, '>>>>>>> core'));

        // Rollback limpia .new y .merge.
        $cu->rollback($res['backup_dir']);
        assertFalse(is_file($install . '/b.php.new'));
        assertFalse(is_file($install . '/b.php.merge'));
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

it('sin base del original → comportamiento clásico (.new)', function () use ($scenario, $put, $B_APP) {
    [$root, $cu, $install, $source] = $scenario(false);
    try {
        $put($install . '/b.php', $B_APP); // editado antes de capturar: no hay base
        $info = $cu->inspect($source, $source . '/core-lock.json');
        assertEquals('no_base', $info['merge']['b.php']['status']);
        $res = $cu->run($source, $source . '/core-lock.json', ['migrate' => false]);
        assertEquals([], $res['merged']);
        assertEquals(['b.php'], $res['conflicts']);
        assertTrue(is_file($install . '/b.php.new'));
        assertFalse(is_file($install . '/b.php.merge'));
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

it('seedBaseFrom completa la base desde el paquete de la versión instalada', function () use ($scenario, $put, $B1, $B_APP) {
    [$root, $cu, $install] = $scenario(false);
    try {
        $put($install . '/b.php', $B_APP);
        $pkg = $root . '/pkg-v1';
        $put($pkg . '/a.txt', "A1\n");
        $put($pkg . '/b.php', $B1);
        $r = $cu->seedBaseFrom($pkg);
        assertEquals(2, $r['stored']);
        assertEquals([], $cu->editedWithoutBase());
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

it('merge desactivado (merge=false) deja solo .new aunque haya base', function () use ($scenario, $put, $B_APP) {
    [$root, $cu, $install, $source] = $scenario(true);
    try {
        $put($install . '/b.php', $B_APP);
        $res = $cu->run($source, $source . '/core-lock.json', ['migrate' => false, 'merge' => false]);
        assertEquals([], $res['merged']);
        assertEquals($B_APP, file_get_contents($install . '/b.php'));
        assertTrue(is_file($install . '/b.php.new'));
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

it('prepareSource rechaza zips con rutas que escapan (zip slip)', function () use ($scenario) {
    if (!class_exists('ZipArchive')) {
        return 'skip';
    }
    [$root, $cu] = $scenario(false);
    try {
        $zipFile = $root . '/evil.zip';
        $z = new ZipArchive();
        $z->open($zipFile, ZipArchive::CREATE);
        $z->addFromString('core-lock.json', '{"files":{}}');
        $z->addFromString('../fuera.txt', 'x');
        $z->close();
        $p = $cu->prepareSource($zipFile);
        assertFalse($p['ok']);
        assertTrue(str_contains($p['error'], 'sospechosa'));
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

it('prepareSource ubica el lock en un zip con carpeta raíz', function () use ($scenario) {
    if (!class_exists('ZipArchive')) {
        return 'skip';
    }
    [$root, $cu] = $scenario(false);
    try {
        $zipFile = $root . '/ok.zip';
        $z = new ZipArchive();
        $z->open($zipFile, ZipArchive::CREATE);
        $z->addFromString('core-9.9.9/core-lock.json', '{"files":{}}');
        $z->addFromString('core-9.9.9/VERSION', "9.9.9\n");
        $z->close();
        $p = $cu->prepareSource($zipFile);
        assertTrue($p['ok'], $p['error']);
        assertEquals('9.9.9', $p['version']);
        CoreUpdate::rrmdir((string) $p['cleanup']);
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

it('backupPath no permite salir de backups/', function () use ($scenario) {
    [$root, $cu] = $scenario(false);
    try {
        assertEquals(null, $cu->backupPath('../../etc'));
        assertEquals(null, $cu->backupPath('core-update-20260101_000000/../x'));
        assertEquals(null, $cu->backupPath('core-update-20990101_000000')); // no existe
    } finally {
        CoreUpdate::rrmdir($root);
    }
});

group('CoreRelease (latest.json + descarga verificada)');

$index = static fn (string $sha): array => [
    'schema' => 1, 'name' => 'nsSkeleton-core', 'latest' => '1.20.0',
    'releases' => [
        '1.20.0' => ['file' => 'nsSkeleton-core-1.20.0.zip', 'sha256' => $sha, 'size' => 3],
        '1.19.0' => ['file' => 'nsSkeleton-core-1.19.0.zip', 'sha256' => str_repeat('a', 64)],
    ],
];

it('check detecta versión nueva y resuelve la URL relativa del zip', function () use ($index, $sha) {
    Http::setTransport(static fn () => ['status' => 200, 'body' => json_encode($index($sha('ZIP'))), 'error' => '']);
    try {
        $c = CoreRelease::check('https://ej.com/downloads/latest.json?x=1', '1.19.0');
        assertTrue($c['ok'], $c['error']);
        assertTrue($c['update_available']);
        assertEquals('1.20.0', $c['latest']);
        assertEquals('https://ej.com/downloads/nsSkeleton-core-1.20.0.zip', $c['release']['url']);
        assertFalse($c['insecure']);

        $same = CoreRelease::check('http://ej.com/latest.json', '1.20.0');
        assertFalse($same['update_available']);
        assertTrue($same['insecure']);
    } finally {
        Http::resetTransport();
    }
});

it('valida latest.json: rechaza índices incompletos', function () use ($index, $sha) {
    assertTrue(CoreRelease::validateIndex($index($sha('x'))) === null);
    assertTrue(CoreRelease::validateIndex(['latest' => '1.0.0', 'releases' => []]) !== null);
    assertTrue(CoreRelease::validateIndex(['latest' => 'v1', 'releases' => []]) !== null);
    $bad = $index('no-es-un-sha');
    assertTrue(CoreRelease::validateIndex($bad) !== null);
    assertTrue(CoreRelease::validateIndex('texto') !== null);
});

it('download verifica sha256 y descarta el archivo si no coincide', function () use ($sha) {
    $dir = sys_get_temp_dir() . '/nsrel_' . bin2hex(random_bytes(4));
    Http::setTransport(static fn () => ['status' => 200, 'body' => 'ZIP', 'error' => '']);
    try {
        $ok = CoreRelease::download('https://ej.com/a.zip', $dir . '/a.zip', $sha('ZIP'));
        assertTrue($ok['ok'], $ok['error']);
        assertEquals('ZIP', file_get_contents($dir . '/a.zip'));

        $bad = CoreRelease::download('https://ej.com/b.zip', $dir . '/b.zip', $sha('OTRA COSA'));
        assertFalse($bad['ok']);
        assertFalse(is_file($dir . '/b.zip'));
        assertTrue(str_contains($bad['error'], 'sha256'));

        Http::setTransport(static fn () => ['status' => 404, 'body' => '', 'error' => '']);
        assertFalse(CoreRelease::download('https://ej.com/c.zip', $dir . '/c.zip')['ok']);
        assertFalse(CoreRelease::download('file:///etc/passwd', $dir . '/d.zip')['ok']);
    } finally {
        Http::resetTransport();
        CoreUpdate::rrmdir($dir);
    }
});

it('buildIndex arma latest.json desde los zips (más nueva primero)', function () {
    $dir = sys_get_temp_dir() . '/nsidx_' . bin2hex(random_bytes(4));
    @mkdir($dir, 0775, true);
    try {
        foreach (['1.9.0', '1.20.0', '1.10.0'] as $v) {
            file_put_contents($dir . "/nsSkeleton-core-{$v}.zip", "zip {$v}");
        }
        file_put_contents($dir . '/nsSkeleton-1.20.0.zip', 'paquete completo: no es de core');
        $idx = CoreRelease::buildIndex($dir);
        assertEquals('1.20.0', $idx['latest']);
        assertEquals(['1.20.0', '1.10.0', '1.9.0'], array_keys($idx['releases']));
        assertEquals(hash('sha256', 'zip 1.20.0'), $idx['releases']['1.20.0']['sha256']);
        assertTrue(CoreRelease::validateIndex($idx) === null);
    } finally {
        CoreUpdate::rrmdir($dir);
    }
});
