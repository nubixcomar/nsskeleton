<?php

declare(strict_types=1);

use App\Services\GlobalSearch;

group('GlobalSearch (E5) — unit');

it('deriva la tabla del path', function () {
    assertEquals('contactos', GlobalSearch::tableFromPath('/admin/contactos'));
    assertEquals('notas', GlobalSearch::tableFromPath('/admin/notas/'));
});

it('rechaza paths inseguros', function () {
    assertEquals('', GlobalSearch::tableFromPath('/admin/x; DROP TABLE y'));
});

it('búsqueda vacía devuelve sin grupos', function () {
    assertEquals(0, count(GlobalSearch::search('   ')));
});
