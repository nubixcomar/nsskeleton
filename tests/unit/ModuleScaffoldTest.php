<?php

declare(strict_types=1);

use App\Services\ModuleScaffold as S;

group('ModuleScaffold');

it('studly normaliza nombres', function () {
    assertEquals('Producto', S::studly('producto'));
    assertEquals('OrdenCompra', S::studly('orden_compra'));
    assertEquals('OrdenCompra', S::studly('orden compra'));
});

it('snake normaliza nombres', function () {
    assertEquals('orden_compra', S::snake('OrdenCompra'));
    assertEquals('producto', S::snake('Producto'));
    assertEquals('mi_campo', S::snake('mi campo'));
});

it('label humaniza un campo', function () {
    assertEquals('Fecha alta', S::label('fecha_alta'));
});

it('mapea tipos SQL', function () {
    assertEquals('VARCHAR(190)', S::sqlType('string'));
    assertEquals('DECIMAL(12,2)', S::sqlType('decimal'));
    assertContains('TINYINT(1)', S::sqlType('bool'));
    assertEquals('VARCHAR(190)', S::sqlType('desconocido')); // fallback
});

it('mapea tipos de input', function () {
    assertEquals('number', S::inputType('decimal'));
    assertEquals('textarea', S::inputType('text'));
    assertEquals('checkbox', S::inputType('bool'));
    assertEquals('datetime-local', S::inputType('datetime'));
});

it('parsea la especificación de campos', function () {
    $f = S::parseFields('nombre:string precio:decimal activo:bool sintipo');
    assertEquals('string', $f['nombre']);
    assertEquals('decimal', $f['precio']);
    assertEquals('bool', $f['activo']);
    assertEquals('string', $f['sintipo']); // default
});

it('isValidType reconoce los tipos soportados', function () {
    assertTrue(S::isValidType('datetime'));
    assertFalse(S::isValidType('blob'));
});
