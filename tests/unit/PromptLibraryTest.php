<?php

declare(strict_types=1);

use App\Services\PromptLibrary as P;

group('PromptLibrary');

it('lista los prompts definidos', function () {
    assertTrue(in_array('resumen', P::names(), true));
    assertTrue(P::has('traducir'));
});

it('render reemplaza las variables {{var}}', function () {
    $r = P::render('resumen', ['texto' => 'HOLA MUNDO']);
    assertContains('HOLA MUNDO', $r);
    assertTrue(!str_contains($r, '{{texto}}'));
});

it('una variable faltante se reemplaza por vacío', function () {
    $r = P::render('traducir', ['idioma' => 'inglés']); // falta {{texto}}
    assertTrue(!str_contains($r, '{{texto}}'));
    assertContains('inglés', $r);
});

it('un prompt inexistente renderiza vacío', function () {
    assertEquals('', P::render('no-existe', ['x' => 1]));
});
