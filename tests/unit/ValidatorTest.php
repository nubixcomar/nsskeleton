<?php

declare(strict_types=1);

use App\Services\Validator;
use App\Services\ModuleScaffold as S;

group('Validator (E2)');

it('required detecta vacío', function () {
    $errors = Validator::make(['nombre' => ''], ['nombre' => ['required']]);
    assertTrue(isset($errors['nombre']));
});

it('required pasa con valor', function () {
    $errors = Validator::make(['nombre' => 'Ana'], ['nombre' => ['required']]);
    assertEquals(0, count($errors));
});

it('email valida formato', function () {
    assertTrue(isset(Validator::make(['e' => 'no-es-mail'], ['e' => ['email']])['e']));
    assertEquals(0, count(Validator::make(['e' => 'a@b.com'], ['e' => ['email']])));
});

it('numeric e integer', function () {
    assertTrue(isset(Validator::make(['n' => 'abc'], ['n' => ['numeric']])['n']));
    assertEquals(0, count(Validator::make(['n' => '12.5'], ['n' => ['numeric']])));
    assertTrue(isset(Validator::make(['n' => '12.5'], ['n' => ['integer']])['n']));
    assertEquals(0, count(Validator::make(['n' => '12'], ['n' => ['integer']])));
});

it('un solo error por campo (el primero)', function () {
    $errors = Validator::make(['e' => ''], ['e' => ['required', 'email']]);
    assertContains('obligatorio', $errors['e']);
});

group('ModuleScaffold::parseRules (E2)');

it('deriva reglas del tipo y suma explícitas', function () {
    $rules = S::parseRules('precio:decimal stock:int email:string:required,email,unique nombre:string:required');
    assertEquals(['numeric'], $rules['precio']);
    assertEquals(['integer'], $rules['stock']);
    assertEquals(['required', 'email', 'unique'], $rules['email']);
    assertEquals(['required'], $rules['nombre']);
});

it('ignora reglas inválidas', function () {
    $rules = S::parseRules('x:string:required,foobar');
    assertEquals(['required'], $rules['x']);
});
