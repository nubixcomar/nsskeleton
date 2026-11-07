# Changelog

Todas las versiones notables de nsSkeleton. Formato basado en versionado semántico.
Lo mantiene el comando `/release` (ver `agentic/commands/release.md`).

## [Sin publicar]

## [1.20.0] — 2026-11-07
**Resumen:** actualizador de core con merge 3-way, `latest.json` y pantalla de admin;
`/nuevo-proyecto` para crear derivados en un paso; manuales remotos; y la metodología
Roadmap → Brief → Plan con su helper `planning`. Suite: 445 tests verdes (1 skip).

**Notas de actualización**
- Migraciones: ninguna nueva en esta versión (`php system/database/migrate.php` igual es seguro).
- Configurá `CORE_UPDATE_URL=https://<landing>/downloads/latest.json` en el `.env` para usar
  `core-update.php --check|--latest` y la pantalla `/admin/core-update` (permiso nuevo
  `core.update`, solo superadmin por defecto; `CORE_UPDATE_UI=false` la apaga).
- Un proyecto en 1.19 o anterior hace este salto con su updater viejo (sin merge). Después,
  `php system/console/core-update.php --seed-base` completa la base del merge para los próximos.
- Metodología RM → BR → PL: RM-022 sigue en curso; el helper `planning` (PL-001) ya está entregado.

**Correcciones**
- **Paquete completo (`build-download.php`)**: ya no empaqueta los archivos de sesión
  (`system/storage/sessions/sess_*`, venía desde que las sesiones pasaron a storage en v1.16) ni
  la base del merge (`system/storage/core-base/`). Nuevo `--out=<dir>` y test de regresión
  `DownloadPackageTest`. El paquete bajó de 1134 a 629 archivos.
- Test `CronMaster › deshabilitado por defecto`: ya no depende de que la DB local tenga el
  cron master apagado (quita la clave dentro de la transacción con rollback).
- `core-update.php`: el dry-run con `--latest`/`--url` ya no deja el zip descargado en `storage/cache/`.

**`/nuevo-proyecto`: crear un proyecto derivado en un paso + `/release` según el rol.** 12 tests nuevos.
- `php system/console/new-project.php "<nombre>"` (comando `/nuevo-proyecto`, solo desde el
  master): copia limpia en carpeta hermana (sin `.git`, `.env`, `landing/`, `tools/bin/`,
  `planning/`, logs ni runtime), `nsproject.json`, roadmap/logs vacíos, `.env` con APP_KEY nueva,
  crea la base + migra + seed (`--demo` opcional), guarda la base del merge del actualizador y
  hace `git init -b main` + commit inicial **sin remoto del skeleton** (`--remote=` agrega el
  repo propio; nunca hace push). Verifica antes: carpeta libre, base inexistente, MySQL arriba, y
  avisa si el master tiene cambios sin commitear o el lock tiene drift (`--strict` aborta).
  `--dry-run` muestra el plan.
- `App\Services\ProjectCreator` (nombres, filtro de copia, marcador, rol) +
  `system/console/project-role.php` (`master` | `project`; `--mark` para derivados creados a mano).
- `/release` distingue el rol: en un derivado versiona la app (`APP_VERSION`, `CHANGELOG.md`
  de la raíz) y **no** genera paquetes de core. `core-manifest.php` (sin `--check`) y
  `build-core-package.php` se niegan si existe `nsproject.json` (salvo `--force`).
- `Installer::buildEnv()` acepta una plantilla explícita y las claves `APP_KEY` y
  `CORE_UPDATE_URL`; fix: un valor con `$1`/`\1` (ej. una contraseña) ya no se interpreta como
  referencia de regex.

**Actualizador de core: merge 3-way, `latest.json` y pantalla de admin.** 23 tests nuevos.
- **Merge 3-way** de conflictos (`App\Services\ThreeWayMerge`, diff3 en PHP puro, sin git):
  si la app editó un archivo del core y los cambios no se pisan con los del core nuevo, queda
  combinado solo (con backup). Si chocan, el archivo local no se toca y quedan `.new` +
  `.merge` (propuesta con marcadores). El DRY-RUN muestra de antemano qué le toca a cada uno.
- **Base del merge** en `system/storage/core-base/` (gitignored): originales del core
  instalado. Se completa tras cada update y en forma oportunista desde los archivos sin editar;
  `--seed-base[=<zip>|remote]` completa los que se editaron antes de tenerla.
- **`latest.json`**: `build-core-package.php` escribe el índice de releases (sha256 por zip).
  CLI `--check` (exit 3 si hay versión nueva), `--latest`, `--sha256` (verifica `--url`),
  `--no-merge`. `App\Services\CoreRelease`. Config `config/core_update.php` + `.env`
  `CORE_UPDATE_URL`.
- **Pantalla `/admin/core-update`** (Sistema → Actualizar core, permiso nuevo `core.update`,
  solo superadmin por defecto): buscar versión → preparar (descarga + sha256 + plan con vista
  previa del merge + chequeo de permisos de escritura) → aplicar (confirmación escribiendo la
  versión) → historial con revertir. `CORE_UPDATE_UI=false` la apaga.
- `App\Services\CoreUpdate`: orquestador común a CLI y panel (lock anti-concurrencia, defensa
  zip-slip, `report.json`, rollback que también restaura la base). `CoreUpdater::apply()` y
  `rollback()` siguen siendo compatibles con la firma anterior.
- Nota: el merge se aprovecha desde el update **siguiente** al que trae este código (el primer
  salto a esta versión lo hace el updater ya instalado).

**Manuales remotos**: copias vivas de documentación de terceros que cambia seguido. 9 tests nuevos.
- `App\Services\RemoteDocs` + `config/remote_docs.php` (overrideable): baja manuales por HTTPS
  tal cual, detecta cambios por sha256, escribe de forma atómica, guarda la versión anterior como
  `*.prev.md` y la procedencia en `remote-docs.lock.json`. Si llega HTML, una respuesta vacía o
  un error de red, la copia local no se toca.
- CLI `php system/console/docs-sync.php [sync|status] [<clave>] [--dry-run]` + job de cron
  `job:docs:sync` (opt-in).
- Destino `app-agentic/knowledge/remote/` (capa del proyecto: el update de core no lo pisa).
- Declarados: `nubixstore-referencia` (referencia pública de la API de nubixstore, 2.6.0) y
  `buenas-practicas-api` (1.6.0), ambos desde `central.nstore.ar/manuales/`.
- Skill/agente `nubixstore-api`: paso 0 obligatorio de sync antes de trabajar; la referencia viva
  manda sobre el manual histórico v2.1; diferencias conocidas del driver contra la 2.6.0
  documentadas (criterio de éxito `status === 0`, códigos 903/905/910, `utils/connect` interno).
  La integración con nubixstore pasa a estar documentada como opcional.

**Metodología Roadmap → Brief → Plan** (filosofía de trabajo nativa: entender → decidir → codear).
- `agentic/methodology/roadmap-brief-plan.md`: tres artefactos codificados (`RM-`, `BR-`, `PL-`)
  con estados, índices, puertas de calidad (Definition of Ready de brief y de plan), tamaños
  (M → plan obligatorio; L/XL → RM+BR+PL), excepciones (urgencia/imprevisto, registradas y
  regularizables), modelo/esfuerzo por fase ("pensar caro, ejecutar barato") y contrato de
  ejecución por fases apto para loops agénticos.
- Comandos `/roadmap` (`/rm`), `/brief` (`/br`) y `/plan` (`/pl`) con subcomandos para listar,
  crear, refinar, revisar, aprobar, ejecutar y cerrar.
- Agentes + skills nuevos: `product/` → `brief-discovery` (curioso), `creative-explorer`
  (creativo), `devils-advocate` (escéptico; revisa briefs y planes); `planning/` →
  `roadmap-manager`, `plan-architect` (modelo más capaz, esfuerzo máximo), `plan-executor`.
- Scaffold `agentic/templates/planning/` (README + 3 índices + plantillas RM/BR/PL); se copia a
  `planning/` con `/roadmap init`. `planning/` agregado a `app_paths` (el update no lo toca).
- Agentes: campo opcional `effort_hint`. Reglas (§2b), sprints (DoD con plan), logging (códigos
  en walkthrough/log), AGENTS.md, README y guías actualizados.
- **Helper `planning`** (`App\Services\Planning` + `php system/console/planning.php
  init|lint|reindex|sync|review|next|list [--json] [--strict]`): valida códigos, estados, vínculos,
  fechas, propagación e índices; regenera los `INDEX.md` de forma idempotente; próximo código libre;
  sugerencias de próxima fase. Binding en `adapters/php-mvc/conventions.md` § Planning. 16 tests
  (11 del servicio + 5 de estructura), incluido lint del `planning/` real del repo.
- **Adopción en el skeleton**: `docs/roadmap.md` migrado a `planning/roadmap/` (RM-001…RM-021,
  históricos ✅) + RM-022 (esta metodología, 🟡) + PL-001 (el helper, ✅). `docs/roadmap.md` queda
  como registro histórico. `planning/` del master no viaja al zip de descarga (`build-download.php`)
  ni a la copia por git (INSTALL §0).
## [1.19.0] — 2026-07-09
**Cron v2** — features del scheduler de nsCentral portadas a MySQL. 17 tests nuevos (367→384).
- **Cron master**: disparador web `GET|POST /cron/run` protegido por secreto (`?key=` o header
  `X-Cron-Key`, constant-time), allowlist de IP y toggle (503 si off) — permite disparar el cron
  desde EasyCron/`curl` sin depender del cron del SO. Pantalla `/admin/cron/master` (URL, comando
  curl, generar/rotar secreto, IPs). `App\Services\CronMaster`.
- **Tareas tipo URL**: comando `url:https://…` (o `url:POST … {json}`) que hace un HTTP con
  reintentos vía `App\Services\Http`; éxito = 2xx. Convive con shell y `job:`.
- **Historial**: pestaña `/admin/cron/history` con filtros (tarea/estado/mensaje), tipo de
  disparo y duración; botones **"Limpiar OK"** (deja solo errores) y **"Vaciar registro"**.
  `cron_runs` +`trigger_type` +`duration_ms`.
- Fix: colisión de variable `$data` con `View::partial` (extract EXTR_SKIP) en la vista de historial.

## [1.18.0] — 2026-07-09
Mejoras del core derivadas de la **auditoría de nsCheckout** (app derivada: deploy real a
nginx + Mercado Pago embebido). 80 tests nuevos, suite 269→367 verde. Informe y plan en
`agentic/knowledge/skeleton/`.

### v1.18 — DRY, DX y comodidad (P2)
- **`App\Services\Http`**: transporte inyectable (`setTransport`), `get()`, `request()` genérico
  con **retries** (5xx/conexión, backoff); preserva postJson/postRaw/postStream.
- **`Core\Webhooks\SignatureVerifier`**: verificación de webhooks **entrantes** (HMAC simple +
  timestamped Stripe/MP con anti-replay, constant-time).
- **`db:doctor`** (`DbDoctor` + `console/db.php`): conexión, versión, extensiones, detección de
  `caching_sha2_password` de MySQL 8, migraciones.
- **Migración remota por token**: endpoint `/_migrate` (404 salvo `MIGRATE_TOKEN`).
- **`git init` en el installer** (`install.php --git` + recordatorio).

### Extracciones genéricas de nsCheckout
- **`Core\Money`** (multi-moneda, aritmética en centavos), **`Core\Security\Hmac`** (firma de
  payloads; `SignatureVerifier` converge en ella), **`App\Services\DomainStatus`** (DNS + TLS).

## [1.17.0] — 2026-07-09
### Robustez, seguridad y frontera core/app (P1)
- **Frontera código-app**: `app/` en la raíz (namespace `App\`, autoloader multi-dir), fuera de
  `core-lock` → el código de proyecto no genera drift.
- **Paquete de deploy con allowlist** (`config/deploy.php`, `--production`): no sube fuente/tests/
  docs/agentic (532→251 archivos).
- **Aislamiento de tests**: `Database::transaction` + helper `itDb()` (rollback siempre).
- Test de nombre lee `APP_NAME` (renombrar la app no rompe la suite).
- **`Core\ErrorLog`**: error-log server-side siempre-on (traza + rotación) + visor `/admin/logs`.
- **Auth multi-guard**: `config/auth.php` + `Core\Guard` (guards admin/brand/customer, sesiones
  aisladas); `Core\Auth` = fachada del guard default (retrocompat 100%).

## [1.16.0] — 2026-07-09
### Camino dorado de producción (P0)
- **CSP configurable** por env/config + **presets** (mercadopago/stripe/recaptcha) + **merge de
  headers por-respuesta** (`Response::withCsp`/`hasHeader`; `App` no pisa lo del controller).
- **Router: override real** app→core (last-wins); rutas de app al final de `routes.php`.
- **Templates de deploy** `docs/deploy/` (nginx + Apache, docroot=`system/public`) + `deploy:doctor`.
- **`APP_BASE_PATH`** (fallback robusto para subdirectorios/alias).
- **`session.save_path`** → `system/storage/sessions/` (escribible en pools aislados) +
  corrección de rutas de `storage/` en `.gitignore`.

## [1.15.1] — 2026-06-29
- **Docs**: `INSTALL.md` ahora documenta **cómo crear un proyecto nuevo** desde el skeleton
  (Paso 0): copia limpia (clonar+cortar git, o paquete), master vs proyecto, git propio y
  desarrollo en lo "tuyo" (overrides/`app-agentic/`) + update de core. `empezar.md` enlaza ahí.

## [1.15.0] — 2026-06-28
Actualización de core — Fase 5 (capa asistida). **Cierra el roadmap completo** (Fases 1–6).
- **Comando `/actualizar-core <dir|zip|url>`** + agente y skill **`core-updater`**: flujo
  asistido sobre el CLI — dry-run, triage de conflictos (proponiendo moverlos a overrides),
  apply con backup, resolución de `.new`, suite verde y rollback si hace falta.
- **`docs/CORE-UPDATE.md`**: documentación completa del mecanismo (modelo core vs proyecto,
  regla "override no edición", uso del CLI, plan/conflictos/rollback, y cómo publicar core).
- INDEX de agentes actualizado (pm/ `core-updater`).

## [1.14.0] — 2026-06-28
Actualización de core — Fase 6 (publicación). Cierra el círculo: producir el paquete que el
actualizador (Fase 4) consume.
- **`landing/build-core-package.php`**: empaqueta SOLO el core (los archivos de `core-lock.json`)
  + el propio lock en la raíz → `landing/downloads/nsSkeleton-core-<version>.zip`. A diferencia
  de `build-download.php` (zip completo core+app+demo), este trae solo el core para un update
  limpio. Opciones `--regen` (regenera el lock antes) y `--out=<dir>`.
- **`/release` integrado**: regenera `core-lock.json` (sin drift) y genera ambos paquetes
  (completo + core) en cada versión. Actualizados el comando y el skill `release-manager`.
- **Updater `--url`**: `core-update.php --url=<zip>` descarga el paquete de core (p. ej. del
  landing) y lo aplica. Distribución end-to-end: publicar → distribuir → actualizar.

## [1.13.0] — 2026-06-28
Actualización de core — Fase 4 (el actualizador). Deja el flujo de update ejecutable
de punta a punta sobre la frontera (Fase 1), el split (Fase 2) y los puntos de extensión (Fase 3).
- **`App\Services\CoreUpdater`**: compara el lock instalado, el árbol local y el lock nuevo y
  produce un PLAN por archivo (`add`/`update`/`skip`/`conflict`/`conflict_add`/`delete`/
  `delete_modified`). Solo toca archivos del core; lo de la app (untracked) nunca se toca.
  Si la app editó un archivo del core, el nuevo se deja como `.new` (conflicto a mergear).
- **`apply()`** respalda todo lo que toca y escribe `applied.json`; **`rollback()`** revierte
  usando ese backup (restaura pisados/borrados, quita agregados y `.new`).
- **CLI `system/console/core-update.php`**: `--source=<dir|zip>` (DRY-RUN por defecto),
  `--apply` (con backup + confirmación), `--rollback=<dir>`. Tras aplicar corre
  `Migrator::migrateCore()` y actualiza `core-lock.json`/`core-manifest.json`/`VERSION`.
- Manifest: limpiadas las entradas muertas del showcase Clientes (ya eliminado).

## [1.12.0] — 2026-06-28
Actualización de core — Fase 3 (puntos de extensión). Permiten personalizar el core sin
editar sus archivos, para que un update no pise lo del proyecto.
- **Config overrides**: `Core\Config::load('x')` mezcla `config/x.php` (core) +
  `config/overrides/x.php` (app); **la app gana** (deep-merge). Adoptado en `app` (bootstrap)
  y `features` (FeatureFlags). Carpeta `config/overrides/` con README.
- **Rutas de la app**: `config/routes.php` ahora también carga `config/routes.app.php`
  (después del core; la app puede agregar/ganar rutas).
- **Vistas child-theme**: `Core\View` resuelve `app/Views/overrides/{vista}.php` antes que la
  del core. Permite overridear layout/parcial/vista sin tocar el core.
- **Migraciones core/app separadas**: las de la app van en `database/migrations/app/`;
  `Migrator::migrate()` corre core→app, el actualizador usa `Migrator::migrateCore()`. El
  generador de módulos ahora emite las migraciones al dir de la app.
- Manifest: los READMEs de los dirs de override son core; lo que la app cree ahí queda
  fuera del lock (untracked = el updater no lo toca).

## [1.11.0] — 2026-06-28
Actualización de core — Fase 1 (frontera) + Fase 2 (split agéntico). Base para que los
proyectos derivados puedan actualizar el core sin afectar lo ya desarrollado.
- **Frontera declarativa core/app**: `core-manifest.json` (reglas de propiedad: `core_paths`/
  `app_paths`/`exclude`) + `core-lock.json` (snapshot con sha256 de cada archivo core).
  Regla: *el core es dueño solo de lo que lista; todo lo demás es del proyecto*.
- **Generador** `system/console/core-manifest.php` (genera el lock; `--check` detecta drift).
  Lo correrá `/release`. El showcase **Clientes** queda clasificado como app (no lo pisa un update).
- **Split agéntico**: nuevo árbol `app-agentic/` (rules/agents/skills/knowledge/templates/modules),
  **propiedad del proyecto, nunca pisado**. `project-rules.md` → renombrado a **`core-rules.md`**
  (reglas del core) y nuevo **`app-rules.md`** (reglas del proyecto).
- **Precedencia app > core**: `rules.md` y `AGENTS.md` cargan core→app; override de agente/skill
  por nombre (app-agentic sombrea a agentic). Referencias actualizadas en skills e installer.

## [1.10.0] — 2026-06-28
Core agéntico: conector de ecommerce + agentes/skills de integración (247 tests verdes).
- **Conector genérico de tiendas** (`App\Services\Ecommerce`): contrato `StoreConnector`,
  base `AbstractStoreConnector` (cURL con TLS verificado, respuesta normalizada
  `['ok','status','data','error']`) y `StoreConnectorFactory` (por config o `settings` cifrados).
- **Drivers**: `NubixstoreConnector` (referencia, login lazy + token cacheado,
  `API_MANUAL_VERSION=2.1`) + `Shopify`, `TiendaNube`, `WooCommerce`, `Magento`.
- **`config/ecommerce.php`**: registro de plataformas con auth, base URL, doc y esquema de
  credenciales — "información lista al instalar".
- **Knowledge** (carpeta nueva `agentic/knowledge/`): manual API nubixstore v2.1 portado de
  nsCentral + doc genérico `ecommerce/ecommerce-apis.md`.
- **Skills**: `ecommerce-integration` (genérico) y `nubixstore-api` (específico, con manual+versión).
- **Agentes** (categoría nueva `integrations/`): `ecommerce-integrator` y `nubixstore-api`.

## [1.9.0] — 2026-06-27
Mejoras portadas de nsCentral (Track I, 228 tests verdes).
- **I1** Conector IA: proveedor **Anthropic (Claude)** además de OpenAI/Deepseek, con
  request/response por estilo (`/chat/completions` vs `/v1/messages`) y streaming SSE propio.
- **I2** **Cronmaster v2**: constructor de horario por presets + descripción humana
  (`ScheduleBuilder`), prioridad/timeout por tarea y throttle del runner.
- **I3** Archivos: **links públicos por token** (`/a/{token}`) para compartir/descargar sin login.
- **I4** Sistema de **avisos/alertas** (patrón de providers) + widget en el dashboard
  (jobs fallidos, cron con error, cola saturada, backup viejo).

## [1.8.0] — 2026-06-23
API & procesos (Track G, 207 tests verdes).
- **G1** API+: paginación + filtros + `?q` + orden, scopes por token (read/write) y
  rate-limit (429).
- **G2** Doc OpenAPI 3.0.3 autogenerada (`/api/openapi`, `/api/openapi.json`) + visor
  self-contained en `/api/docs`.
- **G3** Cola de jobs generalizada (`JobQueue` + contrato `Job`): reintentos con backoff,
  worker CLI + cron `queue:work`, panel de monitoreo.
- **G4** Webhooks salientes (`Webhook`): entrega por la cola de jobs con firma HMAC-SHA256,
  panel de suscripciones, evento `admin.created`.

## [1.7.0] — 2026-06-23
Seguridad & cuentas (Track F, 194 tests verdes).
- **F1** 2FA / TOTP (`Totp` RFC 6238) con desafío en el login y secreto cifrado en reposo.
- **F2** RBAC editable por panel (matriz de roles) + overrides de permisos por usuario
  (allow/deny) con precedencia usuario > rol.
- **F3** Notificaciones in-app (`Notifier`): campanita con contador + bandeja.
- **F4** Auditoría con diff (antes/después): `Audit::logChange` + visor que muestra old→new.

## [1.6.0] — 2026-06-23
Generador++ y datos (Track E, 178 tests verdes).
- **E1** Relaciones FK (`belongsTo`) en el generador: `cliente_id:fk:clientes` → FK +
  `<select>` poblado + etiqueta en el listado (`RelationOptions`).
- **E2** Validaciones por campo (`Validator`: required/email/numeric/integer/unique) con
  errores y repoblado en el formulario.
- **E3** Exportar listados a CSV / Excel / PDF (`Exporter` + `Response::download`).
- **E4** Soft-delete + papelera (restaurar / eliminar definitivo) con `deleted_at`.
- **E5** Búsqueda global a través de los módulos (`GlobalSearch` + buscador en el topbar).

## [1.5.0] — 2026-06-23
UX & DX (150 tests verdes). i18n quedó fuera a pedido.
- **H1** Streaming de respuestas de IA (SSE): `Http::postStream`, `chatStream`, endpoint
  `/admin/ai/stream` y UI con EventSource. Cierra el diferido de C6.
- **H3** Dark mode / theming: `theme.css` (overrides `.dark` sin recompilar Tailwind),
  toggle persistente e init anti-flash.
- **H4** Feature flags (`config/features.php` + override por settings) y healthcheck:
  `/health` ampliado + panel "Estado" (cola, disco, último backup, versión).

## [1.4.0] — 2026-06-23
Roadmap v1.1→v1.4 completo (137 tests verdes).
### v1.1 Endurecimiento
- Suite de tests propia, cifrado de credenciales (AES-256-GCM), hardening de login
  (rate-limit + mi-perfil), recuperación de contraseña por email, cabeceras de seguridad
  + páginas 404/500/403, assets locales (Tailwind compilado + Alpine/Chart.js).
### v1.2 Aceleradores
- Generador de módulos CRUD (`/nuevo-modulo`), migraciones con rollback, búsqueda +
  paginación, settings generales por panel, auditoría de acciones, comandos `/sprint` y `/release`.
### v1.3 Capacidades extendidas
- API REST con Bearer tokens, RBAC (roles/permisos), cron con jobs internos + lock,
  emails con plantillas + cola, file manager (validación/rename/move/preview), IA con
  librería de prompts + system prompt.
### v1.4 Onboarding y ejemplos
- Instalador interactivo, seeders demo, módulo showcase (Clientes), deploy FTP/git.

## [1.0.0] — 2026-06-22
Primer MVP de nsSkeleton.
### Novedades
- Capa agéntica agnóstica (arranque por `AGENTS.md`): rules, 16 skills, agentes, comandos,
  metodología (logging, bug-tracking, sprints), adapter `php-mvc`, instalador Q&A.
- Sistema base (PHP MVC propio + MySQL): núcleo MVC, login admin + perfiles, cronmaster,
  emails (SMTP propio), backup/restore, gráficos + dashboard, file manager, conector de IA.
- Landing de descarga + paquete ZIP.

<!-- Las próximas versiones se agregan ARRIBA de esta línea con /release. -->
