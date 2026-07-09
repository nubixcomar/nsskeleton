<?php

declare(strict_types=1);

use App\Services\Webhook;
use Core\Webhooks\SignatureVerifier;

/**
 * v1.18 #2 — Verificación de firma de webhooks entrantes (hallazgo 1.10).
 */

group('Webhooks · SignatureVerifier');

it('verifica una firma simple válida (con y sin prefijo sha256=)', function () {
    $payload = '{"event":"paid","id":123}';
    $secret = 's3cr3t';
    $sig = SignatureVerifier::sign($payload, $secret); // sha256=<hex>
    assertTrue(SignatureVerifier::verify($payload, $sig, $secret));
    // También acepta el hex crudo.
    $hex = substr($sig, strlen('sha256='));
    assertTrue(SignatureVerifier::verify($payload, $hex, $secret));
});

it('rechaza firma inválida o payload alterado', function () {
    $secret = 's3cr3t';
    $sig = SignatureVerifier::sign('{"a":1}', $secret);
    assertFalse(SignatureVerifier::verify('{"a":2}', $sig, $secret), 'payload alterado');
    assertFalse(SignatureVerifier::verify('{"a":1}', $sig, 'otro-secret'), 'secret incorrecto');
    assertFalse(SignatureVerifier::verify('{"a":1}', '', $secret), 'firma vacía');
});

it('round-trip con Webhook::sign del core (salientes ↔ entrantes)', function () {
    $payload = '{"ping":true}';
    $secret = 'abc123';
    $sig = Webhook::sign($secret, $payload); // firma que emite el core
    assertTrue(SignatureVerifier::verify($payload, $sig, $secret));
});

it('verifica firma con timestamp estilo Stripe (t=..,v1=..)', function () {
    $payload = '{"amount":100}';
    $secret = 'whsec_test';
    $ts = 1699999999;
    $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
    $header = "t={$ts},v1={$expected}";
    assertTrue(SignatureVerifier::verifyTimestamped($payload, $header, $secret, [
        'tolerance' => 0, // sin chequeo de ventana (ts fijo de test)
    ]));
});

it('rechaza timestamped fuera de la ventana de tolerancia (anti-replay)', function () {
    $payload = 'x';
    $secret = 'k';
    $ts = 1000;
    $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
    $header = "t={$ts},v1={$expected}";
    // now muy posterior + tolerancia chica → rechaza aunque la firma sea válida.
    assertFalse(SignatureVerifier::verifyTimestamped($payload, $header, $secret, [
        'tolerance' => 300, 'now' => 1000 + 1000,
    ]));
    // dentro de la ventana → acepta.
    assertTrue(SignatureVerifier::verifyTimestamped($payload, $header, $secret, [
        'tolerance' => 300, 'now' => 1000 + 100,
    ]));
});

it('soporta claves de header custom (ts_key/sig_key para Mercado Pago)', function () {
    $payload = 'id:123;ts:1700000000;';
    $secret = 'mp_secret';
    $ts = 1700000000;
    $tpl = '{payload}'; // MP arma su propio manifest; el template usa payload tal cual
    $expected = hash_hmac('sha256', $payload, $secret);
    $header = "ts={$ts}, v1={$expected}";
    assertTrue(SignatureVerifier::verifyTimestamped($payload, $header, $secret, [
        'ts_key' => 'ts', 'sig_key' => 'v1', 'template' => $tpl, 'tolerance' => 0,
    ]));
});
