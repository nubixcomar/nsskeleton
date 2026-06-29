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
