<?php

declare(strict_types=1);

use App\Services\Migrator;

group('Migrator::parse (up/down)');

it('separa up y down por el marcador @DOWN', function () {
    $tmp = sys_get_temp_dir() . '/nsmig_parse_' . uniqid() . '.sql';
    file_put_contents($tmp, "CREATE TABLE x (id INT);\n-- @DOWN\nDROP TABLE x;\n");
    $p = Migrator::parse($tmp);
    @unlink($tmp);
    assertContains('CREATE TABLE x', $p['up']);
    assertTrue(!str_contains($p['up'], 'DROP TABLE'), 'el up no debe incluir el down');
    assertEquals('DROP TABLE x;', $p['down']);
});

it('sin marcador, down queda vacío', function () {
    $tmp = sys_get_temp_dir() . '/nsmig_parse2_' . uniqid() . '.sql';
    file_put_contents($tmp, "CREATE TABLE y (id INT);\n");
    $p = Migrator::parse($tmp);
    @unlink($tmp);
    assertContains('CREATE TABLE y', $p['up']);
    assertEquals('', $p['down']);
});
