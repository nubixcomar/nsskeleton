<?php

declare(strict_types=1);

/**
 * Conector genérico de tiendas de ecommerce (capa system). No requiere DB ni red:
 * verifica el registro de plataformas, el factory y la resolución de bases/auth
 * de cada driver vía reflexión.
 */

use App\Services\Ecommerce\StoreConnector;
use App\Services\Ecommerce\StoreConnectorFactory;
use App\Services\Ecommerce\NubixstoreConnector;

group('Ecommerce — conector de tiendas');

/** Helper: invoca un método protegido de un conector. */
$call = static function (object $obj, string $method): mixed {
    $m = new ReflectionMethod($obj, $method);
    $m->setAccessible(true);
    return $m->invoke($obj);
};

it('el registro incluye las plataformas soportadas', function () {
    $p = StoreConnectorFactory::platforms();
    foreach (['nubixstore', 'shopify', 'tiendanube', 'woocommerce', 'magento'] as $k) {
        assertTrue(in_array($k, $p, true), "falta plataforma {$k}");
    }
});

it('la plataforma por defecto es nubixstore', function () {
    assertEquals('nubixstore', StoreConnectorFactory::defaultPlatform());
});

it('el factory crea un StoreConnector válido', function () {
    $c = StoreConnectorFactory::make('nubixstore', [
        'base_url' => 'https://demo.tienda.com', 'user' => 'u', 'password' => 'p', 'api_key' => 'k',
    ]);
    assertTrue($c instanceof StoreConnector);
    assertEquals('nubixstore', $c->platform());
});

it('el driver de nubixstore declara la versión del manual', function () {
    assertEquals('2.1', NubixstoreConnector::API_MANUAL_VERSION);
});

it('nubixstore arma la base con sufijo /api', function () use ($call) {
    $c = StoreConnectorFactory::make('nubixstore', ['base_url' => 'https://demo.tienda.com']);
    assertEquals('https://demo.tienda.com/api', $call($c, 'apiBase'));
});

it('shopify resuelve base y header de auth', function () use ($call) {
    $c = StoreConnectorFactory::make('shopify', ['shop' => 'mi-tienda', 'access_token' => 'tok']);
    assertEquals('https://mi-tienda.myshopify.com/admin/api/2024-04', $call($c, 'apiBase'));
    $h = $call($c, 'authHeaders');
    assertContains('X-Shopify-Access-Token: tok', implode("\n", $h));
});

it('tiendanube resuelve base con store_id', function () use ($call) {
    $c = StoreConnectorFactory::make('tiendanube', ['store_id' => '123', 'access_token' => 't']);
    assertEquals('https://api.tiendanube.com/v1/123', $call($c, 'apiBase'));
});

it('woocommerce usa Basic auth', function () use ($call) {
    $c = StoreConnectorFactory::make('woocommerce', ['site' => 'https://shop.com', 'consumer_key' => 'ck', 'consumer_secret' => 'cs']);
    assertEquals('https://shop.com/wp-json/wc/v3', $call($c, 'apiBase'));
    $h = implode("\n", $call($c, 'authHeaders'));
    assertContains('Authorization: Basic ' . base64_encode('ck:cs'), $h);
});

it('magento resuelve base /rest/{store}/V1', function () use ($call) {
    $c = StoreConnectorFactory::make('magento', ['site' => 'https://shop.com', 'access_token' => 't']);
    assertEquals('https://shop.com/rest/default/V1', $call($c, 'apiBase'));
});

it('una plataforma desconocida lanza InvalidArgumentException', function () {
    try {
        StoreConnectorFactory::make('inexistente', []);
        throw new RuntimeException('debió lanzar');
    } catch (InvalidArgumentException) {
        return; // ok
    }
});
