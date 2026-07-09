<?php

declare(strict_types=1);

use Core\ErrorLog;

/**
 * v1.17 #5 — Error-log server-side siempre-on + rotación (hallazgo 1.8).
 * Aislamiento (hallazgo 1.7): cada test snapshotea y restaura el error.log real.
 */

group('ErrorLog (server-side siempre-on)');

/** Corre $fn con el error.log restaurado al estado previo. */
$isolated = static function (callable $fn): void {
    $path = ErrorLog::path();
    $backup = ($path !== null && is_file($path)) ? file_get_contents($path) : null;
    try {
        $fn($path);
    } finally {
        if ($path !== null) {
            if ($backup === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $backup);
            }
        }
    }
};

it('write() agrega una línea con timestamp y nivel', function () use ($isolated) {
    $isolated(function ($path) {
        ErrorLog::write('ERROR', 'MARCADOR-DE-TEST-123');
        $tail = ErrorLog::tail(5);
        $joined = implode("\n", $tail);
        assertContains('MARCADOR-DE-TEST-123', $joined);
        assertContains('[ERROR]', $joined);
    });
});

it('exception() registra clase, mensaje y traza', function () use ($isolated) {
    $isolated(function ($path) {
        try {
            throw new \RuntimeException('boom-de-prueba');
        } catch (\Throwable $e) {
            ErrorLog::exception($e);
        }
        $joined = implode("\n", ErrorLog::tail(30));
        assertContains('RuntimeException', $joined);
        assertContains('boom-de-prueba', $joined);
        assertContains('#0', $joined); // primera línea de la traza
    });
});

it('tail(N) devuelve a lo sumo N líneas', function () use ($isolated) {
    $isolated(function ($path) {
        for ($i = 0; $i < 10; $i++) {
            ErrorLog::write('INFO', "linea-{$i}");
        }
        $tail = ErrorLog::tail(3);
        assertTrue(count($tail) <= 3, 'tail respeta el límite');
        assertContains('linea-9', implode("\n", $tail));
    });
});

it('path() resuelve a storage/logs/error.log', function () {
    $p = ErrorLog::path();
    assertNotNull($p);
    assertContains('storage/logs/error.log', str_replace('\\', '/', (string) $p));
});
