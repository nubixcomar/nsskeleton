# Conocimiento: APIs de ecommerce (genérico)

**Versión:** 1.0 — Conocimiento base para integrar nsSkeleton con cualquier plataforma de
tienda virtual. Fuente de verdad del skill `ecommerce-integration` y del agente
`ecommerce-integrator`.

> nsSkeleton trae un **conector genérico de tiendas** ya programado para que cualquier
> proyecto pueda conectarse a una API de ecommerce sin investigar desde cero. El caso de
> uso primario es **nubixstore** (ver [`../nubixstore/manual-api-nubixstore.md`](../nubixstore/manual-api-nubixstore.md)),
> pero el mismo contrato sirve para Shopify, Tienda Nube, WooCommerce, Magento y otras.

---

## 1. El conector en el stack por defecto (PHP MVC)

| Pieza | Ubicación |
|-------|-----------|
| Contrato | `App\Services\Ecommerce\StoreConnector` (interface) |
| Base compartida (HTTP/cURL, TLS, normalización) | `App\Services\Ecommerce\AbstractStoreConnector` |
| Drivers | `NubixstoreConnector`, `ShopifyConnector`, `TiendaNubeConnector`, `WooCommerceConnector`, `MagentoConnector` |
| Fábrica | `App\Services\Ecommerce\StoreConnectorFactory` |
| Registro de plataformas | `system/config/ecommerce.php` |

**Forma de respuesta (siempre, todas las plataformas):**

```php
['ok' => bool, 'status' => int, 'data' => mixed, 'error' => ?string]
```

`ok` = HTTP 2xx (y, si la plataforma marca error en el body, también lo considera). Nunca
lanza excepción en la request: los errores viajan en `error`.

**Operaciones genéricas del contrato:** `ping()`, `getProducts()`, `getProduct($id)`,
`getOrders()`, `getOrder($id)`, `getCustomers()`, y acceso crudo `get()/post()` para
cualquier endpoint propio de la plataforma.

```php
use App\Services\Ecommerce\StoreConnectorFactory;

// Tienda activa configurada (settings, grupo 'ecommerce', secretos cifrados):
$store = StoreConnectorFactory::fromSettings();

// O explícito (multi-tienda):
$store = StoreConnectorFactory::make('shopify', [
    'shop' => 'mi-tienda', 'access_token' => '***',
]);

$ping   = $store->ping();
$orders = $store->getOrders(['limit' => 50]);
$items  = $store->get('endpoint/propio', ['q' => 'x']); // crudo
```

---

## 2. Conceptos comunes a toda API de ecommerce

- **Autenticación.** Varía por plataforma (ver tabla). Patrones: API key/secret (HTTP Basic),
  token estático en header, OAuth2 → access token (Bearer), o login que devuelve un token con
  expiración (lazy + refresh). **Nunca** hardcodear secretos: cifrarlos en reposo.
- **REST + JSON.** Recursos típicos: products/items, orders, customers, categories, inventory.
- **Paginación.** `page`/`limit`, `cursor`, o `Link` headers. Respetarla: no traer todo de una.
- **Rate limits.** Las plataformas tiran 429 (Shopify usa "leaky bucket", TN límites por minuto).
  Degradar y reintentar con backoff; no martillar.
- **Webhooks.** Para eventos en tiempo real (orden creada, pago, stock). nsSkeleton ya tiene
  webhooks **salientes** (`App\Services\Webhook`); para **entrantes** desde la tienda, exponer
  un endpoint y validar la firma de la plataforma.
- **Idempotencia & moneda/impuestos.** Atención a duplicados al crear órdenes y a cómo cada
  plataforma representa precios, monedas e impuestos.

---

## 3. Cheat-sheet por plataforma

| Plataforma | Auth | Base URL | Doc |
|-----------|------|----------|-----|
| **nubixstore** | `POST /usuarios/token` → Bearer | `{tienda}` (endpoints en `{tienda}/api`) | [manual local](../nubixstore/manual-api-nubixstore.md) |
| **Shopify** | header `X-Shopify-Access-Token` | `https://{shop}.myshopify.com/admin/api/{version}` | shopify.dev/docs/api/admin-rest |
| **Tienda Nube / Nuvemshop** | `Authentication: bearer {token}` + User-Agent | `https://api.tiendanube.com/v1/{store_id}` | tiendanube.github.io/api-documentation |
| **WooCommerce** | HTTP Basic `consumer_key:consumer_secret` | `https://{site}/wp-json/wc/v3` | woocommerce.github.io/woocommerce-rest-api-docs |
| **Magento 2 / Adobe Commerce** | Bearer (integration token) | `https://{site}/rest/{store}/V1` | developer.adobe.com/commerce/webapi/rest |

> Detalle de campos de credenciales por plataforma: `system/config/ecommerce.php`.

### Notas por plataforma
- **nubixstore** — Driver de referencia, el más completo. Login devuelve `access_token` +
  `expires_in`; el body es inconsistente (status "EXITO"/"ERROR" vs numérico) → el indicador
  confiable es HTTP 2xx salvo `status: "ERROR"`. Ver el manual y el skill `nubixstore-api`.
- **Shopify** — Recursos `.json` (`products.json`, `orders.json`). Versionado por fecha
  (`2024-04`). Cuidado con el rate-limit por bucket.
- **Tienda Nube** — Header de auth se llama `Authentication` (no `Authorization`) y **exige**
  `User-Agent` identificando tu app. Listados paginados.
- **WooCommerce** — Es un plugin de WordPress; Basic auth sobre HTTPS. Recursos REST estándar.
- **Magento** — Listados exigen `searchCriteria[...]` en la query; el producto se direcciona
  por **SKU**, no por id numérico.

---

## 4. Cómo agregar una nueva plataforma

1. Crear `App\Services\Ecommerce\{Plataforma}Connector` extendiendo `AbstractStoreConnector`.
2. Implementar `platform()`, `apiBase()`, `authHeaders()` y mapear las operaciones genéricas
   (`ping/getProducts/getProduct/getOrders/getOrder/getCustomers`) a las rutas reales.
3. Registrarla en `system/config/ecommerce.php` (label, driver, auth, base_hint, docs,
   esquema de `credentials`) y en el mapa `DRIVERS` del factory.
4. Si la plataforma tiene un manual extenso, guardarlo en `agentic/knowledge/{plataforma}/`.

---

## 5. Reglas de integración

1. Secretos siempre cifrados (Settings::setSecret / grupo `ecommerce`). Nunca en el código.
2. TLS siempre verificado (el conector ya lo hace; no desactivar).
3. Ante `ok=false`: registrar `status`/`error` y degradar; no asumir datos.
4. Respetar paginación y rate limits.
5. No inventar rutas: si un endpoint no está en el driver ni en el manual de la plataforma,
   leer la doc oficial antes (links arriba).
