<?php

declare(strict_types=1);

use App\Controllers\CronRunController;
use App\Services\CronMaster;
use App\Services\Settings;
use Core\Request;

/**
 * v1.19 fase 1 — Cron master (config + endpoint público). Portado de nsCentral.
 * Requiere MySQL (Settings vive en tabla); itDb aísla con rollback.
 */

group('Cron master · configuración');

itDb('deshabilitado por defecto', function () {
    // "Por defecto" = sin la clave guardada. La instancia local puede tener el cron master
    // activado de verdad: se quita la clave dentro de la transacción (itDb hace rollback),
    // así el test no depende de los datos de la DB y no toca la configuración real.
    \Core\Database::connection()->prepare('DELETE FROM settings WHERE `key` = ?')->execute(['cron.master_enabled']);
    Settings::flush();
    assertFalse(CronMaster::enabled());
});

itDb('setEnabled togglea el flag', function () {
    CronMaster::setEnabled(true);
    Settings::flush();
    assertTrue(CronMaster::enabled());
});

itDb('rotateSecret genera 64 hex y verifyKey valida constant-time', function () {
    $s = CronMaster::rotateSecret();
    assertEquals(64, strlen($s));
    Settings::flush();
    assertTrue(CronMaster::hasSecret());
    assertTrue(CronMaster::verifyKey($s));
    assertFalse(CronMaster::verifyKey('incorrecto'));
    assertFalse(CronMaster::verifyKey(''));
    assertFalse(CronMaster::verifyKey(null));
});

itDb('sin secreto, verifyKey siempre da false', function () {
    Settings::flush();
    assertFalse(CronMaster::verifyKey('cualquiera'));
});

itDb('ipAllowed: vacío = todas; con lista = solo listadas; filtra basura', function () {
    Settings::flush();
    assertTrue(CronMaster::ipAllowed('1.2.3.4'), 'lista vacía permite todas');
    CronMaster::setIpAllowlist("10.0.0.1, 203.0.113.5\nesto-no-es-ip");
    Settings::flush();
    assertCount(2, CronMaster::ipAllowlist());
    assertTrue(CronMaster::ipAllowed('10.0.0.1'));
    assertTrue(CronMaster::ipAllowed('203.0.113.5'));
    assertFalse(CronMaster::ipAllowed('8.8.8.8'));
});

itDb('url incluye /cron/run?key=', function () {
    CronMaster::rotateSecret();
    Settings::flush();
    assertContains('/cron/run?key=', CronMaster::url());
});

group('Cron master · endpoint /cron/run');

/** Deja el runner en no-op vía throttle (evita ejecutar tareas reales en el test). */
$muteRunner = static function (): void {
    Settings::set('cron.min_interval', '3600', 'cron');
    Settings::set('cron.last_tick', date('Y-m-d H:i:s'), 'cron');
    Settings::flush();
};

itDb('503 cuando está deshabilitado', function () {
    CronMaster::setEnabled(false);
    Settings::flush();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/cron/run';
    $_GET = [];
    $res = (new CronRunController())->run(Request::capture());
    assertEquals(503, $res->statusCode());
});

itDb('403 con secreto inválido', function () {
    CronMaster::setEnabled(true);
    CronMaster::rotateSecret();
    Settings::flush();
    $_GET = ['key' => 'incorrecto'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/cron/run?key=incorrecto';
    $res = (new CronRunController())->run(Request::capture());
    assertEquals(403, $res->statusCode());
    $_GET = [];
});

itDb('200 con secreto válido', function () use ($muteRunner) {
    CronMaster::setEnabled(true);
    $s = CronMaster::rotateSecret();
    $muteRunner();
    $_GET = ['key' => $s];
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/cron/run';
    $res = (new CronRunController())->run(Request::capture());
    assertEquals(200, $res->statusCode());
    assertContains('"ok":true', $res->body());
    $_GET = [];
});
