<?php

declare(strict_types=1);

use App\Services\Totp;

group('Totp (F1) — vectores RFC 6238');

// Secreto "12345678901234567890" en base32.
$secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

it('código correcto en T=59 (vector RFC)', function () use ($secret) {
    assertEquals('287082', Totp::code($secret, 59));
});

it('código correcto en T=1111111109 (vector RFC)', function () use ($secret) {
    assertEquals('081804', Totp::code($secret, 1111111109));
});

it('verify acepta el código de su propia ventana', function () use ($secret) {
    $t = 1234567890;
    $code = Totp::code($secret, $t);
    assertTrue(Totp::verify($secret, $code, $t));
});

it('verify rechaza un código equivocado', function () use ($secret) {
    assertFalse(Totp::verify($secret, '000000', 59));
});

it('verify rechaza formato inválido', function () use ($secret) {
    assertFalse(Totp::verify($secret, 'abc', 59));
    assertFalse(Totp::verify($secret, '12345', 59));
});

it('base32 round-trip', function () {
    $raw = '12345678901234567890';
    assertEquals($raw, Totp::base32decode(Totp::base32encode($raw)));
});

it('generateSecret produce base32 usable', function () {
    $s = Totp::generateSecret();
    assertTrue(strlen($s) >= 16);
    $code = Totp::code($s, 100);
    assertTrue(Totp::verify($s, $code, 100));
});
