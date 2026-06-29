<?php

declare(strict_types=1);

use App\Services\ModuleScaffold as S;

group('ModuleScaffold — relaciones (E1)');

it('reconoce el tipo fk', function () {
    assertTrue(S::isValidType('fk'));
    assertEquals('INT UNSIGNED', S::sqlType('fk'));
    assertEquals('select', S::inputType('fk'));
});

it('parseFields marca el campo fk', function () {
    $fields = S::parseFields('titulo:string cliente_id:fk:clientes');
    assertEquals('string', $fields['titulo']);
    assertEquals('fk', $fields['cliente_id']);
});

it('parseRelations extrae campo => tabla', function () {
    $rel = S::parseRelations('titulo:string cliente_id:fk:clientes producto_id:fk:productos');
    assertEquals('clientes', $rel['cliente_id']);
    assertEquals('productos', $rel['producto_id']);
});

it('parseRelations ignora campos no-fk', function () {
    $rel = S::parseRelations('titulo:string precio:decimal');
    assertEquals(0, count($rel));
});
