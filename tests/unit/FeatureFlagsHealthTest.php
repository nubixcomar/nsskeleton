<?php

declare(strict_types=1);

use App\Services\FeatureFlags;
use App\Services\Health;

group('FeatureFlags');

it('expone los flags de config con sus defaults', function () {
    $all = FeatureFlags::all();
    assertTrue(array_key_exists('maintenance_banner', $all));
    assertFalse($all['maintenance_banner']); // default false
    assertTrue($all['exportar_listados']);   // default true
});

it('enabled() respeta el default', function () {
    assertFalse(FeatureFlags::enabled('maintenance_banner'));
    assertFalse(FeatureFlags::enabled('flag_inexistente'));
});

group('Health');

it('summary trae status, version y db', function () {
    $s = Health::summary();
    assertTrue(in_array($s['status'], ['ok', 'degraded'], true));
    assertTrue(isset($s['version']));
    assertTrue(array_key_exists('db', $s));
});

it('full agrega php, cola y disco', function () {
    $f = Health::full();
    assertContains('.', $f['php']); // versión PHP tipo 8.2.x
    assertTrue(array_key_exists('email_queue_pending', $f));
    assertTrue(array_key_exists('disk_free_mb', $f));
    assertTrue(array_key_exists('last_backup', $f));
});
