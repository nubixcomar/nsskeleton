<?php

declare(strict_types=1);

use Core\Money;

/**
 * Extracción genérica — Core\Money (multi-moneda, aritmética en centavos).
 */

group('Money');

it('of() convierte unidades mayores a centavos', function () {
    assertEquals(123450, Money::of('1234.50', 'ARS')->minor);
    assertEquals(100, Money::of(1, 'USD')->minor);
    assertEquals(150, Money::of(1.5, 'USD')->minor);
});

it('decimales por divisa (ARS/USD=2, CLP/JPY=0)', function () {
    assertEquals(2, Money::decimals('ARS'));
    assertEquals(0, Money::decimals('CLP'));
    assertEquals(0, Money::decimals('JPY'));
    // CLP sin decimales: 1000 pesos = 1000 en minor.
    assertEquals(1000, Money::of(1000, 'CLP')->minor);
});

it('suma y resta seguras en la misma divisa', function () {
    $a = Money::ofMinor(100, 'USD');
    $b = Money::ofMinor(250, 'USD');
    assertEquals(350, $a->add($b)->minor);
    assertEquals(150, $b->subtract($a)->minor);
});

it('evita el error de float 0.1 + 0.2', function () {
    $r = Money::of('0.1', 'USD')->add(Money::of('0.2', 'USD'));
    assertEquals(30, $r->minor);
    assertEquals(0.3, $r->toMajor());
});

it('operar distinta divisa lanza excepción', function () {
    try {
        Money::ofMinor(100, 'USD')->add(Money::ofMinor(100, 'ARS'));
        assertTrue(false, 'debía lanzar');
    } catch (\InvalidArgumentException $e) {
        assertContains('divisa', $e->getMessage());
    }
});

it('multiply redondea a la unidad mínima', function () {
    // 3 x 0.333... ≈ 1.00
    $unit = Money::of('0.33', 'USD'); // 33 centavos
    assertEquals(99, $unit->multiply(3)->minor);
    // impuesto 21%
    assertEquals(121, Money::ofMinor(100, 'USD')->multiply(1.21)->minor);
});

it('format aplica símbolo y separadores por divisa', function () {
    assertEquals('$ 1.234,50', Money::of('1234.50', 'ARS')->format());
    assertEquals('US$ 1,234.50', Money::of('1234.50', 'USD')->format());
    assertEquals('1.234,50', Money::of('1234.50', 'ARS')->format(['symbol' => false]));
    // CLP sin decimales.
    assertEquals('$ 1.000', Money::of(1000, 'CLP')->format());
});

it('helpers: zero/isZero/isNegative/equals', function () {
    assertTrue(Money::zero('USD')->isZero());
    assertTrue(Money::ofMinor(-5, 'USD')->isNegative());
    assertTrue(Money::ofMinor(100, 'USD')->equals(Money::of(1, 'USD')));
    assertFalse(Money::ofMinor(100, 'USD')->equals(Money::ofMinor(100, 'ARS')));
});
