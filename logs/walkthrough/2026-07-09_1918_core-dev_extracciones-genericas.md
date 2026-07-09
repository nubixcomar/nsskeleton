# Walkthrough — Extracciones genéricas de nsCheckout (Money, Hmac, DomainStatus)

Fecha y hora: 2026-07-09 19:18 | Agente: core-dev | Modelo: claude-opus-4-8

## Contexto
Sección 2 del informe: piezas construidas en nsCheckout, transversales a próximas apps. Se
evaluaron caso por caso; se extrajeron al core solo las **genéricas** (sin inflarlo).

## Qué se extrajo al core
- **`Core\Money`**: value object inmutable multi-moneda, aritmética en **centavos** (evita float).
  `of`/`ofMinor`/`zero`, `add`/`subtract`/`multiply`, `format` (símbolo+separadores por divisa),
  `toMajor`, `equals`/`isZero`/`isNegative`. Mapa de divisas built-in (ARS/USD/EUR/BRL/CLP/MXN/
  UYU/JPY/GBP) overrideable en `config/money.php`. 8 tests (incl. 0.1+0.2 y CLP sin decimales).
- **`Core\Security\Hmac`**: primitiva de firma HMAC — `sign`/`verify` (constant-time, acepta
  prefijo `sha256=`), `signBase64`, `canonical` (ksort recursivo) + `signArray`/`verifyArray`
  (firma orden-independiente, caso `CartSignature`). **`Webhooks\SignatureVerifier` convergió**
  a usar `Hmac` (sin duplicar HMAC/hash_equals). 6 tests; SignatureVerifier sin regresión.
- **`App\Services\DomainStatus`**: `check($domain)` agrega **DNS** (`gethostbynamel`) + **TLS**
  (cert del peer: `valid`, `expires`, `issuer`, `days_left`), `normalize` (quita esquema/path/
  puerto). Probes DNS/TLS **inyectables** → testeable sin red. 5 tests.

## Ya existían en el core (verificado)
- Exportadores: `App\Services\Exporter` (CSV/Excel/PDF).
- Helper CSRF para JSON/fetch: `Session::csrf()`/`verifyCsrf()` + `AdminController::verifyCsrf`.
- Framework de connectors: `system/app/Services/Ecommerce/` (v1.10) cubre `integrations`.

## Quedan como app-específicas (plantillas, no core)
- `payments` (PaymentGateway + embebido): plantilla de `/nuevo-modulo`; el core ya aporta
  `Core\Http` (retries), `SignatureVerifier`, CSP preset mercadopago/stripe, `Core\Money`.
- `marketing/consent` (GTM/GA4/Pixel): front/marketing, plantilla.

## Verificación
- Suite: **367 PASS / 0 FAIL / 1 SKIP** (+19 tests: 8 + 6 + 5). `core-lock.json` sin drift.

## Archivos tocados
- `system/app/Core/Money.php`, `system/app/Core/Security/Hmac.php`,
  `system/app/Services/DomainStatus.php` (nuevos)
- `system/app/Core/Webhooks/SignatureVerifier.php` (converge con Hmac)
- `tests/unit/MoneyTest.php`, `HmacTest.php`, `DomainStatusTest.php` (nuevos)
- `agentic/knowledge/skeleton/plan-mejoras-post-nscheckout.md`, `docs/roadmap.md` (resolución)
- `core-lock.json` (regenerado)

## Estado
**Plan COMPLETO**: v1.16 (7/7) + v1.17 (7/7) + v1.18 (5/5) + extracciones genéricas (3) +
resolución de las app-específicas. Sigue: bump de VERSION/CHANGELOG y cierre del loop.
