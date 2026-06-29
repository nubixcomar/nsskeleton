<?php

declare(strict_types=1);

use App\Services\Paginator;

group('Paginator::meta (math pura)');

it('total 0 → 1 página, from/to 0, sin prev/next', function () {
    $m = Paginator::meta(0, 1, 15);
    assertEquals(1, $m['pages']);
    assertEquals(0, $m['from']);
    assertEquals(0, $m['to']);
    assertFalse($m['hasPrev']);
    assertFalse($m['hasNext']);
});

it('45 items, 15 por página → 3 páginas; página 1 from 1 to 15 hasNext', function () {
    $m = Paginator::meta(45, 1, 15);
    assertEquals(3, $m['pages']);
    assertEquals(1, $m['from']);
    assertEquals(15, $m['to']);
    assertTrue($m['hasNext']);
    assertFalse($m['hasPrev']);
});

it('última página: from 31 to 45, sin next', function () {
    $m = Paginator::meta(45, 3, 15);
    assertEquals(3, $m['page']);
    assertEquals(31, $m['from']);
    assertEquals(45, $m['to']);
    assertFalse($m['hasNext']);
    assertTrue($m['hasPrev']);
});

it('clampa una página fuera de rango', function () {
    $m = Paginator::meta(45, 99, 15);
    assertEquals(3, $m['page']);
});

it('total menor a perPage → 1 página, to = total', function () {
    $m = Paginator::meta(10, 1, 15);
    assertEquals(1, $m['pages']);
    assertEquals(10, $m['to']);
});
