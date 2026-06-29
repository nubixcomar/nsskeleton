# Walkthrough — Core agéntico: conector de ecommerce + agentes/skills nubixstore

**Fecha y hora:** 2026-06-28 17:00 | **Agente:** ecommerce-integrator / nubixstore-api | **Modelo:** claude-opus-4-8
**Sprint:** — | **Versión:** 1.10.0

---

## Resumen ejecutivo
Se incorporó al core un **conector genérico de tiendas de ecommerce** (capa `system/`) y el
**conocimiento + agentes + skills** para integrarse con APIs de tiendas, con foco primario en
**nubixstore**. Al instalar, el proyecto ya trae la información y herramientas para conectarse
a Shopify, Tienda Nube, WooCommerce, Magento y nubixstore. El manual de la API de nubixstore
(v2.1) y el skill que la domina fueron portados desde nsCentral (impulso).

## Cambios realizados
- **Conector genérico** (`App\Services\Ecommerce`): contrato `StoreConnector`, base
  `AbstractStoreConnector` (cURL con TLS verificado + respuesta normalizada
  `['ok','status','data','error']`), `StoreConnectorFactory` (resuelve por config o settings
  cifrados).
- **Drivers**: `NubixstoreConnector` (completo, de referencia, con login lazy + token cacheado
  y `API_MANUAL_VERSION=2.1`), `ShopifyConnector`, `TiendaNubeConnector`, `WooCommerceConnector`,
  `MagentoConnector` (auth real + mapeo de productos/órdenes/clientes).
- **Config** `system/config/ecommerce.php`: registro de plataformas (label, driver, auth,
  base URL, doc, esquema de credenciales) — "información lista al instalar".
- **Knowledge** (`agentic/knowledge/`, carpeta nueva): manual de nubixstore portado y
  neutralizado + doc genérico `ecommerce/ecommerce-apis.md`.
- **Skills**: `ecommerce-integration` (genérico) y `nubixstore-api` (específico, con manual+versión).
- **Agentes**: categoría nueva `integrations/` con `ecommerce-integrator` y `nubixstore-api`
  (este último responsable de mantener el manual). INDEX, README de skills y adapter php-mvc
  actualizados.
- **Test**: `tests/unit/EcommerceConnectorTest.php` (factory, bases, auth por plataforma).

## Decisiones de diseño
- **Driver pattern sobre un contrato único**: agnóstico a la plataforma, nubixstore como
  referencia por ser el caso de uso primario.
- Cliente HTTP propio en `AbstractStoreConnector` (no se reusó `Services\Http` porque este es
  POST/JSON-only; el conector necesita GET/PUT/DELETE + headers arbitrarios + manejo de CA en
  Windows). TLS nunca se desactiva.
- Credenciales cifradas en `settings` (grupo `ecommerce`), mismo patrón que `AiConnector`.
- La versión del manual se ancla en una constante (`API_MANUAL_VERSION`) verificada por test,
  para detectar desincronización manual↔driver.

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| system/app/Services/Ecommerce/StoreConnector.php | nuevo (contrato) |
| system/app/Services/Ecommerce/AbstractStoreConnector.php | nuevo (base HTTP/normalización) |
| system/app/Services/Ecommerce/NubixstoreConnector.php | nuevo (driver referencia) |
| system/app/Services/Ecommerce/ShopifyConnector.php | nuevo |
| system/app/Services/Ecommerce/TiendaNubeConnector.php | nuevo |
| system/app/Services/Ecommerce/WooCommerceConnector.php | nuevo |
| system/app/Services/Ecommerce/MagentoConnector.php | nuevo |
| system/app/Services/Ecommerce/StoreConnectorFactory.php | nuevo (fábrica) |
| system/config/ecommerce.php | nuevo (registro plataformas) |
| agentic/knowledge/README.md, ecommerce/ecommerce-apis.md, nubixstore/manual-api-nubixstore.md | nuevo (knowledge) |
| agentic/skills/ecommerce-integration/SKILL.md, nubixstore-api/SKILL.md | nuevo (skills) |
| agentic/agents/integrations/ecommerce-integrator.md, nubixstore-api.md | nuevo (agentes) |
| agentic/agents/INDEX.md, agentic/skills/README.md, agentic/adapters/php-mvc/conventions.md | actualizados |
| tests/unit/EcommerceConnectorTest.php | nuevo (10 tests) |
| VERSION, docs/CHANGELOG.md | bump 1.10.0 |

## Testing / verificación
- `php -l` OK en los 8 archivos PHP del conector + config.
- Smoke test (sin red): factory, plataformas, resolución de base/auth por driver — OK.
- Suite completa: **PASS 247 / FAIL 0 / SKIP 1** (los nuevos tests del conector verdes).

## Pendientes / follow-ups
- (Opcional) Panel de admin para configurar la tienda activa y sus credenciales (hoy vía
  `settings`/seed). 
- (Opcional) Webhooks **entrantes** desde la tienda (validación de firma por plataforma).
- Completar endpoints específicos de Shopify/TN/Woo/Magento a medida que se usen.

## Referencias
- Manual API nubixstore: `agentic/knowledge/nubixstore/manual-api-nubixstore.md`
- Conocimiento ecommerce: `agentic/knowledge/ecommerce/ecommerce-apis.md`
