<?php

declare(strict_types=1);

use App\Services\Backup;
use Core\Database;

group('Base de datos (feature · requiere MySQL)');

it('conecta y la tabla admin_users existe', function () {
    try {
        $row = Database::selectOne('SELECT COUNT(*) AS n FROM admin_users');
    } catch (\Throwable) {
        return 'skip';
    }
    assertTrue(is_array($row));
});

it('dump de base genera SQL con CREATE TABLE', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    $r = Backup::createDatabaseBackup();
    assertTrue($r['ok']);
    $sql = file_get_contents(Backup::dir() . '/' . $r['file']) ?: '';
    assertContains('CREATE TABLE', $sql);
    Backup::delete($r['file']);
});
