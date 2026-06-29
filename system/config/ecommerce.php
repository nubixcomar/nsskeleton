<?php

declare(strict_types=1);

/**
 * Registro de plataformas de ecommerce que el conector genérico sabe consumir.
 *
 * Esta es la "información lista al instalar": el skeleton ya trae descritas las
 * principales APIs de tiendas (auth, base URL, campos de credenciales y doc) para
 * que cualquier proyecto pueda conectarse sin investigar desde cero.
 *
 * Conector:  App\Services\Ecommerce\StoreConnectorFactory
 * Contrato:  App\Services\Ecommerce\StoreConnector
 * Skill IA:  agentic/skills/ecommerce-integration  (+ nubixstore-api para nubixstore)
 *
 * Las CREDENCIALES reales NO van acá: se guardan cifradas en `settings`
 * (grupo `ecommerce`, vía Settings::setSecret) y las lee el factory en runtime.
 * Acá solo se describe el ESQUEMA de cada plataforma.
 *
 * Cada `credentials` lista los campos esperados:  campo => [label, secret(bool)]
 */

return [
    // Plataforma activa por defecto (nubixstore es el caso de uso primario del skeleton).
    'default' => 'nubixstore',

    'platforms' => [

        'nubixstore' => [
            'label'       => 'nubixstore',
            'driver'      => \App\Services\Ecommerce\NubixstoreConnector::class,
            'auth'        => 'token',     // POST /usuarios/token → Bearer
            'base_hint'   => 'https://www.mi-tienda.com   (sin /api)',
            'api_version' => \App\Services\Ecommerce\NubixstoreConnector::API_MANUAL_VERSION,
            'docs'        => 'agentic/knowledge/nubixstore/manual-api-nubixstore.md',
            'credentials' => [
                'base_url' => ['label' => 'URL de la tienda (sin /api)', 'secret' => false],
                'user'     => ['label' => 'Usuario API (USER)',          'secret' => false],
                'password' => ['label' => 'Contraseña API (PASSWORD)',   'secret' => true],
                'api_key'  => ['label' => 'API Key (API_KEY)',           'secret' => true],
            ],
        ],

        'shopify' => [
            'label'       => 'Shopify',
            'driver'      => \App\Services\Ecommerce\ShopifyConnector::class,
            'auth'        => 'header_token', // X-Shopify-Access-Token
            'base_hint'   => 'https://{shop}.myshopify.com/admin/api/{version}',
            'api_version' => '2024-04',
            'docs'        => 'https://shopify.dev/docs/api/admin-rest',
            'credentials' => [
                'shop'         => ['label' => 'Shop (subdominio myshopify)', 'secret' => false],
                'access_token' => ['label' => 'Admin API access token',      'secret' => true],
                'api_version'  => ['label' => 'Versión de API (opcional)',    'secret' => false],
            ],
        ],

        'tiendanube' => [
            'label'       => 'Tienda Nube / Nuvemshop',
            'driver'      => \App\Services\Ecommerce\TiendaNubeConnector::class,
            'auth'        => 'bearer',  // Authentication: bearer {token}
            'base_hint'   => 'https://api.tiendanube.com/v1/{store_id}',
            'api_version' => 'v1',
            'docs'        => 'https://tiendanube.github.io/api-documentation/',
            'credentials' => [
                'store_id'     => ['label' => 'Store ID',                'secret' => false],
                'access_token' => ['label' => 'Access token',           'secret' => true],
                'user_agent'   => ['label' => 'User-Agent (tu app)',    'secret' => false],
            ],
        ],

        'woocommerce' => [
            'label'       => 'WooCommerce',
            'driver'      => \App\Services\Ecommerce\WooCommerceConnector::class,
            'auth'        => 'basic',   // consumer_key:consumer_secret
            'base_hint'   => 'https://{site}/wp-json/wc/v3',
            'api_version' => 'wc/v3',
            'docs'        => 'https://woocommerce.github.io/woocommerce-rest-api-docs/',
            'credentials' => [
                'site'            => ['label' => 'URL del sitio WordPress', 'secret' => false],
                'consumer_key'    => ['label' => 'Consumer key (ck_...)',   'secret' => true],
                'consumer_secret' => ['label' => 'Consumer secret (cs_...)', 'secret' => true],
            ],
        ],

        'magento' => [
            'label'       => 'Magento 2 / Adobe Commerce',
            'driver'      => \App\Services\Ecommerce\MagentoConnector::class,
            'auth'        => 'bearer',  // Integration access token
            'base_hint'   => 'https://{site}/rest/{store}/V1',
            'api_version' => 'V1',
            'docs'        => 'https://developer.adobe.com/commerce/webapi/rest/',
            'credentials' => [
                'site'         => ['label' => 'URL del sitio Magento',   'secret' => false],
                'access_token' => ['label' => 'Integration access token', 'secret' => true],
                'store_code'   => ['label' => 'Store code (default)',     'secret' => false],
            ],
        ],
    ],

    // Timeout por defecto (segundos) de las requests del conector.
    'timeout' => 20,
];
