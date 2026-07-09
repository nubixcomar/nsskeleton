# Auditoría nsSkeleton — aprendizajes de construir y desplegar nsCheckout

**Para:** equipo de desarrollo del core **nsSkeleton** (v1.15.1)
**De:** desarrollo de **nsCheckout** (app derivada) — build MVP→v2.1 + **deploy real a producción** + **integración de pago embebido de Mercado Pago**
**Fecha:** 2026-07-09
**Objetivo:** consolidar todo lo que **faltó, hubo que agregar, o costó** encima del core, para que el próximo proyecto arranque más rápido, robusto y seguro.

> **Fuente de la auditoría:** el core resolvió muy bien el **build** (login, cron, emails, backups, gráficos, file manager, RBAC, API REST, webhooks, crypto, cola de emails, tests, installer). Los gaps que siguen surgieron sobre todo en el **deploy real** y en **integraciones externas con JS/iframes** — dos cosas que el desarrollo local no ejercita. Ahí es donde el core necesita reforzarse.

---

## 0. Resumen ejecutivo (top hallazgos)

| # | Hallazgo | Tipo | Sev. | Dónde dolió |
|---|----------|------|------|-------------|
| 1 | **CSP no configurable** (hay que editar `Core\Security.php`) | Gap / seguridad | **P0** | Integrar cualquier JS/iframe externo (MP Bricks, mapas, widgets) |
| 2 | **Router first-match-wins** pero se documenta como "la app pisa al core" | Bug / DX | **P0** | No se pudo overridear la home `/`; se resolvió por nginx |
| 3 | **Deploy y front asumen Apache**; en **nginx** no rutea nada | Gap / deploy | **P0** | Puesta en producción real (el server era nginx) |
| 4 | **Sesiones fallan en pools PHP-FPM aislados** (save_path por defecto) | Bug / hardening | **P0** | Login/CSRF/carrito rotos en prod hasta configurar save_path |
| 5 | **Deploy sube TODO el árbol** (docs, tests, installer, agentic, código fuente) | Seguridad / hygiene | **P1** | Se expone fuente y tooling en el server público |
| 6 | **Código del proyecto vive bajo `system/app/`** → rompe `core-lock` en cada edición | Arquitectura | **P1** | Todo cambio de app aparece como "drift" del core |
| 7 | **Tests dependientes de datos no aíslan** (sin rollback / DB de test) | Bug / testing | **P1** | La suite se pone en rojo por datos que quedan en la DB |
| 8 | **Sin error-log server-side cuando `APP_DEBUG=false`** | Hardening / DX | **P1** | Diagnosticar 500s en prod obligó a prender debug (riesgo) |
| 9 | **Auth core no soporta multi-guard / login por rol con redirect** | Gap / feature | **P1** | El panel de marca tuvo que armar auth propia para no tocar core |
| 10 | **Sin abstracción HTTP client** (curl crudo repetido) + helpers de webhooks entrantes | Gap / DRY | **P2** | Cada connector/pasarela reimplementa curl y verificación de firma |

---

## 1. Bugs y gaps del core (con detalle y recomendación)

### 1.1 — [P0] CSP no configurable — hay que editar el core para permitir orígenes externos
**Qué pasó:** `Core\Security.php` arma una CSP **hardcodeada** (`default-src 'self'`, `script-src 'self' …`, `connect-src 'self'`, sin `frame-src`). Para embeber el **Card Payment Brick de Mercado Pago** hubo que **editar el archivo del core** y agregar dominios (`sdk.mercadopago.com`, `*.mlstatic.com`, `*.mercadopago.com`, `*.mercadopago.com.ar`, `*.mercadolibre.com`, `*.mercadolibre.com.ar`, `blob:` para workers, `frame-src`/`child-src`/`worker-src`). No hay ningún hook de configuración.
**Peor aún:** los headers de `Security::headers()` se aplican en `Core\App` **DESPUÉS** de que el controller devuelve su `Response`, **pisando** cualquier header que el controller intente setear → **no se puede** relajar la CSP por-ruta desde la app.
**Impacto:** cualquier integración con JS/iframes de terceros (pasarelas, mapas, chat, analytics, reCAPTCHA) obliga a modificar el core → drift y se pierde en el próximo `/actualizar-core`.
**Recomendación:**
- CSP **configurable por env/config**: `CSP_SCRIPT_SRC_EXTRA`, `CSP_CONNECT_SRC_EXTRA`, `CSP_FRAME_SRC_EXTRA`, `CSP_IMG_SRC_EXTRA`, o un `config/security.php` (override no-core) con arrays de orígenes permitidos.
- Permitir **override por-response**: que `App` haga *merge* (no overwrite) de los headers que el controller ya puso, o exponer `Response::withCsp()`.
- Traer presets opcionales por integración conocida (ej. `security.presets = ['mercadopago', 'stripe', 'recaptcha']`).

### 1.2 — [P0] Router first-match-wins vs. documentación "la app pisa al core"
**Qué pasó:** `routes.php` carga las rutas de la app **después** de las del core, con el comentario *"si repetís una ruta, gana la de la app"*. Pero `Core\Router::dispatch()` hace `return` en el **primer** match → para una ruta **exacta** duplicada (ej. `/`), **gana el core** (`HomeController`), no la app. Agregamos `$router->get('/', …)` en `routes.app.php` y **nunca** se alcanzó.
**Impacto:** no se pudo hacer que la raíz del sitio muestre el checkout; hubo que resolverlo con un `rewrite` en **nginx**. Tiempo perdido + comportamiento contraintuitivo.
**Recomendación:** hacer el override **real**: que registrar una ruta con el mismo `method+path` **reemplace** la anterior (last-wins), o un `Router::override()` explícito. Como mínimo, **corregir el comentario** para que no engañe.

### 1.3 — [P0] Deploy y front asumen Apache; en nginx no rutea
**Qué pasó:** el front-controller depende de `.htaccess` (rewrite a `index.php`) y de derivar la base del `SCRIPT_NAME`. El `App\Services\Deployer` sube archivos pero no hay guía de servidor. El server real de producción era **nginx** → los `.htaccess` se ignoran, no hay `try_files`, y **todas** las rutas lindas daban 301→404. Además el `SCRIPT_NAME` bajo un `alias` de nginx se reportaba mal (la app buscaba `/login` en vez de `/admin/login`) hasta forzar `fastcgi_param SCRIPT_NAME`.
**Impacto:** el deploy "funcionó" (archivos subidos, PHP corriendo, DB conectada) pero la app **no ruteaba** hasta configurar nginx a mano.
**Recomendación:**
- Documentar que el **DocumentRoot debe apuntar a `system/public`** y proveer **templates de server block** para **nginx** (con `try_files $uri $uri/ /index.php?$query_string;` + bloque `~ \.php$`) **y** Apache.
- Un comando `deploy:doctor` que valide docroot, rewrite/try_files, versión PHP, extensiones y permisos.
- Que el router no dependa tanto de `SCRIPT_NAME`; ofrecer `APP_BASE_PATH` explícito por env como fallback robusto para subdirectorios/alias.

### 1.4 — [P0] Sesiones fallan en pools PHP-FPM aislados (save_path por defecto)
**Qué pasó:** la app usa el `session.save_path` **por defecto de PHP** (`/var/lib/php/sessions`). En un pool **aislado** (usuario propio + `open_basedir`) ese directorio **no es escribible** → `Warning: session_start(): … Permission denied` y **login/CSRF/carrito rotos**, sin error claro. Se arregló poniendo `php_admin_value[session.save_path]` a un dir del sitio.
**Impacto:** en cualquier hosting con aislamiento (lo normal en producción seria), la app no mantiene sesión de arranque.
**Recomendación:** por defecto, `session.save_path` a **`storage/sessions/`** del proyecto (como hace Laravel), creado por el installer con permisos correctos, y configurable por env (`SESSION_PATH`). Alternativa: driver de sesión en DB. `Core\Session` debería setearlo en el bootstrap, no depender del php.ini del server.

### 1.5 — [P1] El deploy sube TODO el árbol (fuente, docs, tests, installer, agentic)
**Qué pasó:** `Deployer::filesToDeploy()` excluye `.git/vendor/node_modules/tools/bin/landing/downloads` y runtime de `storage`, pero **sí sube** `docs/`, `tests/`, `installer/`, `agentic/`, `app-agentic/`, `landing/`, `README`, `core-*.json`, `AGENTS.md`… → **665 archivos**, incluyendo **código fuente y documentación interna**, al server público.
**Impacto:** superficie de exposición innecesaria (fuente, endpoints de tests, docs de arquitectura, prompts).
**Recomendación:** un **paquete de producción** con allowlist (solo runtime: `system/`, assets compilados, `.env` aparte) o un `deploy.exclude` configurable; documentar que el docroot es `system/public` y el resto debería quedar **fuera** del webroot.

### 1.6 — [P1] El código del proyecto vive bajo `system/app/` → rompe `core-lock`
**Qué pasó:** los controllers/services/models/vistas **propios de la app** (Checkout, Order, Cart, MercadoPago…) se crearon **dentro de `system/app/`**, que es **core-lock-tracked**. Resultado: **cada edición legítima de la app** aparece como *"drift"* y el test `core-lock --check` queda en rojo. Los "puntos de extensión" documentados (`config/overrides/`, `app-agentic/`) **no cubren** controllers/services nuevos.
**Impacto:** frontera difusa entre "core" y "app"; imposible distinguir un cambio del proyecto de una modificación real del core; el updater de core podría pisar código de la app.
**Recomendación:** una **ubicación de código de app claramente separada** y **no** trackeada por core-lock (ej. `app/` en la raíz con su propio namespace, o `system/app/App/**` explícitamente excluido). Que `core-lock --check` **ignore** los dirs de app. Definir en la doc "dónde va el código nuevo del proyecto".

### 1.7 — [P1] Tests dependientes de datos no aíslan (sin rollback / DB de test)
**Qué pasó:** los tests de **analítica** y **order-sync** siembran pedidos y **cuentan filas**, pero **no limpian** ni corren en transacción; usan la **DB de dev**. Datos que quedan (de otros tests o de pruebas manuales) los ponen en rojo ("esperaba 2 y obtuvo 4/5"). Además un test del core **hardcodea** el nombre `nsSkeleton` y **falla tras renombrar** la app (debería leer `APP_NAME`), y el `core-lock --check` (ver 1.6) suma otra falla "preexistente".
**Impacto:** la suite no es determinística; "2 FAIL preexistentes" se normalizó como ruido, lo que **enmascara** regresiones reales.
**Recomendación:** correr cada test en **transacción con rollback** (o **DB de test** dedicada + migrate fresco); los asserts que cuentan deben **scopear** por el fixture sembrado. El test de nombre debe leer `APP_NAME`. El `core-lock --check` debe excluir app + assets compilados.

### 1.8 — [P1] Sin error-log server-side cuando `APP_DEBUG=false`
**Qué pasó:** con `APP_DEBUG=false` (correcto en prod), un error tira **500 genérico** sin dejar rastro accesible. Para diagnosticar un 500 real **hubo que prender `APP_DEBUG` en producción** momentáneamente (algo que hasta el harness de seguridad marcó como riesgoso) porque no había **log de errores** para leer sin shell.
**Impacto:** diagnosticar en prod obliga a debilitar la seguridad.
**Recomendación:** **siempre** escribir excepciones/errores a `storage/logs/error.log` (con traza), independientemente de `APP_DEBUG`. Rotación básica. Idealmente un visor en `/admin` (ya hay File manager y health). Así se diagnostica sin exponer nada.

### 1.9 — [P1] Auth del core no soporta multi-guard / login por rol con redirect
**Qué pasó:** para el **panel de marca** (brand owners) se necesitaba un login separado que **no** entre a `/admin`. El core (`AuthController`/`Rbac`/`AdminController`) **no** permite login único ruteado por rol sin tocar core (router first-match + auth acoplada al admin). Se resolvió con una **sesión propia** (`BrandAuth`) y rutas bajo `/panel` — "100% app, cero core tocado", pero es un patrón que **todo** SaaS multi-tenant va a repetir.
**Impacto:** cada app multi-rol/multi-tenant reimplementa auth.
**Recomendación:** soportar **múltiples guards** en el core (ej. `auth.guards = [admin, brand, customer]`), cada uno con su sesión, tabla de usuarios, prefijo y redirect post-login por rol. Es una de las piezas más reutilizables que faltan.

### 1.10 — [P2] Sin HTTP client del core + helpers de webhooks entrantes
**Qué pasó:** `MercadoPagoClient`, los 5 connectors de catálogo y Stripe usan **curl crudo** repetido (con el buen patrón de *poster inyectable* para testear, pero duplicado en cada clase). Los **webhooks entrantes** (MP/Stripe) requieren verificar **HMAC `x-signature`** a mano; el core tiene webhooks **salientes** (G4) pero no helpers para **verificar** entrantes.
**Recomendación:** un `Core\Http` client (timeouts, retries, TLS, JSON, poster inyectable ya estandarizado) y un `Core\Webhooks\SignatureVerifier` (HMAC-SHA256 con manifest configurable). DRY + seguridad consistente.

### Otros (menores)
- **[P2] MySQL 8 `caching_sha2_password`:** el cliente MariaDB de XAMPP no autentica contra MySQL 8; hay que usar PDO/mysqlnd. Un comando `db:doctor` (probar conexión + versión + extensiones) evitaría el diagnóstico manual.
- **[P2] Migración remota sin shell:** `migrate.php` es solo CLI; provisionar una DB remota con solo FTP fue tedioso (se importó un dump por PDO). Un endpoint de migración **web protegido por token** (one-shot) o export/import documentado ayudaría.
- **[P2] `git init` no está en el flujo:** el proyecto quedó **sin control de versiones**. El installer debería inicializar git (o recordarlo fuerte) para tener historial/rollback antes de tocar prod.
- **[P2] `.user.ini`/`.htaccess` de protección:** el `.htaccess` raíz protege dotfiles pero es inútil en nginx; la protección de `.env`/`.md`/`.sql` debe estar **también** en los templates de nginx.

---

## 2. Módulos/features que nsCheckout construyó y convendría **subir al core**

Estas piezas se hicieron a medida pero son **transversales** a las próximas apps:

| Módulo construido en nsCheckout | Reutilizable como core |
|---|---|
| **Abstracción de pagos** (`PaymentGateway` + Mercado Pago redirect **y embebido/Orders API** + Stripe) | Módulo `payments` con interface, idempotencia, verificación de webhooks, y **patrón de pago embebido** (SDK + tokenización client-side + requisitos de CSP) documentado |
| **Framework de connectors de catálogo** (`BaseCatalogConnector` + TiendaNube/Shopify/Woo/Magento/nubixstore) | Módulo `integrations` con fetcher inyectable y normalización |
| **Multi-tenant / scoping** (brand panel, aislamiento duro por `checkout_id`, auth propia) | Guards múltiples + helper de scoping por tenant (ver 1.9) |
| **`Money`** (formateo multi-moneda, decimales por divisa) | Utilidad de core |
| **Consent cookie + Tracking** (GTM/GA4 dual + Pixel, `purchase` gateado por consentimiento) | Módulo `marketing/consent` |
| **HMAC de firma de payloads** (`CartSignature`) + verificación de webhooks entrantes | `Core\Security\Hmac` |
| **CSRF sobre AJAX/JSON** (el pago embebido postea JSON con `_csrf`) | Documentar/entregar helper para flujos fetch/SPA |
| **`DomainStatus`** (chequeo DNS + TLS) | Útil para cualquier app con dominios propios |
| **Exportadores** CSV/Excel/PDF (ya hay `Exporter`) | Confirmar que está en core y documentado |

---

## 3. Backlog priorizado para el core (propuesta)

**P0 — antes de la próxima app (bloquean integraciones/deploy serio):**
1. CSP configurable por env/config + merge de headers por-response (1.1).
2. Router: override real de rutas app→core + arreglar doc (1.2).
3. Templates de deploy **nginx + Apache** + `deploy:doctor` + docroot=`system/public` (1.3).
4. `session.save_path` a `storage/sessions/` por defecto, configurable (1.4).

**P1 — robustez y seguridad:**
5. Paquete de deploy con allowlist (no subir fuente/tests/docs) (1.5).
6. Separar código de app del core-lock (ubicación no trackeada) (1.6).
7. Aislamiento de tests (transacción/rollback o DB de test) + test de nombre por `APP_NAME` (1.7).
8. Error-log server-side siempre-on + visor en admin (1.8).
9. Auth multi-guard / login por rol con redirect (1.9).

**P2 — DRY, DX y comodidad:**
10. `Core\Http` client + `SignatureVerifier` de webhooks entrantes (1.10).
11. `db:doctor` + migración remota protegida por token.
12. `git init` en el installer.
13. Presets de CSP por integración (mercadopago/stripe/recaptcha…).

---

## 4. Notas de contexto (para entender los hallazgos)

- **Qué se construyó sobre el core:** nsCheckout = motor de checkouts one-page multi-marca. MVP (fases 1–5) + post-MVP (v1.1 políticas/URL friendly/gift/estado, v1.2 analítica, v1.3 connectors+pasarelas, v1.4 endurecimiento firma/dominio/webhooks/cron, v2.0 multi-marca onboarding+multi-moneda, v2.1 panel de marca) + **deploy real** + **MP embebido**. Historial completo en `logs/dev.log` y `logs/walkthrough/` (86 walkthroughs).
- **El core cumplió muy bien en el build.** La mayoría de estos gaps **no se ven** desarrollando en local: aparecen al **desplegar en un server real (nginx, pool aislado)** y al **integrar JS/iframes de terceros (CSP)**. Recomendación transversal: agregar al skeleton un **"camino dorado" de producción** (deploy doctor + templates + checklist) y un **ejemplo de integración externa con CSP** ya resuelto, para que la próxima app no tropiece con lo mismo.
- **Todo lo detectado se resolvió en nsCheckout** (a veces tocando el core: `Security.php` CSP — marcado en el archivo para reaplicar en updates). Este informe busca que esas soluciones **nazcan en el core** y dejen de ser parches por-proyecto.

---

*Generado a partir del código de nsCheckout, `logs/dev.log`, los 86 walkthroughs, y la experiencia directa del deploy + integración de Mercado Pago (2026-07-08/09).*
