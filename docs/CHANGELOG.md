# Changelog

Todas las versiones notables de nsSkeleton. Formato basado en versionado semántico.
Lo mantiene el comando `/release` (ver `agentic/commands/release.md`).

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
