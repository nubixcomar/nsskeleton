<?php

declare(strict_types=1);

use App\Services\AiConnector;

group('AiConnector::parseSseLine (streaming)');

it('extrae el token de una línea data: válida', function () {
    $line = 'data: {"choices":[{"delta":{"content":"Hola"}}]}';
    assertEquals('Hola', AiConnector::parseSseLine($line));
});

it('devuelve null en [DONE]', function () {
    assertNull(AiConnector::parseSseLine('data: [DONE]'));
});

it('devuelve null en líneas vacías o comentarios', function () {
    assertNull(AiConnector::parseSseLine(''));
    assertNull(AiConnector::parseSseLine(': keep-alive'));
});

it('devuelve null si el delta no trae content', function () {
    assertNull(AiConnector::parseSseLine('data: {"choices":[{"delta":{}}]}'));
});

it('acumula varios tokens en orden', function () {
    $tokens = [];
    foreach ([
        'data: {"choices":[{"delta":{"content":"Hola"}}]}',
        'data: {"choices":[{"delta":{"content":" mundo"}}]}',
        'data: [DONE]',
    ] as $line) {
        $t = AiConnector::parseSseLine($line);
        if ($t !== null) {
            $tokens[] = $t;
        }
    }
    assertEquals('Hola mundo', implode('', $tokens));
});
