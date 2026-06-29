<?php

declare(strict_types=1);

use App\Services\Exporter;

group('Exporter (E3)');

$rows = [
    ['id' => 1, 'nombre' => 'Ana', 'activo' => true],
    ['id' => 2, 'nombre' => 'Beto, Jr', 'activo' => false],
];
$cols = ['id' => '#', 'nombre' => 'Nombre', 'activo' => 'Activo'];

it('csv lleva encabezados y filas', function () use ($rows, $cols) {
    $csv = Exporter::csv($rows, $cols);
    assertContains('#,Nombre,Activo', $csv);
    assertContains('Ana', $csv);
    assertContains('Sí', $csv);  // bool true → Sí
    assertContains('No', $csv);  // bool false → No
});

it('csv escapa comas en los valores', function () use ($rows, $cols) {
    $csv = Exporter::csv($rows, $cols);
    assertContains('"Beto, Jr"', $csv);
});

it('csv arranca con BOM UTF-8', function () use ($rows, $cols) {
    $csv = Exporter::csv($rows, $cols);
    assertEquals("\xEF\xBB\xBF", substr($csv, 0, 3));
});

it('excelHtml es una tabla HTML', function () use ($rows, $cols) {
    $html = Exporter::excelHtml($rows, $cols, 'Clientes');
    assertContains('<table', $html);
    assertContains('<th>Nombre</th>', $html);
    assertContains('Ana', $html);
});

it('printableHtml incluye auto-print', function () use ($rows, $cols) {
    $html = Exporter::printableHtml($rows, $cols, 'Clientes');
    assertContains('window.print()', $html);
});

it('filename arma slug + extensión', function () {
    $name = Exporter::filename('contactos', 'csv');
    assertContains('contactos-', $name);
    assertContains('.csv', $name);
});
