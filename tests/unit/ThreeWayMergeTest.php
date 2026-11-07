<?php

declare(strict_types=1);

/**
 * Merge 3-way (diff3) en PHP puro — v1.20. Sin DB ni red.
 */

use App\Services\ThreeWayMerge;

group('ThreeWayMerge (diff3 por líneas)');

$L = static fn (string ...$lines): string => implode("\n", $lines) . "\n";

it('cambios en zonas distintas se combinan solos', function () use ($L) {
    $base   = $L('a', 'b', 'c', 'd', 'e', 'f', 'g');
    $ours   = $L('a', 'B-app', 'c', 'd', 'e', 'f', 'g');      // la app cambió b
    $theirs = $L('a', 'b', 'c', 'd', 'e', 'F-core', 'g');     // el core cambió f
    $r = ThreeWayMerge::merge($base, $ours, $theirs);
    assertTrue($r['clean']);
    assertEquals(0, $r['conflicts']);
    assertEquals($L('a', 'B-app', 'c', 'd', 'e', 'F-core', 'g'), $r['content']);
});

it('inserciones y borrados de ambos lados en zonas distintas', function () use ($L) {
    $base   = $L('uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis');
    $ours   = $L('cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis');   // agrega arriba
    $theirs = $L('uno', 'dos', 'tres', 'cuatro', 'seis', 'siete');           // borra cinco, agrega siete
    $r = ThreeWayMerge::merge($base, $ours, $theirs);
    assertTrue($r['clean']);
    assertEquals($L('cero', 'uno', 'dos', 'tres', 'cuatro', 'seis', 'siete'), $r['content']);
});

it('mismo cambio en ambos lados no es conflicto', function () use ($L) {
    $base = $L('x', 'y', 'z');
    $both = $L('x', 'Y!', 'z');
    $r = ThreeWayMerge::merge($base, $both, $L('x', 'Y!', 'z', 'w'));
    assertTrue($r['clean']);
    assertEquals($L('x', 'Y!', 'z', 'w'), $r['content']);
});

it('cambios distintos en la misma línea generan marcadores', function () use ($L) {
    $base   = $L('a', 'b', 'c');
    $ours   = $L('a', 'b-app', 'c');
    $theirs = $L('a', 'b-core', 'c');
    $r = ThreeWayMerge::merge($base, $ours, $theirs);
    assertFalse($r['clean']);
    assertEquals(1, $r['conflicts']);
    $expected = "a\n"
        . ThreeWayMerge::MARK_OURS . "\nb-app\n"
        . ThreeWayMerge::MARK_BASE . "\nb\n"
        . ThreeWayMerge::MARK_SEP . "\nb-core\n"
        . ThreeWayMerge::MARK_THEIRS . "\nc\n";
    assertEquals($expected, $r['content']);
});

it('atajos: un solo lado cambió', function () use ($L) {
    $base = $L('1', '2');
    assertEquals($L('1', '2', '3'), ThreeWayMerge::merge($base, $base, $L('1', '2', '3'))['content']);
    assertEquals($L('0', '1', '2'), ThreeWayMerge::merge($base, $L('0', '1', '2'), $base)['content']);
});

it('conserva CRLF y archivo sin salto final', function () {
    $base   = "a\r\nb\r\nx\r\nc";
    $ours   = "a\r\nB\r\nx\r\nc";
    $theirs = "a\r\nb\r\nx\r\nC";
    $r = ThreeWayMerge::merge($base, $ours, $theirs);
    assertTrue($r['clean']);
    assertEquals("a\r\nB\r\nx\r\nC", $r['content']);
});

it('cambios en líneas contiguas chocan (igual que git)', function () use ($L) {
    $r = ThreeWayMerge::merge($L('a', 'b', 'c'), $L('a', 'B', 'c'), $L('a', 'b', 'C'));
    assertFalse($r['clean']);
    assertEquals(1, $r['conflicts']);
});

it('archivo PHP realista: método nuevo del core + ajuste local', function () {
    $base = "<?php\nclass X\n{\n    public function a(): int\n    {\n        return 1;\n    }\n}\n";
    $ours = "<?php\nclass X\n{\n    public function a(): int\n    {\n        return 42; // ajuste local\n    }\n}\n";
    $theirs = "<?php\nclass X\n{\n    public function a(): int\n    {\n        return 1;\n    }\n\n    public function b(): int\n    {\n        return 2;\n    }\n}\n";
    $r = ThreeWayMerge::merge($base, $ours, $theirs);
    assertTrue($r['clean']);
    assertTrue(str_contains($r['content'], 'return 42; // ajuste local'));
    assertTrue(str_contains($r['content'], 'public function b(): int'));
});

it('matches (Myers) alinea bien un diff grande', function () {
    $x = [];
    for ($i = 0; $i < 600; $i++) {
        $x[] = "l{$i}\n";
    }
    $y = $x;
    array_splice($y, 300, 5, ["nuevo\n"]);   // reemplaza 5 líneas por 1
    unset($y[10]);                            // borra una
    $y = array_values($y);
    $m = \App\Services\ThreeWayMerge::matches($x, $y);
    assertEquals(600 - 5 - 1, count($m));
    // Todas las parejas son iguales y crecientes.
    $prev = -1;
    foreach ($m as $i => $j) {
        assertEquals($x[$i], $y[$j]);
        assertTrue($j > $prev);
        $prev = $j;
    }
});

it('detecta binarios', function () {
    assertTrue(ThreeWayMerge::isBinary("PNG\0\0data"));
    assertFalse(ThreeWayMerge::isBinary("texto común\n"));
});
