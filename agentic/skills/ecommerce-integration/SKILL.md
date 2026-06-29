---
name: ecommerce-integration
summary: Integra el sistema con la API de una tienda de ecommerce (Shopify, Tienda Nube, WooCommerce, Magento, nubixstore) usando el conector genérico.
generic: true
---

## Rol
Especialista en integraciones de ecommerce. Conectás el software con la API de una tienda
virtual a través del **conector genérico** del skeleton, sin reinventar el cliente HTTP ni
el manejo de auth.

## Conocimiento base (leer antes)
- [`../../knowledge/ecommerce/ecommerce-apis.md`](../../knowledge/ecommerce/ecommerce-apis.md) —
  conceptos comunes (auth, paginación, rate limits, webhooks) y cheat-sheet por plataforma.
- Registro de plataformas y esquema de credenciales: `system/config/ecommerce.php`.
- Para **nubixstore**, adoptá además el skill [`nubixstore-api`](../nubixstore-api/SKILL.md)
  (conoce el manual completo y la versión de la API).

## Entrada
- Plataforma destino (`nubixstore`, `shopify`, `tiendanube`, `woocommerce`, `magento`, u otra).
- Caso de uso: leer catálogo, descargar órdenes/ventas, sincronizar stock, clientes, etc.
- Credenciales del cliente (que se guardan **cifradas**, nunca en el código).

## Tarea
1. **Resolver el conector** con `App\Services\Ecommerce\StoreConnectorFactory`:
   - tienda configurada → `StoreConnectorFactory::fromSettings()`;
   - explícito / multi-tienda → `StoreConnectorFactory::make($platform, $credentials)`.
2. Usar las operaciones genéricas del contrato `StoreConnector`: `ping()`, `getProducts()`,
   `getProduct($id)`, `getOrders($filters)`, `getOrder($id)`, `getCustomers()`. Para rutas
   propias de la plataforma, `get()/post()` crudos.
3. Manejar **siempre** la respuesta normalizada `['ok','status','data','error']`: ante
   `ok=false`, registrar `status`/`error` y degradar (no asumir datos).
4. Respetar **paginación** y **rate limits** de la plataforma.
5. Si la plataforma no tiene driver: crear `{Plataforma}Connector` extendiendo
   `AbstractStoreConnector` y registrarlo en `config/ecommerce.php` (ver §4 del knowledge).
6. Para eventos en tiempo real, usar webhooks (entrantes: endpoint + validación de firma).

## Reglas
- **Secretos cifrados** (Settings::setSecret / grupo `ecommerce`), nunca hardcodeados.
- **TLS siempre verificado**: el conector ya lo hace, no desactivarlo.
- No inventar rutas: si un endpoint no está en el driver ni en la doc de la plataforma,
  leer la doc oficial antes (links en el knowledge).
- Cumplir [`../../rules/core-rules.md`](../../rules/core-rules.md) (+ `app-rules.md` del
  proyecto) y [`../../rules/new-features-rules.md`](../../rules/new-features-rules.md).
- Mantener el feature aislado y desactivable.

## Salida
- Código de integración (servicio/controlador/job) usando el conector.
- Si se agregó una plataforma: nuevo driver + entrada en `config/ecommerce.php`.
- Walkthrough + línea en `logs/ecommerce-integration.log` y actualización del roadmap,
  según [`../../methodology/logging.md`](../../methodology/logging.md).
