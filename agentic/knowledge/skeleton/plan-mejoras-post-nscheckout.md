# Plan de desarrollo y mejora — nsSkeleton (post-auditoría nsCheckout)

**Origen:** [`auditoria-nscheckout-para-core.md`](auditoria-nscheckout-para-core.md) (nsCheckout, 2026-07-09)
**Core base:** v1.15.1
**Objetivo:** cerrar los gaps que aparecieron al **desplegar en server real (nginx, pool aislado)** e **integrar JS/iframes de terceros (CSP)** — las dos cosas que el desarrollo local no ejercita — para que la próxima app arranque más rápido y sin parchear el core.

> **Cómo se armó:** cada hallazgo se validó contra el código real del skeleton (no solo el informe). Ver "Validación" en cada ítem. Se encaja en el esquema de versiones existente (`docs/roadmap.md`) y respeta la metodología: **cada fase agrega tests versionados** a `tests/`.

---

## Validación previa (código real del core, v1.15.1)

| Hallazgo | Estado en código | Archivo |
|---|---|---|
| 1.1 CSP hardcodeada, sin `frame-src` | **Confirmado** | `system/app/Core/Security.php:26-36` |
| 1.1 headers pisan al controller | **Confirmado** — `App` hace `$response->header()` tras el dispatch | `system/app/Core/App.php:44-46` |
| 1.2 Router first-match-wins | **Confirmado** — `dispatch()` hace `return` en el primer match | `system/app/Core/Router.php:69-77` |
| 1.4 Session sin `save_path` | **Confirmado** — `Session::start()` no lo setea | `system/app/Core/Session.php:12-21` |
| 1.5 Deploy sube de más | **Confirmado** — `EXCLUDE` no saca docs/tests/installer/agentic | `system/app/Services/Deployer.php:18` |
| 1.8 Sin error-log | **Matiz** — ya hay log mínimo a `storage/logs/app.log`; falta traza+rotación+visor | `system/app/Core/App.php:52-66` |

Los demás (1.3 nginx, 1.6 core-lock, 1.7 tests, 1.9 multi-guard, 1.10 HTTP client) se toman del informe; requieren diseño nuevo, no solo fix.

---

## v1.16 — Camino dorado de producción (P0) 🎯

> **Meta:** que un deploy a nginx con pool aislado + una integración con JS/iframe externo funcionen **sin tocar el core**. Es lo que bloqueó a nsCheckout.

| # | Feature | Hallazgo | Archivos objetivo | Criterio de aceptación / test |
|---|---------|----------|-------------------|-------------------------------|
| 1 | **CSP configurable + merge por-response** | 1.1 | `Core/Security.php`, `Core/App.php`, `config/security.php` (nuevo, override) | Env `CSP_SCRIPT_SRC_EXTRA`/`CSP_CONNECT_SRC_EXTRA`/`CSP_FRAME_SRC_EXTRA`/`CSP_IMG_SRC_EXTRA` + `config/security.php`. `App` hace **merge** (no overwrite) de headers ya seteados por el controller. Test: controller que setea `frame-src` custom → sobrevive; env extra aparece en la CSP. |
| 2 | **Presets de CSP por integración** | 1.1 / P2-13 | `config/security.php` | `security.presets = ['mercadopago','stripe','recaptcha']` inyecta los orígenes conocidos. Test: preset `mercadopago` habilita `sdk.mercadopago.com` + `frame-src`. |
| 3 | **Router: override real app→core** | 1.2 | `Core/Router.php`, `docs/architecture.md` | Registrar mismo `method+path` **reemplaza** (last-wins) o `Router::override()`. Corregir el comentario de `routes.php`. Test: app registra `/` después del core → gana la app. |
| 4 | **Templates nginx + Apache + docroot=`system/public`** | 1.3 | `docs/deploy/` (nuevo), `installer/` | Server-block nginx con `try_files … /index.php?$query_string` + bloque `~ \.php$` + `fastcgi_param SCRIPT_NAME`; vhost Apache. Protección de `.env/.md/.sql` en **ambos**. Doc: docroot apunta a `system/public`. |
| 5 | **`APP_BASE_PATH` explícito por env** | 1.3 | `Core/Request.php`, `Core/Router.php` | Fallback robusto para subdirectorios/alias sin depender de `SCRIPT_NAME`. Test: base path forzado por env rutea bien bajo alias. |
| 6 | **`session.save_path` → `storage/sessions/`** | 1.4 | `Core/Session.php`, `installer/`, `.env.example` | Default a `storage/sessions/` (creado por installer con permisos), configurable por `SESSION_PATH`. Seteado en bootstrap, no en php.ini. Test: sesión persiste con save_path del proyecto. |
| 7 | **`deploy:doctor`** | 1.3 / P2 | `system/console/deploy.php` o comando nuevo | Valida docroot, rewrite/try_files, versión PHP, extensiones, permisos de `storage/`. Test: reporta config faltante en fixture. |

**Riesgo/nota:** los cambios a `Security.php`/`Router.php`/`Session.php`/`App.php` son **core-lock-tracked** → regenerar `core-lock.json` en el release. Mantener retrocompat: sin env/config nuevos, comportamiento idéntico al actual.

---

## v1.17 — Robustez, seguridad y frontera core/app (P1) 🛡️

> **Meta:** que la suite sea determinística, que el código de la app no rompa `core-lock`, y que se pueda diagnosticar prod sin bajar la seguridad.

| # | Feature | Hallazgo | Archivos objetivo | Criterio de aceptación / test |
|---|---------|----------|-------------------|-------------------------------|
| 1 | **Frontera código-app fuera de core-lock** | 1.6 | `system/app/App/**` o `app/` raíz + `core-lock` config | Ubicación de código de app **no trackeada** por core-lock; `core-lock --check` la ignora. Doc "dónde va el código nuevo del proyecto". Test: crear controller de app no genera drift. |
| 2 | **Paquete de deploy con allowlist** | 1.5 | `Services/Deployer.php` | Solo runtime (`system/` + assets compilados + `.env` aparte); `deploy.exclude` configurable. NO subir docs/tests/installer/agentic/fuente. Test: `filesToDeploy()` no incluye `docs/`, `tests/`, `installer/`, `agentic/`. |
| 3 | **Aislamiento de tests (rollback/DB test)** | 1.7 | `tests/run.php`, helpers de test | Cada test en transacción con rollback (o DB de test + migrate fresco); asserts que cuentan **scopean** por fixture. Test: la suite pasa dos veces seguidas sin residuos. |
| 4 | **Test de nombre por `APP_NAME`** | 1.7 | test que hardcodea `nsSkeleton` | Leer `APP_NAME`. `core-lock --check` excluye app + assets compilados. Test: renombrar app no rompe la suite. |
| 5 | **Error-log server-side siempre-on + rotación** | 1.8 | `Core/App.php`, `Core/Logger` (nuevo?) | `storage/logs/error.log` con **traza completa** siempre (independiente de `APP_DEBUG`) + rotación básica. Test: excepción con `APP_DEBUG=false` deja traza en error.log. |
| 6 | **Visor de logs en /admin** | 1.8 | controller admin + vista | Leer/tailear `error.log` desde el panel (ya hay File manager/health). Test: endpoint guard-protegido devuelve últimas N líneas. |
| 7 | **Auth multi-guard / login por rol** | 1.9 | `Core/Auth`, `AuthController`, `config/auth.php` | `auth.guards = [admin, brand, customer]`, cada uno con sesión, tabla, prefijo y redirect post-login. Test: login en guard `brand` no entra a `/admin` y redirige a `/panel`. |

**Riesgo/nota:** el ítem 1 (frontera core/app) es el más estructural — decidir **`app/` en raíz con namespace propio** vs. **excluir `system/app/App/**`**. Recomendado: `app/` en raíz, namespace `App\`, para separación limpia y que el updater no pise nunca la app. Afecta autoloader e installer.

---

## v1.18 — DRY, DX y comodidad (P2) 🧰

> **Meta:** dejar de reimplementar curl/webhooks/migraciones en cada app.

| # | Feature | Hallazgo | Archivos objetivo | Criterio de aceptación / test |
|---|---------|----------|-------------------|-------------------------------|
| 1 | **`Core\Http` client** | 1.10 | `system/app/Core/Http.php` (nuevo) | Timeouts, retries, TLS, JSON, poster inyectable estandarizado. Test: mock de poster, retry en 5xx, timeout. |
| 2 | **`Core\Webhooks\SignatureVerifier`** | 1.10 | `Core/Webhooks/` (nuevo) | HMAC-SHA256 con manifest configurable para webhooks **entrantes** (complementa los salientes G4). Test: firma válida/ inválida MP/Stripe. |
| 3 | **`db:doctor`** | P2 | `system/console/` | Prueba conexión + versión + extensiones; detecta `caching_sha2_password` de MySQL 8. Test: reporta driver incompatible. |
| 4 | **Migración remota protegida por token** | P2 | endpoint web one-shot | Provisionar DB remota sin shell; token de un uso. Test: endpoint rechaza sin token, migra con token. |
| 5 | **`git init` en el installer** | P2 | `installer/`, `system/console/install.php` | Inicializar git (o recordatorio fuerte) para tener historial/rollback antes de prod. Test: proyecto nuevo queda con repo git. |

---

## Módulos de nsCheckout candidatos a subir al core (evaluar, sección 2 del informe)

No entran como versión fija: son **extracciones** a evaluar caso por caso (posiblemente como *módulos opcionales* del generador, no core obligatorio):

- **`payments`** — interface `PaymentGateway` + patrón embebido (SDK + tokenización client-side + requisitos CSP) documentado.
- **`integrations`** — `BaseCatalogConnector` con fetcher inyectable + normalización.
- **`Money`** — formateo multi-moneda / decimales por divisa (utilidad de core).
- **`marketing/consent`** — consent cookie + tracking GTM/GA4/Pixel gateado por consentimiento.
- **`Core\Security\Hmac`** — firma de payloads (`CartSignature`) + verificación entrante (converge con v1.18#2).
- **Helper CSRF sobre AJAX/JSON** — flujos fetch/SPA (el pago embebido postea JSON con `_csrf`).
- **`DomainStatus`** — chequeo DNS + TLS para apps con dominios propios.
- **Exportadores CSV/Excel/PDF** — confirmar que `Exporter` ya está en core y documentado.

> Sugerencia: convertir estos en **plantillas del generador de módulos** (`/nuevo-modulo`) más que en core siempre-cargado, para no inflar el core base.

### Resolución (2026-07-09) — qué se extrajo

**Extraídas al core (genéricas, sin inflar):**
- ✅ **`Core\Money`** — value object inmutable multi-moneda en centavos (8 tests).
- ✅ **`Core\Security\Hmac`** — firma de payloads (sign/verify/`signArray` canónico); **`Webhooks\SignatureVerifier` convergió** a usarla (6 tests).
- ✅ **`App\Services\DomainStatus`** — DNS + TLS con probes inyectables (5 tests).

**Ya existían en el core (confirmado):**
- ✅ **Exportadores** — `App\Services\Exporter` (CSV/Excel/PDF) ya en core.
- ✅ **Helper CSRF** — `Session::csrf()`/`verifyCsrf()` + `AdminController::verifyCsrf()`; sirve a flujos JSON/fetch (el cliente manda `_csrf`). No requiere pieza nueva.

**Quedan como app-específicas / plantillas (NO core, para no inflarlo):**
- ⏭️ **`payments`** (interface `PaymentGateway` + patrón embebido) — muy ligado al negocio y a cada pasarela. Recomendado: plantilla de `/nuevo-modulo` cuando la próxima app lo pida. El core ya aporta las piezas transversales que necesita: `Core\Http` (retries/poster), `Webhooks\SignatureVerifier` (verificación entrante), CSP con preset `mercadopago`/`stripe`, `Core\Money`.
- ⏭️ **`integrations`** (`BaseCatalogConnector`) — el core **ya tiene** el framework de connectors ecommerce (`system/app/Services/Ecommerce/`, v1.10). No se duplica.
- ⏭️ **`marketing/consent`** (cookie + GTM/GA4/Pixel) — específico de front/marketing; plantilla, no core.

---

## Secuenciación recomendada

1. **v1.16 completa primero** — desbloquea deploy real + integraciones externas; es lo que costó tiempo en producción. Sin esto, la próxima app tropieza igual.
2. **v1.17** — la frontera core/app (1.6) conviene hacerla **temprano** dentro de v1.17 porque cambia dónde vive el código; hacerla después obligaría a mover código ya escrito.
3. **v1.18 + extracciones** — a demanda, cuando la próxima app las necesite.

**Transversal (documentar en `docs/`):** un **"camino dorado" de producción** (deploy doctor + templates nginx/apache + checklist) y un **ejemplo de integración externa con CSP** ya resuelto (ej. Mercado Pago embebido), para que no se reaprenda por proyecto.

---

*Plan generado 2026-07-09 a partir de la auditoría de nsCheckout, validado contra el código de nsSkeleton v1.15.1.*
