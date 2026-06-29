<?php

declare(strict_types=1);

use App\Services\Charts;

group('Charts');

it('bar genera 1 dataset y tipo bar', function () {
    $c = Charts::bar('c1', 'T', ['A', 'B'], [1, 2]);
    assertEquals('bar', $c['type']);
    assertCount(1, $c['datasets']);
});
it('bar es serializable a JSON', function () {
    $c = Charts::bar('c1', 'T', ['A'], [1]);
    assertTrue(json_encode($c) !== false);
});
it('doughnut asigna un color por dato', function () {
    $c = Charts::doughnut('c2', 'T', ['A', 'B', 'C'], [1, 2, 3]);
    assertCount(3, $c['datasets'][0]['backgroundColor']);
});
it('line marca fill y tensión', function () {
    $c = Charts::line('c3', 'T', ['A'], [1]);
    assertEquals('line', $c['type']);
    assertTrue($c['datasets'][0]['fill'] === true);
});
it('palette cicla colores', fn () => assertCount(10, Charts::palette(10)));
