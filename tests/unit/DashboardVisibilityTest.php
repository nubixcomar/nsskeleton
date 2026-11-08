<?php

declare(strict_types=1);

/**
 * PL-002/F3 — Dashboard abierto con widgets filtrados por permiso (BR-001 CS1, caso borde 2).
 * Sin DB: lógica pura de bloques + parciales renderizados con los datos en null.
 */

use App\Services\Dashboard;
use Core\View;

group('Dashboard · widgets por permiso (PL-002/F3)');

$all = ['alerts', 'kpis', 'charts', 'feed', 'recent', 'modules'];

it('viewer (solo dashboard.view) ve KPIs, gráficos, feed y módulos; no alertas ni recientes', function () use ($all) {
    $can = static fn (string $p): bool => $p === 'dashboard.view';
    assertEquals(['kpis', 'charts', 'feed', 'modules'], Dashboard::visibleBlocks($all, $can));
});

it('sin ningún permiso quedan solo los bloques abiertos', function () use ($all) {
    $none = static fn (string $p): bool => false;
    assertEquals(['feed', 'modules'], Dashboard::visibleBlocks($all, $none));
});

it('recent aparece con audit.view o con cron.manage; alerts con logs.view', function () use ($all) {
    $audit = static fn (string $p): bool => $p === 'audit.view';
    assertTrue(in_array('recent', Dashboard::visibleBlocks($all, $audit), true));
    $cron = static fn (string $p): bool => $p === 'cron.manage';
    assertTrue(in_array('recent', Dashboard::visibleBlocks($all, $cron), true));
    $logs = static fn (string $p): bool => $p === 'logs.view';
    assertTrue(in_array('alerts', Dashboard::visibleBlocks($all, $logs), true));
});

it('respeta el orden del preset y deja pasar bloques desconocidos (de la app)', function () {
    $can = static fn (string $p): bool => true;
    assertEquals(['charts', 'mi_bloque', 'kpis'], Dashboard::visibleBlocks(['charts', 'mi_bloque', 'kpis'], $can));
});

it('recent: sin permisos no renderiza nada; con uno solo muestra esa tarjeta', function () {
    assertEquals('', trim(View::partial('partials/dashboard/recent', ['recentAudit' => null, 'recentJobs' => null])));

    $onlyJobs = View::partial('partials/dashboard/recent', ['recentAudit' => null, 'recentJobs' => []]);
    assertTrue(str_contains($onlyJobs, 'Jobs recientes'));
    assertFalse(str_contains($onlyJobs, 'Actividad reciente'), 'sin audit.view no debe mostrar la auditoría');

    $onlyAudit = View::partial('partials/dashboard/recent', ['recentAudit' => [], 'recentJobs' => null]);
    assertTrue(str_contains($onlyAudit, 'Actividad reciente'));
    assertFalse(str_contains($onlyAudit, 'Jobs recientes'));
});

it('feed: sin logs.view muestra novedades pero no el estado del sistema', function () {
    $nov = [['tag' => 'Nuevo', 'color' => 'emerald', 'title' => 'T', 'text' => 'x']];
    $html = View::partial('partials/dashboard/feed', ['novedades' => $nov, 'health' => null]);
    assertTrue(str_contains($html, 'Novedades'));
    assertFalse(str_contains($html, 'Estado del sistema'));
    assertFalse(str_contains($html, 'Disco libre'));
});
