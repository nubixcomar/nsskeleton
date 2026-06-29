---
name: nubixstore-api
summary: Consume e integra la API de nubixstore (catálogo, pedidos, clientes, stock, picking, pagos…) con dominio del manual y la versión vigente.
generic: false
---

## Rol
Especialista en la **API de nubixstore**. Sabés su modelo de auth, su formato de respuestas
(inconsistente a propósito) y sus endpoints por módulo. Sos el skill a adoptar antes de
escribir cualquier integración con una tienda nubixstore. Caso de uso primario del skeleton.

## Fuente de verdad (leer antes de inventar rutas)
- **Manual completo**: [`../../knowledge/nubixstore/manual-api-nubixstore.md`](../../knowledge/nubixstore/manual-api-nubixstore.md)
  — auth, formato de respuestas y **todos los endpoints por módulo** (§5). **Versión actual: 2.1.**
- **Driver listo (stack PHP MVC)**: `App\Services\Ecommerce\NubixstoreConnector`
  (constante `API_MANUAL_VERSION` = versión del manual; deben coincidir).
- **Conocimiento genérico de ecommerce**: [`../../knowledge/ecommerce/ecommerce-apis.md`](../../knowledge/ecommerce/ecommerce-apis.md).

## Modelo de autenticación (manual §2)
1. `POST {base}/usuarios/token` con body `{ USER, PASSWORD, API_KEY }` → `access_token` + `expires_in`.
2. Endpoints `api_*`: `GET/POST {base}/api/{controlador}/{funcion}` con header
   `Authorization: Bearer {access_token}`.

`{base}` = URL de la tienda **sin** `/api`. El driver maneja **login lazy + cacheo del token**
(renueva al expirar): no hay que loguear a mano.

> Cuidado: el login responde HTTP 200 incluso con credenciales inválidas (el error va en el
> body). El único indicador confiable de éxito es la presencia de `access_token`.

## Cómo usar el driver
```php
use App\Services\Ecommerce\StoreConnectorFactory;

// Tienda configurada (settings, grupo 'ecommerce', secretos cifrados):
$api = StoreConnectorFactory::fromSettings('nubixstore');
// o explícito (multi-tienda):
$api = StoreConnectorFactory::make('nubixstore', [
    'base_url' => 'https://www.cliente.com.ar', 'user' => '...', 'password' => '...', 'api_key' => '...',
]);

$ping   = $api->ping();                                   // GET /api/utils/connect
$orders = $api->getOrders(['from'=>'2026-06-01','to'=>'2026-06-30','limit'=>200,'page'=>1]); // §5.14
$item   = $api->getProduct(123);                          // GET /api/articulos/item?id=123
$cats   = $api->get('articulos/categories');              // endpoint arbitrario
```

### Forma de la respuesta (siempre)
```php
['ok' => bool, 'status' => int, 'data' => mixed, 'error' => ?string]
```
`ok` = HTTP 2xx salvo que el body diga `status: "ERROR"`. La API es inconsistente (login usa
`EXITO`/`ERROR`; otros endpoints status numérico) → validar el contenido esperado, no solo `ok`.

## Endpoints clave (mapeados en el driver)
| Caso de uso | Endpoint (manual) | Helper del driver |
|---|---|---|
| Health / conectividad | `GET /api/utils/connect` (§5.18) | `$api->ping()` |
| Ventas / pedidos | `GET /api/pedidos/orders` (§5.14) | `$api->getOrders([...])` |
| Detalle de orden | `GET /api/pedidos/order/:id` | `$api->getOrder($id)` |
| Catálogo | `GET /api/articulos/items` (§5.1) | `$api->getProducts([...])` |
| Un artículo | `GET /api/articulos/item` | `$api->getProduct($id)` |
| Stock | `GET /api/articulos/stock` | `$api->getStock([...])` |
| Clientes | `GET /api/usuarios/clients` (§5.17) | `$api->getCustomers([...])` |

> Resto de módulos (envíos, depósitos, garantías, cupones, picking, pagos, marketing, etc.):
> ver el manual §5 y llamar con `$api->get('{controlador}/{funcion}', [...])`. Convención:
> `path = '{controlador_sin_controller}/{funcion_sin_api_}'`.

## Reglas
1. **Nunca** hardcodear credenciales: cifradas en `settings` (grupo `ecommerce`) o pasadas a
   `make()`. Una tienda puede tener varias páginas/credenciales: resolver la correcta antes.
2. Ante `ok=false`, no asumir datos: registrar `error`/`status` y degradar.
3. Respetar **paginación** (`page`/`limit`) en listados; no traer todo de una.
4. Si un endpoint no está en este skill ni en el driver, **leer el manual** antes de inventar
   la ruta. Si el manual tampoco lo cubre, coordinar con el agente `api-nubixstore`.
5. **Versión**: si el manual cambia de versión, actualizar `NubixstoreConnector::API_MANUAL_VERSION`
   y el test que la verifica, y dejar constancia en el walkthrough.

## Salida
- Código de integración con nubixstore usando el driver.
- Walkthrough + línea en `logs/nubixstore-api.log`, según
  [`../../methodology/logging.md`](../../methodology/logging.md).
