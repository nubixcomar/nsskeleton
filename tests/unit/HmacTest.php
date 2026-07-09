<?php

declare(strict_types=1);

use Core\Security\Hmac;
use Core\Webhooks\SignatureVerifier;

/**
 * Extracción genérica — Core\Security\Hmac (firma de payloads).
 * Converge con Webhooks\SignatureVerifier (que ahora usa esta primitiva).
 */

group('Hmac (firma de payloads)');

it('sign/verify de un string (hex y con prefijo)', function () {
    $data = 'payload-importante';
    $secret = 'k3y';
    $sig = Hmac::sign($data, $secret);
    assertTrue(Hmac::verify($data, $sig, $secret));
    assertTrue(Hmac::verify($data, 'sha256=' . $sig, $secret), 'acepta prefijo sha256=');
    assertFalse(Hmac::verify($data . 'x', $sig, $secret), 'payload alterado');
    assertFalse(Hmac::verify($data, $sig, 'otro'), 'secret incorrecto');
});

it('equals es constant-time y rechaza vacíos', function () {
    assertTrue(Hmac::equals('abc', 'abc'));
    assertFalse(Hmac::equals('abc', 'abd'));
    assertFalse(Hmac::equals('', ''));
});

it('firma de array es independiente del orden de las claves', function () {
    $secret = 's';
    $a = ['id' => 1, 'total' => 100, 'items' => [['sku' => 'x', 'qty' => 2]]];
    $b = ['total' => 100, 'items' => [['qty' => 2, 'sku' => 'x']], 'id' => 1];
    $sigA = Hmac::signArray($a, $secret);
    assertEquals($sigA, Hmac::signArray($b, $secret), 'misma firma pese a distinto orden');
    assertTrue(Hmac::verifyArray($b, $sigA, $secret));
});

it('verifyArray rechaza datos alterados', function () {
    $secret = 's';
    $cart = ['total' => 100];
    $sig = Hmac::signArray($cart, $secret);
    assertFalse(Hmac::verifyArray(['total' => 999], $sig, $secret));
});

it('signBase64 produce firma verificable manualmente', function () {
    $b64 = Hmac::signBase64('x', 'k');
    $expected = base64_encode(hash_hmac('sha256', 'x', 'k', true));
    assertEquals($expected, $b64);
});

it('SignatureVerifier converge con Hmac (misma firma base)', function () {
    $payload = '{"a":1}';
    $secret = 'k';
    // La firma del verifier es "sha256=" + Hmac::sign.
    assertEquals('sha256=' . Hmac::sign($payload, $secret), SignatureVerifier::sign($payload, $secret));
    assertTrue(SignatureVerifier::verify($payload, Hmac::sign($payload, $secret), $secret));
});
