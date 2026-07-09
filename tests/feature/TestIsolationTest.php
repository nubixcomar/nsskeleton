<?php

declare(strict_types=1);

use Core\Database;

/**
 * v1.17 #3 — Aislamiento de tests con transacción + rollback (hallazgo 1.7).
 * Los tests que siembran datos ya no ensucian la DB ni ponen la suite en rojo.
 * Requiere MySQL (skip si no está disponible).
 */

group('Aislamiento de tests (transacción + rollback)');

itDb('siembra filas y las cuenta scopeado por su fixture', function () {
    for ($i = 0; $i < 3; $i++) {
        Database::run('INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, ?)',
            ["__txiso_{$i}", 'v', 'txiso']);
    }
    $row = Database::selectOne('SELECT COUNT(*) c FROM settings WHERE `group` = ?', ['txiso']);
    assertEquals(3, (int) ($row['c'] ?? 0), 'debe contar exactamente el fixture sembrado');
});

it('las filas del test anterior NO quedaron (el rollback las revirtió)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    $row = Database::selectOne('SELECT COUNT(*) c FROM settings WHERE `group` = ?', ['txiso']);
    assertEquals(0, (int) ($row['c'] ?? 0), 'itDb debe haber revertido las 3 filas sembradas');
});

group('Database::transaction');

it('revierte ante excepción (rollback)', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    try {
        Database::transaction(static function (): void {
            Database::run('INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, ?)',
                ['__txrb', 'v', 'txrb']);
            throw new \RuntimeException('forzar rollback');
        });
    } catch (\RuntimeException) {
        // esperado
    }
    $row = Database::selectOne('SELECT COUNT(*) c FROM settings WHERE `group` = ?', ['txrb']);
    assertEquals(0, (int) ($row['c'] ?? 0), 'la excepción debe revertir el INSERT');
});

it('commitea al terminar OK', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    Database::transaction(static function (): void {
        Database::run('INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, ?)',
            ['__txok', 'v', 'txok']);
    });
    $row = Database::selectOne('SELECT COUNT(*) c FROM settings WHERE `group` = ?', ['txok']);
    assertEquals(1, (int) ($row['c'] ?? 0), 'el commit debe persistir');
    // Limpieza (esta sí commiteó): borrar el fixture.
    Database::run('DELETE FROM settings WHERE `group` = ?', ['txok']);
});
