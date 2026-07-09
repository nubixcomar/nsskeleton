<?php

declare(strict_types=1);

use Core\Config;
use Core\Response;
use Core\Security;

/**
 * v1.16 #1/#2 — CSP configurable (env/config/presets) + override por-respuesta.
 * Derivado del hallazgo 1.1 de la auditoría de nsCheckout (CSP hardcodeada).
 *
 * Nota de aislamiento (hallazgo 1.7): cada test limpia el env y la caché de Config
 * que tocó, para no dejar residuo que afecte a otros tests.
 */

group('Seguridad · CSP configurable');

/** Helper: limpia claves CSP_* del entorno y la caché de config. */
$resetCsp = static function (): void {
    foreach ([
        'CSP_SCRIPT_SRC_EXTRA', 'CSP_STYLE_SRC_EXTRA', 'CSP_CONNECT_SRC_EXTRA',
        'CSP_FRAME_SRC_EXTRA', 'CSP_IMG_SRC_EXTRA', 'CSP_FONT_SRC_EXTRA',
        'CSP_WORKER_SRC_EXTRA', 'CSP_PRESETS',
    ] as $k) {
        unset($_ENV[$k]);
        putenv($k);
    }
    Config::flush();
};

it('CSP base mantiene default-src self (retrocompat)', function () use ($resetCsp) {
    $resetCsp();
    $csp = Security::csp();
    assertContains("default-src 'self'", $csp);
    assertContains("frame-ancestors 'self'", $csp);
    assertContains("form-action 'self'", $csp);
    $resetCsp();
});

it('env CSP_SCRIPT_SRC_EXTRA agrega el origen a script-src', function () use ($resetCsp) {
    $resetCsp();
    $_ENV['CSP_SCRIPT_SRC_EXTRA'] = 'https://ejemplo.test https://otro.test';
    Config::flush();
    $csp = Security::csp();
    assertContains('https://ejemplo.test', $csp);
    assertContains('https://otro.test', $csp);
    // Deben quedar en la directiva script-src, no en otra.
    assertTrue((bool) preg_match('/script-src [^;]*https:\/\/ejemplo\.test/', $csp), 'origen en script-src');
    $resetCsp();
});

it('env CSP_CONNECT_SRC_EXTRA acepta lista separada por comas', function () use ($resetCsp) {
    $resetCsp();
    $_ENV['CSP_CONNECT_SRC_EXTRA'] = 'https://api.a.test,https://api.b.test';
    Config::flush();
    $csp = Security::csp();
    assertTrue((bool) preg_match('/connect-src [^;]*https:\/\/api\.a\.test/', $csp));
    assertTrue((bool) preg_match('/connect-src [^;]*https:\/\/api\.b\.test/', $csp));
    $resetCsp();
});

it('preset mercadopago habilita SDK y frame-src (por env CSP_PRESETS)', function () use ($resetCsp) {
    $resetCsp();
    $_ENV['CSP_PRESETS'] = 'mercadopago';
    Config::flush();
    $csp = Security::csp();
    assertContains('https://sdk.mercadopago.com', $csp);
    assertContains('frame-src', $csp);
    assertTrue((bool) preg_match('/frame-src [^;]*mercadopago/', $csp), 'frame-src con dominios MP');
    $resetCsp();
});

it('sin presets ni extras NO aparece frame-src (retrocompat: solo se agrega si hay orígenes)', function () use ($resetCsp) {
    $resetCsp();
    $csp = Security::csp();
    assertTrue(!str_contains($csp, 'frame-src '), 'frame-src no debe aparecer sin orígenes');
    $resetCsp();
});

it('config/security.php define presets conocidos (mercadopago, stripe, recaptcha)', function () use ($resetCsp) {
    $resetCsp();
    $cfg = Config::load('security');
    $defs = $cfg['preset_definitions'] ?? [];
    assertTrue(isset($defs['mercadopago']), 'preset mercadopago');
    assertTrue(isset($defs['stripe']), 'preset stripe');
    assertTrue(isset($defs['recaptcha']), 'preset recaptcha');
    $resetCsp();
});

group('Seguridad · override por-respuesta');

it('Response::hasHeader es case-insensitive', function () {
    $r = Response::html('x');
    $r->header('Content-Security-Policy', "default-src 'none'");
    assertTrue($r->hasHeader('content-security-policy'));
    assertTrue($r->hasHeader('CONTENT-SECURITY-POLICY'));
    assertFalse($r->hasHeader('X-No-Existe'));
});

it('Response::withCsp setea la CSP de la respuesta', function () {
    $r = Response::json(['ok' => true])->withCsp("default-src 'self' https://mp.test");
    assertTrue($r->hasHeader('Content-Security-Policy'));
});

it('merge: si el controller ya seteó CSP, App no la pisaría (hasHeader=true)', function () {
    // Simula la condición que evalúa Core\App: solo setea si !hasHeader.
    $r = Response::html('x')->withCsp("default-src 'self' https://custom.test");
    // App recorrería Security::headers() y saltearía las ya presentes.
    $yaSeteada = $r->hasHeader('Content-Security-Policy');
    assertTrue($yaSeteada, 'la CSP del controller debe sobrevivir el merge');
});
