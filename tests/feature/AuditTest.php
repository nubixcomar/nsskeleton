<?php

declare(strict_types=1);

use App\Services\Audit;
use Core\Database;

group('Audit (feature · requiere MySQL)');

it('registra una acción en audit_log', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $marker = '__test_audit_' . uniqid();
    Audit::log($marker, 'objetivo-x', 'detalle de prueba');

    $row = Database::selectOne('SELECT * FROM audit_log WHERE action = ? LIMIT 1', [$marker]);
    assertNotNull($row);
    assertEquals('objetivo-x', $row['target']);

    Database::run('DELETE FROM audit_log WHERE action = ?', [$marker]);
});

it('no rompe aunque no haya admin logueado (best-effort)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $marker = '__test_audit_anon_' . uniqid();
    Audit::log($marker); // sin sesión activa en CLI
    $row = Database::selectOne('SELECT * FROM audit_log WHERE action = ? LIMIT 1', [$marker]);
    assertNotNull($row);
    Database::run('DELETE FROM audit_log WHERE action = ?', [$marker]);
});
