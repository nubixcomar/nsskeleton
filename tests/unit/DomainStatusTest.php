<?php

declare(strict_types=1);

use App\Services\DomainStatus;

/**
 * Extracción genérica — App\Services\DomainStatus (DNS + TLS).
 * Los probes se inyectan para no depender de la red.
 */

group('DomainStatus');

it('normalize quita esquema, path y puerto', function () {
    assertEquals('example.com', DomainStatus::normalize('https://example.com/path?x=1'));
    assertEquals('example.com', DomainStatus::normalize('EXAMPLE.com:443'));
    assertEquals('sub.example.com', DomainStatus::normalize('  http://sub.example.com/  '));
});

it('check() agrega DNS + TLS con probes inyectados (todo OK)', function () {
    $dns = static fn ($d) => ['resolved' => true, 'ips' => ['1.2.3.4']];
    $tls = static fn ($d) => ['valid' => true, 'expires' => '2027-01-01', 'issuer' => 'Lets Encrypt', 'days_left' => 200, 'error' => null];
    $r = DomainStatus::check('https://mi-dominio.com/x', $dns, $tls);
    assertEquals('mi-dominio.com', $r['domain']);
    assertTrue($r['dns']['resolved']);
    assertEquals('1.2.3.4', $r['dns']['ips'][0]);
    assertTrue($r['tls']['valid']);
    assertTrue($r['ok']);
});

it('ok=false si DNS no resuelve', function () {
    $dns = static fn ($d) => ['resolved' => false, 'ips' => []];
    $tls = static fn ($d) => ['valid' => true, 'expires' => null, 'issuer' => null, 'days_left' => null, 'error' => null];
    $r = DomainStatus::check('x.test', $dns, $tls);
    assertFalse($r['ok']);
});

it('ok=false si el TLS no es válido', function () {
    $dns = static fn ($d) => ['resolved' => true, 'ips' => ['1.2.3.4']];
    $tls = static fn ($d) => ['valid' => false, 'expires' => null, 'issuer' => null, 'days_left' => null, 'error' => 'expirado'];
    $r = DomainStatus::check('x.test', $dns, $tls);
    assertFalse($r['ok']);
    assertEquals('expirado', $r['tls']['error']);
});

it('probeDns real: localhost resuelve (o skip sin red)', function () {
    $r = DomainStatus::probeDns('localhost');
    // localhost siempre resuelve a 127.0.0.1 sin salir a la red.
    assertTrue($r['resolved']);
    assertTrue(in_array('127.0.0.1', $r['ips'], true) || $r['ips'] !== []);
});
