<?php

declare(strict_types=1);

use App\Services\AiConnector;
use App\Services\Http;
use App\Services\SmtpMailer;

group('SmtpMailer (manejo de error)');

it('falla sin lanzar excepción si el host no responde', function () {
    $m = new SmtpMailer('127.0.0.1', 2, 'u', 'p', 'none', 'from@x.com', 'Test', 2);
    $r = $m->send('dest@x.com', 'Asunto áéí', '<b>hola</b>');
    assertFalse($r['ok']);
    assertTrue($r['error'] !== '');
});

group('AiConnector + Http');

it('providers incluye openai y deepseek', function () {
    $p = AiConnector::providers();
    assertTrue(in_array('openai', $p, true) && in_array('deepseek', $p, true));
});
it('config() resiliente: provider default openai', function () {
    assertEquals('openai', AiConnector::config()['provider']);
});
it('config() devuelve un modelo por defecto', fn () => assertTrue(AiConnector::config()['model'] !== ''));
it('chat() sin API key → ok=false con mensaje de key', function () {
    // Solo es válido si no hay key configurada en el entorno de test.
    if (AiConnector::config()['api_key'] !== '') {
        return 'skip';
    }
    $r = AiConnector::chat([['role' => 'user', 'content' => 'hola']]);
    assertFalse($r['ok']);
    assertContains('key', strtolower($r['error']));
});
it('Http::postJson a host muerto → ok=false sin excepción', function () {
    $r = Http::postJson('http://127.0.0.1:2/x', ['a' => 1], [], 2);
    assertFalse($r['ok']);
});
