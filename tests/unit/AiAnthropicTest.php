<?php

declare(strict_types=1);

use App\Services\AiConnector;

group('AiConnector — proveedor Anthropic (I1)');

it('expone anthropic en la lista de proveedores', function () {
    assertTrue(in_array('anthropic', AiConnector::providers(), true));
    assertTrue(in_array('openai', AiConnector::providers(), true));
});

it('splitSystem separa el system del resto', function () {
    [$system, $rest] = AiConnector::splitSystem([
        ['role' => 'system', 'content' => 'Sos útil'],
        ['role' => 'user', 'content' => 'Hola'],
    ]);
    assertEquals('Sos útil', $system);
    assertCount(1, $rest);
    assertEquals('user', $rest[0]['role']);
});

it('buildRequest arma la request OpenAI-style', function () {
    $cfg = ['style' => 'openai', 'base' => 'https://api.openai.com/v1', 'model' => 'gpt-4o-mini', 'api_key' => 'k'];
    $req = AiConnector::buildRequest($cfg, [['role' => 'user', 'content' => 'hola']]);
    assertContains('/chat/completions', $req['url']);
    assertEquals('Authorization: Bearer k', $req['headers'][0]);
    assertTrue(isset($req['payload']['messages']));
});

it('buildRequest arma la request Anthropic-style (system aparte + max_tokens + x-api-key)', function () {
    $cfg = ['style' => 'anthropic', 'base' => 'https://api.anthropic.com/v1', 'model' => 'claude-haiku-4-5-20251001', 'api_key' => 'k'];
    $req = AiConnector::buildRequest($cfg, [
        ['role' => 'system', 'content' => 'Sos un asistente'],
        ['role' => 'user', 'content' => 'hola'],
    ], ['max_tokens' => 256]);
    assertContains('/messages', $req['url']);
    assertEquals('Sos un asistente', $req['payload']['system']);
    assertEquals(256, $req['payload']['max_tokens']);
    assertCount(1, $req['payload']['messages']); // el system NO va como mensaje
    assertContains('x-api-key: k', $req['headers'][0]);
    assertContains('anthropic-version', $req['headers'][1]);
});

it('extractContent según estilo', function () {
    assertEquals('hola', AiConnector::extractContent('anthropic', ['content' => [['text' => 'hola']]]));
    assertEquals('chau', AiConnector::extractContent('openai', ['choices' => [['message' => ['content' => 'chau']]]]));
});

it('parseSseAnthropic extrae text de content_block_delta', function () {
    $line = 'data: {"type":"content_block_delta","delta":{"type":"text_delta","text":"Hola"}}';
    assertEquals('Hola', AiConnector::parseSseAnthropic($line));
    assertNull(AiConnector::parseSseAnthropic('data: {"type":"message_start"}'));
    // el dispatcher elige según estilo
    assertEquals('Hola', AiConnector::parseSseToken('anthropic', $line));
});
