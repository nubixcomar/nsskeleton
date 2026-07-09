# Roadmap de funcionalidades y versiones

> Roadmap detallado por versiones. Cada versión agrupa funcionalidades con estado.
> Los agentes actualizan el estado al completar features (ver
> [`agentic/methodology/sprints.md`](../agentic/methodology/sprints.md)).

## Leyenda de estados
`📋 Pendiente` · `🟡 En progreso` · `✅ Completo` · `🧪 En testing` · `⛔ Bloqueado` · `❌ Descartado`

---

## v0.1 — Fundaciones (capa agéntica + esqueleto)
| # | Funcionalidad                                   | Estado | Sprint | Notas |
|---|--------------------------------------------------|--------|--------|-------|
| 1 | Scaffold capa agéntica (agentic/)               | ✅     | S0     |       |
| 2 | Metodología: logging, bug-tracking, sprints     | ✅     | S0     |       |
| 3 | Instalador (Q&A) + arranque agnóstico AGENTS.md | ✅     | S1     | sin .claude |
| 4 | Portar 16 skills genéricos + agentes + comandos | ✅     | S1     | de nubixstore |
| 5 | Núcleo MVC del sistema base                     | ✅     | S1     | probado: /, /health, 404 |
| 6 | Login admin + perfiles                          | ✅     | S1     | render+guard probados; flujo DB pendiente MySQL up |

## v0.2 — Sistema base operativo
| # | Funcionalidad                                   | Estado | Sprint | Notas |
|---|--------------------------------------------------|--------|--------|-------|
| 1 | Login admin + gestión de perfiles               | ✅     | S1     | render+guard probados |
| 2 | Diseño backend (Tailwind + Alpine, responsive)  | ✅     | S1     | layout admin + sidebar responsive |
| 3 | Cron / programador de tareas (cronmaster)       | ✅     | S1     | parser cron 11/11 tests; guard OK |
| 4 | Configuración y envío de emails                 | ✅     | S1     | SMTP propio; error-path OK; guard OK |
| 5 | Backup y restauración automática                | ✅     | S1     | ZIP real probado (163 entradas); guard OK |
| 6 | Librería de gráficos (barras, torta, dashboards)| ✅     | S1     | Chart.js + dashboard con datos reales; partial probado |
| 7 | File manager (carpetas/subcarpetas, uploads)    | ✅     | S1     | 13/13 tests reales; anti-traversal; guard OK |
| 8 | Conector de IA (OpenAI/Deepseek, credenciales)  | ✅     | S1     | 7/7 tests; error-path OK; guard OK |

## v1.0.0 — Listo para iniciar proyectos · 🚀 Released 2026-06-22
| # | Funcionalidad                                   | Estado | Sprint | Notas |
|---|--------------------------------------------------|--------|--------|-------|
| 1 | Portar skills genéricos                         | ✅     | S1     | hecho en v0.1 |
| 2 | Punteros opt-in del instalador (OpenAI)         | ✅     | S1     | documentados |
| 3 | Documentación completa de uso                   | ✅     | S1     | docs/INSTALL.md + READMEs por módulo |

---

---

## v1.1 — Endurecimiento y producción · ✅ COMPLETA (2026-06-23)
> Verificación: **cada fase agrega tests versionados** a `tests/` (la suite es la fase 1).

| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **A6** Suite de tests formal (runner `tests/run.php`)      | ✅     | 37/37 tests verdes; fundación lista |
| 2 | **A1** Cifrado en reposo de credenciales (SMTP, IA) + `APP_KEY` | ✅ | Crypto AES-256-GCM; 44/44 tests |
| 3 | **A2** Hardening login: rate-limit/lockout, mi-perfil, cambiar contraseña | ✅ | lockout 5 intentos; e2e OK; 46/46 tests |
| 4 | **A3** Recuperación de contraseña por email                | ✅     | token hash+TTL, un uso; e2e OK; 49/49 |
| 5 | **A4** Cabeceras de seguridad + páginas 404/500 estilizadas | ✅    | 5 headers + CSP; 404/500; 56/56 tests |
| 6 | **A5** Assets locales (Tailwind standalone + Alpine/Chart.js) | ✅  | CSS 28KB compilado + JS vendor; CSP endurecida; 62/62 |

## v1.2 — Aceleradores de desarrollo · ✅ COMPLETA (2026-06-23)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **B1** Generador de módulos CRUD (`/nuevo-modulo` + skill) ⭐ | ✅  | CRUD e2e OK; demo "Producto" vivo; 69/69 |
| 2 | **B2** Migraciones con rollback (`down`) + estado          | ✅     | Migrator up/rollback/status/fresh; @DOWN; 72/72 |
| 3 | **B3** Búsqueda + paginación reutilizable en listados      | ✅     | Paginator + partials; en perfiles y generador; 80/80 |
| 4 | **B4** Settings generales por panel (nombre, TZ, branding) | ✅     | AppSettings + panel + logo; e2e OK; 85/85 |
| 5 | **B5** Auditoría de acciones de admin + visor              | ✅     | audit_log + visor; integrado en login/CRUD/settings; 87/87 |
| 6 | **B6** Completar `/sprint` y `/release` genéricos          | ✅     | comandos+skills+agentes pm+plantillas+changelog; 93/93 |

## v1.3 — Capacidades extendidas · ✅ COMPLETA (2026-06-23)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **C1** Capa API REST (tokens + CRUD desde modelos)         | ✅     | Bearer tokens + CRUD genérico; e2e OK; 94/94 |
| 2 | **C2** Roles/permisos real (RBAC)                          | ✅     | Rbac + guards + menú filtrado + visor; e2e 403/200 OK; 100/100 |
| 3 | **C3** Cron con jobs internos (callables) + lock           | ✅     | Jobs registry + job:<n> + flock anti-solapamiento; 105/105 |
| 4 | **C4** Emails con plantillas HTML + cola de envío          | ✅     | layout+plantilla, email_queue+worker job:email:queue; 109/109 |
| 5 | **C5** File manager: extensiones/tamaño, renombrar, preview | ✅    | whitelist+límite, rename/move anti-traversal, thumbnails; 118/118 |
| 6 | **C6** IA: librería de prompts + system prompt + streaming | ✅     | prompts {{var}} + system prompt; streaming diferido (cliente no-streaming); 125/125 |

## v1.4 — Onboarding y ejemplos · ✅ COMPLETA (2026-06-23)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **D1** Instalador interactivo (skill que ejecuta el Q&A)   | ✅     | skill/instalar + Installer (buildEnv/stackDoc) + CLI dry-run; 129/129 |
| 2 | **D2** Datos demo / seeders de ejemplo                     | ✅     | DemoSeeder seed/undo idempotente + CLI; 130/130 |
| 3 | **D3** Módulo showcase end-to-end (generado con B1)        | ✅     | módulo Clientes (web+API) + doc; stamp único; 133/133 |
| 4 | **D4** Deploy FTP/git desde el `.env`                      | ✅     | Deployer + CLI dry-run/--run (opt-in) + skill/deploy; 137/137 |

---

## v1.5 — UX & DX · ✅ COMPLETA (2026-06-23) · (i18n excluido a pedido)
> Cada fase: build + tests versionados + verificación + walkthrough.

| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **H1** Streaming de respuestas de IA (SSE)                 | ✅     | Http::postStream + parseSseLine + chatStream + endpoint SSE + UI EventSource; 142/142 |
| 2 | **H3** Dark mode / theming                                 | ✅     | theme.css overrides .dark + toggle + init anti-flash; 146/146 |
| 3 | **H4** Feature flags + healthcheck/métricas                | ✅     | FeatureFlags + Health, /health ampliado, panel Estado, banner por flag; 150/150 |

---

## v1.6 — Generador++ y datos (Track E) · ✅ COMPLETA (2026-06-23)
> Cada fase: build + tests versionados + verificación + walkthrough.

| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **E1** Relaciones en el generador (FK `belongsTo` + select) | ✅    | tipo fk + parseRelations + RelationOptions + FK en migración + select + etiqueta; 158/158 |
| 2 | **E2** Validaciones por campo (requerido/email/numérico/único) | ✅ | Validator + parseRules + errores/old en form; 165/165 |
| 3 | **E3** Exportar listados (CSV / Excel / PDF)               | ✅     | Exporter + Response::download + botones en índice; 171/171 |
| 4 | **E4** Soft-delete + papelera (restaurar)                  | ✅     | deleted_at + Paginator filter + trash/restore/force + vista papelera; 173/173 |
| 5 | **E5** Búsqueda global + filtros por columna               | ✅     | GlobalSearch + SearchController + buscador en topbar; 178/178 |

## v1.7 — Seguridad & cuentas (Track F) · ✅ COMPLETA (2026-06-23)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **F1** 2FA / TOTP para el login admin                      | ✅     | Totp RFC6238 + challenge en login + setup/enable/disable + secreto cifrado; 185/185 |
| 2 | **F2** RBAC editable por panel + permisos por usuario      | ✅     | catálogo + override de rol (settings) + user_permissions allow/deny + matriz/tri-estado; 188/188 |
| 3 | **F3** Notificaciones in-app (campanita) + bandeja         | ✅     | Notifier + tabla + campanita/badge + bandeja + notifyAll al crear admin; 190/190 |
| 4 | **F4** Auditoría con diff (antes/después)                  | ✅     | Audit::diff/logChange + columna changes + visor con old→new; 194/194 |

## v1.8 — API & procesos (Track G) · ✅ COMPLETA (2026-06-23)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **G1** API+ (paginación, filtros, scopes por token, rate-limit) | ✅ | Paginator meta + filtros/orden/q + scopes read/write + RateLimiter 429; 196/196 |
| 2 | **G2** Doc OpenAPI/Swagger autogenerada                    | ✅     | OpenApiGenerator 3.0.3 + /api/openapi(.json) + visor /api/docs; 200/200 |
| 3 | **G3** Cola de jobs generalizada (no solo emails) + panel  | ✅     | JobQueue + contrato Job + reintentos/backoff + CLI + cron queue:work + panel; 204/204 |
| 4 | **G4** Webhooks salientes (eventos → URLs externas)        | ✅     | Webhook + entrega por cola + firma HMAC + panel + evento admin.created; 207/207 |

---

## v1.9 — Mejoras portadas de nsCentral (Track I) · ✅ COMPLETA (2026-06-27)
> Ideas tomadas del proyecto nsCentral (`C:\xampp\htdocs\impulso`, persistencia JSON) y
> adaptadas a la arquitectura MVC + MySQL de skeleton. Cada fase: build + tests + verificación + walkthrough.

| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **I1** Conector IA: proveedor Anthropic (Claude) + adapters | ✅    | style openai/anthropic + buildRequest/extractContent/parseSseAnthropic; 213/213 |
| 2 | **I2** Cronmaster v2 (presets + describe + prioridad/timeout/throttle) | ✅ | ScheduleBuilder + constructor Alpine + orden por prioridad + timeout/throttle en runner; 222/222 |
| 3 | **I3** Archivos: links públicos por token (`/a/{token}`)   | ✅     | FileShare + PublicFileController + toggle compartir/revocar + badge; 224/224 |
| 4 | **I4** Sistema de avisos/alertas (providers) + widget dashboard | ✅ | AlertProvider + AlertService + 4 providers (jobs/cron/cola/backup) + widget; 228/228 |

---

## Actualización de core (v1.11–v1.15) · ✅ COMPLETO (2026-06-28)
> Mecanismo para que un proyecto derivado actualice su core **sin afectar lo ya desarrollado**.
> Detalle en [`CORE-UPDATE.md`](CORE-UPDATE.md). Decisiones: distribución zip+manifiesto,
> frontera por manifiesto+lock (sin mover archivos), regla "override no edición".

| # | Fase                                                       | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | Frontera declarativa (`core-manifest.json` + `core-lock.json`) | ✅ | generador `core-manifest.php` + `--check` drift |
| 2 | Split agéntico (`core-rules`/`app-rules`/`app-agentic/`)   | ✅     | precedencia app > core + override por nombre |
| 3 | Puntos de extensión (Config overrides, `routes.app.php`, vistas child-theme, migraciones app) | ✅ | overridear sin editar el core |
| 4 | Updater (`CoreUpdater` + `core-update.php`)               | ✅     | plan/apply/rollback + conflictos `.new` |
| 6 | Publicación (`build-core-package.php` + `/release` + `--url`) | ✅  | zip de core derivado del lock |
| 5 | Capa asistida (`/actualizar-core` + agente/skill + doc)   | ✅     | dry-run, triage, verificación |

### Backlog del core — mejoras futuras 📋
> Mejoras opcionales sobre el mecanismo de actualización de core. No bloquean: el mecanismo ya
> funciona end-to-end. Tomar cuando aporten valor.

| # | Mejora                                                     | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **Merge 3-way asistido** para conflictos                  | 📋     | hoy el conflicto se deja como `.new` para merge manual; un 3-way (base=lock instalado, ours=local, theirs=core nuevo) reduciría el trabajo manual |
| 2 | **`latest.json`** en el landing para que `--url` resuelva "la última" | 📋 | el updater podría chequear/avisar si hay versión nueva sin pasar la URL exacta |
| 3 | **UI de admin** para disparar/monitorear el update         | 📋     | botón "Actualizar core" con plan, confirmación y resultado desde el panel (hoy es CLI/comando) |

---

## v1.16 — Camino dorado de producción (P0) · ✅ COMPLETA (2026-07-09)
> Deriva de la **auditoría de nsCheckout** (deploy real nginx + integración MP embebido).
> Plan detallado: [`agentic/knowledge/skeleton/plan-mejoras-post-nscheckout.md`](../agentic/knowledge/skeleton/plan-mejoras-post-nscheckout.md).

| # | Feature                                                    | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **CSP configurable** por env/config + merge por-respuesta (1.1) | ✅ | `config/security.php`, `Security::csp()`, `Response::withCsp/hasHeader`, `App` merge; 9 tests |
| 2 | **Presets de CSP** por integración (mercadopago/stripe/recaptcha) (1.1) | ✅ | `preset_definitions` + `CSP_PRESETS` env; incluido en los 9 tests |
| 3 | **Router: override real** app→core (last-wins) + doc (1.2) | ✅     | `Router::add` reemplaza por method+path; app al final de routes.php; 4 tests |
| 4 | **Templates nginx + Apache** + docroot=`system/public` (1.3) | ✅   | `docs/deploy/` (nginx/apache/README camino dorado) |
| 5 | **`APP_BASE_PATH`** explícito por env (1.3)                | ✅     | `Url::base()` override + `Request` lo reutiliza; 5 tests |
| 6 | **`session.save_path`** → `storage/sessions/` (1.4)       | ✅     | `Session::configureSavePath`; `SESSION_PATH`; gitignore corregido; 3 tests |
| 7 | **`deploy:doctor`** (docroot/rewrite/php/permisos) (1.3)  | ✅     | `DeployDoctor` + `deploy.php doctor`; 4 tests |

## v1.17 — Robustez, seguridad y frontera core/app (P1) · ✅ COMPLETA (2026-07-09)
| # | Feature | Estado | Notas |
|---|---------|--------|-------|
| 1 | Frontera código-app fuera de core-lock (1.6) | ✅ | autoloader multi-dir + `app/` raíz + README; 0 drift; 4 tests |
| 2 | Paquete de deploy con allowlist (1.5) | ✅ | `config/deploy.php` + `--production`; 532→251 archivos; 5 tests |
| 3 | Aislamiento de tests (rollback/DB test) (1.7) | ✅ | `Database::transaction` + helper `itDb()`; 4 tests + doc |
| 4 | Test de nombre por `APP_NAME` (1.7) | ✅ | unit+feature leen `APP_NAME`; rename no rompe |
| 5 | Error-log server-side siempre-on + rotación (1.8) | ✅ | `Core\ErrorLog` (traza+rotación+tail); `App` lo usa; 4 tests |
| 6 | Visor de logs en /admin (1.8) | ✅ | `/admin/logs` + `LogViewerController` + permiso `logs.view`; 5 tests |
| 7 | Auth multi-guard / login por rol (1.9) | ✅ | `config/auth.php` + `Core\Guard` (sesiones aisladas); `Auth` fachada retrocompat; 7 tests |

## v1.18 — DRY, DX y comodidad (P2) · ✅ COMPLETA (2026-07-09)
| # | Feature | Estado | Notas |
|---|---------|--------|-------|
| 1 | `Core\Http` client (1.10) | ✅ | `Http` con transporte inyectable + `get`/`request`/retries; 7 tests |
| 2 | `Core\Webhooks\SignatureVerifier` entrantes (1.10) | ✅ | HMAC simple + timestamped (Stripe/MP) anti-replay; 6 tests |
| 3 | `db:doctor` | ✅ | `DbDoctor` + `console/db.php`; detecta caching_sha2_password; 4 tests |
| 4 | Migración remota protegida por token | ✅ | `/_migrate` token-gated (404 por defecto); `RemoteMigrate`; 4 tests |
| 5 | `git init` en el installer | ✅ | `Installer::gitInit` + `install.php --git` + recordatorio; 4 tests |

## Extracciones de nsCheckout (sección 2 del informe) · ✅ RESUELTAS (2026-07-09)
> Piezas construidas en nsCheckout evaluadas para el core. Detalle en el plan.

| Pieza | Resolución |
|-------|-----------|
| `Money` (multi-moneda) | ✅ extraída → `Core\Money` (8 tests) |
| `Hmac` (firma payloads) | ✅ extraída → `Core\Security\Hmac`; `SignatureVerifier` convergió (6 tests) |
| `DomainStatus` (DNS+TLS) | ✅ extraída → `App\Services\DomainStatus` (5 tests) |
| Exportadores CSV/Excel/PDF | ✅ ya en core (`App\Services\Exporter`) |
| Helper CSRF JSON/fetch | ✅ ya en core (`Session::csrf`/`verifyCsrf`) |
| `payments` (PaymentGateway) | ⏭️ plantilla `/nuevo-modulo` (app-específico); core ya aporta Http/CSP/Money/webhooks |
| `integrations` (BaseCatalogConnector) | ⏭️ ya existe framework ecommerce (`Services/Ecommerce/`, v1.10) |
| `marketing/consent` (GTM/GA4/Pixel) | ⏭️ plantilla (front/marketing, no core) |

---

## Versionado
nsSkeleton usa **versionado semántico** (`MAJOR.MINOR.PATCH`) para el framework.
Los proyectos derivados pueden elegir su propio esquema en el instalador.
