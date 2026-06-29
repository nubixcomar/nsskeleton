<?php

declare(strict_types=1);

use App\Services\AiConnector;

group('AiConnector::withSystem');

it('no agrega system si el prompt está vacío', function () {
    $m = AiConnector::withSystem([['role' => 'user', 'content' => 'hola']], '');
    assertEquals(1, count($m));
    assertEquals('user', $m[0]['role']);
});

it('antepone system cuando hay prompt y no existe uno', function () {
    $m = AiConnector::withSystem([['role' => 'user', 'content' => 'hola']], 'Sos un asistente.');
    assertEquals(2, count($m));
    assertEquals('system', $m[0]['role']);
    assertEquals('Sos un asistente.', $m[0]['content']);
});

it('no duplica system si ya hay uno', function () {
    $m = AiConnector::withSystem(
        [['role' => 'system', 'content' => 'X'], ['role' => 'user', 'content' => 'hola']],
        'Otro'
    );
    assertEquals(2, count($m));
    assertEquals('X', $m[0]['content']);
});
