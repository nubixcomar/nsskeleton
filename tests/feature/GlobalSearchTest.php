<?php

declare(strict_types=1);

use App\Services\GlobalSearch;
use Core\Database;

group('GlobalSearch (feature · requiere MySQL)');

it('textColumns detecta columnas de texto', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    $cols = GlobalSearch::textColumns('admin_users');
    assertTrue(in_array('name', $cols, true));
    assertTrue(in_array('email', $cols, true));
    // no debería traer columnas datetime
    assertFalse(in_array('created_at', $cols, true));
});

it('encuentra un valor sembrado en un módulo', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    // asegura que exista la tabla contactos con un valor único
    try {
        Database::run('INSERT INTO contactos (nombre, email, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', ['ZZBUSCAGLOBAL', 'zz@x.com']);
    } catch (\Throwable) {
        return 'skip'; // si no existe la tabla, omitir
    }

    $groups = GlobalSearch::search('ZZBUSCAGLOBAL');
    $found = false;
    foreach ($groups as $g) {
        foreach ($g['matches'] as $m) {
            if (str_contains($m['label'], 'ZZBUSCAGLOBAL')) {
                $found = true;
            }
        }
    }
    assertTrue($found, 'esperaba encontrar el valor sembrado en la búsqueda global');
});
