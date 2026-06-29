<?php

declare(strict_types=1);

use App\Services\Deployer;

group('Deployer (plan, sin acciones salientes)');

it('filesToDeploy incluye el código y excluye secretos/deps', function () {
    $files = Deployer::filesToDeploy();

    assertTrue(in_array('AGENTS.md', $files, true));
    assertTrue(in_array('system/public/index.php', $files, true));

    // Exclusiones
    foreach ($files as $f) {
        assertTrue(!str_starts_with($f, 'tools/bin/'), 'no debe incluir el binario de Tailwind');
        assertTrue(!str_starts_with($f, 'vendor/'), 'no debe incluir vendor');
        assertTrue($f !== '.env', 'no debe incluir el .env');
    }
});

it('config lee credenciales del entorno', function () {
    $_ENV['FTP_HOST'] = 'ftp.ejemplo.com';
    $_ENV['DEPLOY_BRANCH'] = 'produccion';
    $c = Deployer::config();
    unset($_ENV['FTP_HOST'], $_ENV['DEPLOY_BRANCH']);

    assertEquals('ftp.ejemplo.com', $c['ftp_host']);
    assertEquals('produccion', $c['branch']);
});

it('gitCommands incluye push con la rama', function () {
    $_ENV['DEPLOY_BRANCH'] = 'main';
    $cmds = Deployer::gitCommands();
    unset($_ENV['DEPLOY_BRANCH']);

    assertTrue(in_array('git add -A', $cmds, true));
    assertContains('git push origin main', implode("\n", $cmds));
});

it('ftpConfigured es false sin host/usuario', function () {
    $prevH = $_ENV['FTP_HOST'] ?? null;
    $prevU = $_ENV['FTP_USER'] ?? null;
    $_ENV['FTP_HOST'] = '';
    $_ENV['FTP_USER'] = '';
    $r = Deployer::ftpConfigured();
    if ($prevH !== null) { $_ENV['FTP_HOST'] = $prevH; } else { unset($_ENV['FTP_HOST']); }
    if ($prevU !== null) { $_ENV['FTP_USER'] = $prevU; } else { unset($_ENV['FTP_USER']); }
    assertFalse($r);
});
