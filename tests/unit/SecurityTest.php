<?php

declare(strict_types=1);

use Core\Security;
use Core\View;

group('Security (cabeceras)');

it('incluye nosniff y SAMEORIGIN', function () {
    $h = Security::headers();
    assertEquals('nosniff', $h['X-Content-Type-Options']);
    assertEquals('SAMEORIGIN', $h['X-Frame-Options']);
});

it('incluye CSP, Referrer-Policy y Permissions-Policy', function () {
    $h = Security::headers();
    assertTrue(isset($h['Content-Security-Policy']));
    assertTrue(isset($h['Referrer-Policy']));
    assertTrue(isset($h['Permissions-Policy']));
});

it('HSTS solo se agrega en HTTPS', function () {
    assertFalse(isset(Security::headers(false)['Strict-Transport-Security']));
    assertTrue(isset(Security::headers(true)['Strict-Transport-Security']));
});

group('Páginas de error');

it('la vista 404 existe y contiene "404"', function () {
    assertTrue(View::exists('errors/404'));
    assertContains('404', View::partial('errors/404', ['path' => '/x']));
});

it('la vista 500 existe y contiene "500"', function () {
    assertTrue(View::exists('errors/500'));
    assertContains('500', View::partial('errors/500', ['debug' => false, 'detail' => '']));
});

it('500 en debug muestra el detalle; sin debug lo oculta', function () {
    $conDebug = View::partial('errors/500', ['debug' => true, 'detail' => 'TRACE-XYZ']);
    $sinDebug = View::partial('errors/500', ['debug' => false, 'detail' => 'TRACE-XYZ']);
    assertContains('TRACE-XYZ', $conDebug);
    assertTrue(!str_contains($sinDebug, 'TRACE-XYZ'));
});

it('render con layout error devuelve una Response', function () {
    $r = View::render('errors/404', ['path' => '/x'], 'layouts/error', 404);
    assertTrue($r instanceof \Core\Response);
});
