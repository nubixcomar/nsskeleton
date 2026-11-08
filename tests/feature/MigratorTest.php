<?php

declare(strict_types=1);

use App\Services\Migrator;
use Core\Database;

group('Migrator migrate/rollback (feature · requiere MySQL)');

it('aplica y revierte una migración aislada (con @DOWN)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    // Dir temporal con una migración de prueba.
    $dir = sys_get_temp_dir() . '/nsmig_' . uniqid();
    @mkdir($dir, 0775, true);
    $name = '29990101_0001_create_nsmig_demo.sql';
    file_put_contents(
        $dir . '/' . $name,
        "CREATE TABLE IF NOT EXISTS nsmig_demo (id INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id))"
        . " ENGINE=InnoDB;\n-- @DOWN\nDROP TABLE IF EXISTS nsmig_demo;\n"
    );

    $tableExists = static function (): bool {
        try {
            Database::selectOne('SELECT 1 FROM nsmig_demo LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    };

    try {
        // Migrar
        $done = Migrator::migrate($dir);
        assertTrue(in_array($name, $done, true), 'debería aplicar la migración demo');
        assertTrue($tableExists(), 'la tabla debería existir tras migrar');

        // Status: aplicada y reversible
        $status = Migrator::status($dir);
        assertEquals(1, count($status));
        assertTrue($status[0]['applied']);
        assertTrue($status[0]['reversible']);

        // Rollback
        $reverted = Migrator::rollback(1, $dir);
        assertTrue(in_array($name, $reverted, true), 'debería revertir la migración demo');
        assertFalse($tableExists(), 'la tabla no debería existir tras el rollback');
    } finally {
        // Limpieza (por si quedó a medias).
        Database::connection()->exec('DROP TABLE IF EXISTS nsmig_demo');
        Database::run('DELETE FROM schema_migrations WHERE migration = ?', [$name]);
        @unlink($dir . '/' . $name);
        @rmdir($dir);
    }
});

it('rollback global entre core y app: revierte primero la aplicada después (PL-002/F8)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    // Dos dirs temporales que hacen de "core" y "app" (nunca se escribe en system/).
    $tag = bin2hex(random_bytes(3));
    $coreDir = sys_get_temp_dir() . '/nsmig_core_' . $tag;
    $appDir = sys_get_temp_dir() . '/nsmig_app_' . $tag;
    @mkdir($coreDir, 0775, true);
    @mkdir($appDir, 0775, true);
    $coreName = "29990101_0001_core_{$tag}.sql";
    $appName = "29990101_0000_app_{$tag}.sql";   // nombre MENOR que el de core: el orden lo da la aplicación
    $coreTable = "nsmig_core_{$tag}";
    $appTable = "nsmig_app_{$tag}";
    $sql = static fn (string $t): string => "CREATE TABLE IF NOT EXISTS {$t} (id INT UNSIGNED NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB;\n-- @DOWN\nDROP TABLE IF EXISTS {$t};\n";
    file_put_contents("{$coreDir}/{$coreName}", $sql($coreTable));
    file_put_contents("{$appDir}/{$appName}", $sql($appTable));
    $exists = static function (string $t): bool {
        try {
            Database::selectOne("SELECT 1 FROM {$t} LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    };

    try {
        Migrator::migrate($coreDir);
        Migrator::migrate($appDir);
        // Empate forzado: mismo segundo para las dos.
        Database::run(
            "UPDATE schema_migrations SET applied_at = '2000-01-01 00:00:00' WHERE migration IN (?, ?)",
            [$coreName, $appName]
        );

        $r1 = Migrator::rollbackAcross(1, [$coreDir, $appDir]);
        assertEquals([$appName], $r1, 'primero se revierte la de app (aplicada después)');
        assertFalse($exists($appTable));
        assertTrue($exists($coreTable), 'la de core sigue aplicada');

        $r2 = Migrator::rollbackAcross(1, [$coreDir, $appDir]);
        assertEquals([$coreName], $r2);
        assertFalse($exists($coreTable));

        // Con un solo dir: comportamiento previo (solo ese directorio).
        Migrator::migrate($coreDir);
        Migrator::migrate($appDir);
        assertEquals([$coreName], Migrator::rollback(1, $coreDir), 'rollback con dir revierte solo ese dir');
        assertTrue($exists($appTable));
    } finally {
        Database::connection()->exec("DROP TABLE IF EXISTS {$coreTable}");
        Database::connection()->exec("DROP TABLE IF EXISTS {$appTable}");
        Database::run('DELETE FROM schema_migrations WHERE migration IN (?, ?)', [$coreName, $appName]);
        @unlink("{$coreDir}/{$coreName}");
        @unlink("{$appDir}/{$appName}");
        @rmdir($coreDir);
        @rmdir($appDir);
    }
});
