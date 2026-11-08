---
code: PL-002
title: Hardening del core 1.20.3 (permisos admin, sesión, frontera core/app, migraciones)
status: ✅ Completado
brief: BR-001
rm: —
origin: brief
exception: sin RM por decisión del humano (hardening, no feature visible) — ver BR-001
size: L
replaces: —
planned_with: claude-opus-5-5 (esfuerzo alto, código verificado línea por línea) + revisión independiente
execution_hint: estándar / esfuerzo medio (F2, F3 y F6 piden atención: tocan muchos archivos)
loop_ready: true
created: 2026-11-08
updated: 2026-11-08
---

# PL-002 — Hardening del core 1.20.3 (permisos admin, sesión, frontera core/app, migraciones)

> **Plan = contrato ejecutable.** Otro agente, en otra sesión, con un modelo más económico,
> tiene que poder ejecutarlo sin hacer preguntas. Si una fase requiere "pensar mucho", falta
> detalle acá → marcar la fase `⛔ bloqueada` y volver a `/plan`.

> **Reglas para todas las fases**
> 1. **Lock antes de testear:** toda fase que toca archivos del core (`system/`, `tests/`, `agentic/`,
>    `docs/`, `core-manifest.json`) corre `php system/console/core-manifest.php` (regenera
>    `core-lock.json`) **antes** de `php tests/run.php`; si no, `CoreBoundaryTest › sin drift` falla.
> 2. **Prohibido construir controladores admin en tests** (`new XController`, `new class extends
>    AdminController {}`): el constructor hace `redirect()->send(); exit;` y corta la suite con un
>    falso verde. Usar `ReflectionClass::newInstanceWithoutConstructor()`.
> 3. `tests/run.php` no filtra por grupos: la verificación es siempre la suite completa.
> 4. Las fases son **secuenciales** (todas regeneran `core-lock.json`).

## 1. Resumen
Cierra NSK-01 (permisos), NSK-02 (drift en derivados), NSK-03 (rollback) y dos observaciones
(usuario inactivo, `totp_secret`) del informe de nsWMS (BR-001). Al terminar: toda ruta de un
controlador admin exige un permiso declarado o está marcada "solo sesión"; un controlador sin
declaración cae en un default seguro y configurable; el dashboard queda abierto a cualquier usuario
logueado pero cada bloque se muestra (y se calcula) solo con su permiso; un usuario inactivo queda
afuera; los derivados pueden usar puntos de extensión, módulos generados y tests propios sin drift; el
updater solo marca conflicto donde el core cambió; `rollback`/`fresh` operan sobre core + app.
Se publica como **1.20.3** y se lleva a nsWMS con el actualizador.

## 2. Fuentes y trazabilidad
- Brief: [BR-001](../briefs/BR-001-hallazgos-core-nswms-1-20-2.md) · Roadmap: — (excepción)
- Informe original: <https://claude.ai/artifact/2LkSg7ob2wpsJctW8P3DSW> · Triage: `logs/walkthrough/2026-11-08_0900_security-audit_triage-hallazgos-nswms.md`

| Criterio de éxito (BR-001) | Se cubre en | Se verifica con |
|----------------------------|-------------|-----------------|
| CS1 viewer → 403 donde corresponde | F2, F3 | `AdminPermissionsTest` + E2E HTTP (F10) |
| CS2 controlador sin declarar no queda abierto | F2, F4 | `AdminPermissionsTest › default` |
| CS3 test de barrido de rutas | F5 | `AdminPermissionsTest › barrido` |
| CS4 usuario inactivo/borrado afuera | F1 | `GuardActiveTest` (it + finally) + E2E (F10) |
| CS5 sin `totp_secret` en `Auth::user()` | F1 | `GuardActiveTest` |
| CS6 sin drift por extensiones en derivados | F6 | `CoreManifestCheckTest` |
| CS7 `make-module` no toca archivos del core | F7 | aserción en `CoreBoundaryTest`: el lock no contiene `system/config/modules_menu.php` |
| CS8 sin conflicto si el core no cambió el archivo | F7 | `CoreUpdaterTest › skip-core-unchanged` |
| CS9 rollback/fresh core + app | F8 | `MigratorTest` (it + finally, dirs temporales) |
| CS10 validación en nsWMS | F10 | update de nsWMS + suite + aviso a nsWMS |

## 3. Contexto técnico actual
- `system/app/Controllers/Admin/AdminController.php`: constructor exige `Auth::check()` (L19-26);
  `requirePermission($p)` es **`protected`** (L38), renderiza `errors/403` y hace **`exit`**.
- Llaman `requirePermission` en su **constructor** (después de `parent::__construct()`), 12 controladores:
  `AdminUser`(admins.manage) · `ApiToken`(api.manage) · `Audit`(audit.view) · `Backup`(backup.manage) ·
  `CoreUpdate`(core.update, después del chequeo `ui_enabled`) · `Files`(files.manage) ·
  `JobQueue`(cron.manage) · `Mail`(mail.manage) · `Role`(admins.manage) · `Settings`(settings.manage) ·
  `UserPermission`(admins.manage) · `Webhook`(api.manage).
  Y 1 **por acción**: `LogViewerController::index()` (L23, `logs.view`).
- Sin permiso: `Cron`, `Ai` (incluye `saveSettings`), `Health`, `Search`, `Dashboard` (usa `Rbac::can`
  solo para filtrar la galería de módulos), `Profile`, `Notification`, `TwoFactor`.
  `Auth`/`ForgotPassword` no heredan de `AdminController` (públicos, correcto).
- Dashboard: `DashboardController::index()` (L25-60) arma `stats`, `charts`, `alerts` (`AlertService::all()`),
  `health` (`Health::full()`, se muestra en `partials/dashboard/feed.php:30-36`: PHP, disco, último
  backup, versiones), `kpis`, `novedades`, `recentAudit`, `recentJobs`, `modules`. Bloques por preset en
  `system/config/dashboard.php` (`alerts, kpis, charts, feed, recent, modules`), resueltos por
  `App\Services\Dashboard::blocks()`. `admin/dashboard.php:23-24` tiene botones fijos a `/admin/health` y
  `/admin/search`. El login siempre redirige a `/admin` (`AuthController.php:24, 68, 107`).
- `system/console/make-module.php` L145: plantilla `final class {$model}Controller extends AdminController`
  sin constructor ni permiso (heredoc con `\$` escapados); escribe `system/config/modules_menu.php`
  (L543-547) en un `BASE_PATH` fijo (L21), y ese archivo **está en `core-lock.json`**.
- Lectores de `modules_menu.php` (todos con `is_file()`): `layouts/admin.php`, `GlobalSearch::search`,
  `Breadcrumb`, `make-module.php`. `ProjectCreator::shouldCopy` lo copia a los proyectos nuevos.
- `Rbac::can($perm, ?$user)`: superadmin `*`; overrides por usuario; `permissionsFor($role)` lee override
  de settings `rbac_roles` o `config/permissions.php`. `Rbac::config()` es **privado**; `Rbac::catalog()` público.
- `Core\Router` (`Router.php:52-80`): `private array $routes` = lista de `{method, regex, handler, params}`
  (**sin path**) y `private array $index` = `["GET /admin/cron" => posición en $routes]`.
  `system/config/routes.php` devuelve `static function (Router $router): void` y solo necesita `BASE_PATH`.
- `Core\Guard`: `check()` = `Session::has(key)` (puro: `GuardTest` lo usa con ids falsos y sin DB —
  **no** cambiar su contrato); `user()` = `SELECT *` + `unset password`; `verifyCredentials()` ya respeta
  `cfg['active_column']` (admin: `'active'`). Tras `logout()`, `AuthController::showLogin` vuelve a
  chequear `Auth::check()` → sin bucle de redirect. El 2FA pendiente usa `2fa_pending_user`, no `AdminController`.
- `totp_secret`: todos sus usos hacen `SELECT totp_secret …` propio (`AuthController:94`,
  `TwoFactorController:31,54`) → se puede quitar de `user()` sin romper nada.
- `Core\Config::get` usa `array_key_exists` (L59) → un override con `null` sí se respeta; cache en
  `private static array $cache` (L26), `Config::flush()` público.
- `system/console/core-manifest.php`: `$root = dirname(__DIR__, 2)` fijo (L24); `--check` regenera el
  lock completo en memoria y compara texto (L145-152); ya se niega a regenerar (sin `--check`) si hay
  `nsproject.json` (L32).
- `CoreUpdater::plan()`: local≠nuevo y local≠viejo ⇒ `conflict`, aunque `viejo === nuevo`.
  `core-update.php` muestra el detalle solo de `conflict`/`conflict_add`/`delete_modified` (≈L259) y
  etiqueta `skip` como "sin cambios" (≈L244).
- `Migrator`: `schema_migrations(migration PK, applied_at DATETIME)`, sin id. `rollback($n, ?$dir)` con
  `$dir ?? self::dir()` (solo core). `migrate.php`: `rollback` usa `(int) ($argv[2] ?? 1)` (L63) y no pasa
  dir; `fresh` cuenta core+app y revierte solo core (L69-74). `MigratorTest` existente usa `it` + `finally`
  con dirs temporales (L10-58); el DDL hace commit implícito (no sirve `itDb`).
- Empaquetador de core: `landing/build-core-package.php`.

## 4. Diseño

### 4.1 Permiso declarado por controlador (NSK-01)
```
abstract class AdminController:
    const AUTH_ONLY = ''          // "solo sesión", a propósito

    __construct():
        si !Auth::check() → redirect login (igual que hoy)
        si Auth::user() === null → Auth::logout(); flash 'Tu sesión terminó o tu cuenta está desactivada.'; redirect login; exit
        $perm = $this->requiredPermission()
        si $perm !== self::AUTH_ONLY → $this->requirePermission($perm)

    protected requiredPermission(): string
        // Default para controladores que no declaran (módulos generados viejos, controladores de app).
        // config/security.php 'admin_default_permission' (default 'modules.manage'); null = solo sesión (legado).
        $d = Config::get('security', 'admin_default_permission', 'modules.manage')
        devolver is_string($d) ? $d : self::AUTH_ONLY
```
Nombre `requiredPermission()` (no `permission()`) para no chocar con acciones `permission(Request)` de
derivados. `requirePermission()` sigue `protected` para checks por acción (compatibilidad).

| Controlador | `requiredPermission()` |
|-------------|------------------------|
| AdminUser, Role, UserPermission | `admins.manage` |
| ApiToken, Webhook | `api.manage` |
| Audit | `audit.view` |
| Backup | `backup.manage` |
| CoreUpdate | `core.update` |
| Files | `files.manage` |
| JobQueue, **Cron** | `cron.manage` |
| LogViewer, **Health** | `logs.view` |
| Mail | `mail.manage` |
| Settings | `settings.manage` |
| **Ai** | `ai.manage` |
| **Search** | `modules.manage` (solo busca en módulos generados) |
| **Dashboard, Profile, Notification, TwoFactor** | `AdminController::AUTH_ONLY` (Dashboard: widgets filtrados, §4.2) |

### 4.2 Dashboard abierto con widgets filtrados (decisión del humano)
Cualquier usuario logueado entra a `/admin`; cada bloque se muestra **y se calcula** solo con su permiso.

| Bloque / dato | Permiso | Si no tiene permiso |
|---------------|---------|---------------------|
| `kpis`, `charts` (y `stats` que los alimenta) | `dashboard.view` | bloque oculto, sin consultas |
| `alerts` (`AlertService::all()`) | `logs.view` | `alerts = null`, bloque oculto |
| `feed` → salud (`Health::full()`) | `logs.view` | `health = null`, la tarjeta de salud no se renderiza |
| `feed` → novedades | — (siempre) | — |
| `recent` → auditoría | `audit.view` | `recentAudit = null` |
| `recent` → jobs | `cron.manage` | `recentJobs = null`; si ambos `null`, bloque oculto |
| `modules` | por ítem (`Rbac::can`, ya existe) | ítem "Estado / Salud" pasa a `logs.view` |
| botón "Estado" / "Buscar" (`dashboard.php:23-24`) | `logs.view` / `modules.manage` | botón oculto |

```
Dashboard::visibleBlocks(array $blocks, callable $can): array      // pura, testeable
    reglas = ['kpis'=>['dashboard.view'], 'charts'=>['dashboard.view'], 'alerts'=>['logs.view'],
              'feed'=>[], 'recent'=>['audit.view','cron.manage'], 'modules'=>[]]
    devolver los $blocks cuya regla es [] o alguno de sus permisos cumple $can(perm)
    // 'modules' además se oculta en el controlador si la galería filtrada queda vacía

DashboardController::index():
    $can = fn($p) => Rbac::can($p)
    $blocks = Dashboard::visibleBlocks(Dashboard::blocks(), $can)
    calcular stats/charts/kpis solo si 'kpis' o 'charts' ∈ $blocks; alerts solo si 'alerts' ∈ $blocks
    health = $can('logs.view') ? Health::full() : null
    recentAudit = $can('audit.view') ? recentAudit() : null ; recentJobs = $can('cron.manage') ? recentJobs() : null
    view(..., ['blocks'=>$blocks, 'canHealth'=>$can('logs.view'), 'canSearch'=>$can('modules.manage'), ...])
vista: si $blocks vacío → estado vacío "No tenés paneles disponibles. Usá el menú." + link /admin/profile
```

### 4.3 Sesión (observaciones)
```
Guard::user():                         // sin cache
    $id = $this->id(); si null → null
    $u = SELECT * FROM <table> WHERE id = ? LIMIT 1;  si null → null
    $col = cfg['active_column']; si $col y (int)($u[$col] ?? 0) !== 1 → null
    unset($u['password'], $u['totp_secret'])
    devolver $u
Guard::check() → SIN CAMBIOS (puro)
```

### 4.4 Drift en derivados (NSK-02)
```
core-manifest.php:
    --root=<dir> (opcional; default dirname(__DIR__, 2)) → para tests; el guard de L32 usa el root resuelto
    si --check y existe <root>/nsproject.json:   // MODO PROYECTO
        lock = core-lock.json[files]
        para cada (path, hash) en lock: falta → missing[]; hash distinto → modified[]
        si vacíos → "core-lock.json OK — modo proyecto: N archivos del core verificados (los archivos nuevos son del proyecto)." exit 0
        si no → "DRIFT (modo proyecto):" + hasta 20 rutas con [modificado]/[falta] + total → exit 1
    si --check en master → comportamiento actual + listar hasta 20 diferencias:
        added = keys(new) - keys(old); removed = keys(old) - keys(new); modified = comunes con hash distinto
```
- `system/config/modules_menu.php` pasa a **`app_paths`** (deja de viajar en el paquete de core; el
  master conserva su array vacío; `ProjectCreator` lo sigue copiando).
- `tests/app/` en `app_paths` + el runner lo recorre (tests propios del proyecto).
- `CoreUpdater::plan()`: si `old !== null && old === new && local !== new` → `skip` con razón
  `'editado por la app; el core no lo cambió'` (en vez de `conflict`). La CLI lista esos skip en el detalle.

### 4.5 Migraciones (NSK-03)
```
Migrator::rollbackAcross(int $steps, array $dirs): array
    orden = lista combinada de archivos de $dirs en el orden en que migrate() los aplica (dirs en orden, cada uno sort)
    pos[nombre] = índice en orden; ruta[nombre] = ruta completa
    filas = appliedRows() cuyo nombre ∈ pos
    ordenar filas por applied_at DESC y, en empate, pos[nombre] DESC   // revierte primero lo aplicado después
    revertir las primeras $steps (DOWN si hay) y borrar su fila
Migrator::rollback(int $steps = 1, ?string $dir = null):
    $dir !== null → rollbackAcross($steps, [$dir])     // compatibilidad: solo ese dir
    $dir === null → rollbackAcross($steps, [self::dir(), self::appDir()])   // GLOBAL
migrate.php rollback [N] [--core|--app]:
    flags = args que empiezan con '--'; N = primer arg numérico (default 1)
    --core → rollback(N, Migrator::dir()); --app → rollback(N, Migrator::appDir()); sin flag → rollback(N)
migrate.php fresh → rollback(count(aplicadas que existen en core+app), null) + migrate(null)
```

## 5. Decisiones y alternativas descartadas
| Decisión | Alternativas | Motivo |
|----------|--------------|--------|
| Default `modules.manage` configurable para controladores sin declarar | Deny estricto (informe) | El deny rompería todos los módulos generados de derivados existentes; `modules.manage` deja afuera a `viewer` y mantiene a `admin`/`editor`/`superadmin`. `null` restaura el legado. |
| `requiredPermission()` por controlador | Middleware/permiso en `routes.php`; nombre `permission()` | No toca el Router ni las rutas de los derivados; testeable por reflexión; el nombre evita choques con acciones de derivados (revisión #16). |
| Dashboard abierto + widgets filtrados | Exigir `dashboard.view` (403); redirigir al primer ítem permitido | Decisión del humano (2026-11-08): sin callejón sin salida al entrar y sin exponer salud/auditoría/jobs. |
| Health con `logs.view` | Permiso nuevo `health.view` | Un permiso nuevo no existiría en las matrices `rbac_roles` ya guardadas. |
| Search con `modules.manage` | Filtrar resultados por módulo | Solo busca en módulos generados, que ya se gobiernan con `modules.manage`. |
| `Guard::check()` puro + validación en `user()`/`AdminController`, sin cache | Validar en `check()`; cache por request | `GuardTest` usa `check()` sin DB; una cache estática sobrevive entre tests del mismo proceso (revisión #4). |
| `--check` modo proyecto = solo lo del lock | Opción A (lista de puntos de extensión en `app_paths`) | Coincide con la semántica real del updater y cubre módulos/tests sin mantener listas. |
| `modules_menu.php` a `app_paths` | Que `make-module` escriba otro archivo | Cambio mínimo; los lectores ya usan `is_file`. |
| Rollback global con desempate por orden de aplicación | Desempate por nombre DESC | Refleja el orden real de `migrate()` (core antes que app); evita revertir una del core antes que una de app aplicada en el mismo segundo (revisión #5). |
| `cron.allow_shell` diferido | Incluirlo apagado | Rompería tareas de sistema shell; con permisos arreglados el shell queda para `cron.manage`. |
| Fases secuenciales | F1∥F6∥F8 | Todas regeneran `core-lock.json` (revisión #3). |

## 6. Fases

### F1 — Sesión: usuario activo y sin `totp_secret` · estado: ✅ hecha
- **Objetivo:** que `Auth::user()` devuelva `null` para usuarios inactivos/borrados y nunca exponga `totp_secret`; que el admin cierre la sesión de esos usuarios.
- **Depende de:** —
- **Archivos:**
  - `system/app/Core/Guard.php` — `user()` según §4.3 (sin cache).
  - `system/app/Controllers/Admin/AdminController.php` — tras `Auth::check()`, si `Auth::user() === null`: `Auth::logout()`, `Session::flash('error', 'Tu sesión terminó o tu cuenta está desactivada.')`, redirect a `/admin/login`, `exit`.
  - `tests/feature/GuardActiveTest.php` — crear.
- **Pseudocódigo:** §4.3. Test:
  ```
  it('…', fn) con try/finally:
      si no hay DB → 'skip'
      id = INSERT admin_users (name, username, email, password=Auth::hash('x'), role='viewer', active=1, totp_secret='enc:test')
      Guard::for('admin')->login(id)
      u = Guard::for('admin')->user(); assert u !== null; assert !isset(u['password'], u['totp_secret'])
      UPDATE admin_users SET active = 0 WHERE id = id;  assert user() === null
      DELETE admin_users WHERE id = id;               assert user() === null
      finally: DELETE la fila si quedó; Guard::for('admin')->logout()
  ```
- **Criterios de aceptación:**
  - [ ] Usuario con `active = 0` → `user()` es `null`; usuario borrado → `null`.
  - [ ] Usuario activo → array sin `password` ni `totp_secret`.
  - [ ] `tests/unit/GuardTest.php` sigue verde sin cambios.
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php`
- **Notas para el ejecutor:** columnas de `admin_users` en `system/database/migrations/20260622_0001_create_admin_users.sql`, `20260625_010000_add_2fa_to_admin_users.sql` y `20260628_010000_add_username_usertype_to_users.sql`. Usar `it` + `finally` (no `itDb`): `login()` regenera la sesión y es más simple limpiar explícito.

### F2 — Permiso declarado por controlador · estado: ✅ hecha
- **Objetivo:** mecanismo `requiredPermission()` + default configurable, y los 21 controladores del core declarando su permiso (§4.1).
- **Depende de:** F1 (mismo constructor de `AdminController`)
- **Archivos:**
  - `system/app/Controllers/Admin/AdminController.php` — `AUTH_ONLY`, `requiredPermission()`, llamada al final del constructor.
  - `system/config/security.php` — clave `'admin_default_permission' => 'modules.manage'` con comentario (null = solo sesión, comportamiento previo).
  - Los 12 controladores con `requirePermission` en el constructor: **quitar** esa línea y agregar `protected function requiredPermission(): string { return '<permiso>'; }`. Si el constructor queda solo con `parent::__construct()`, borrarlo. `CoreUpdateController`: mantener el chequeo `ui_enabled` en su constructor (queda después del permiso: 403 antes que 404, aceptado) y quitar su `requirePermission`.
  - `LogViewerController` — quitar `requirePermission('logs.view')` de `index()` y declarar `requiredPermission()` = `logs.view`.
  - `CronController`, `AiController`, `HealthController`, `SearchController`, `DashboardController`, `ProfileController`, `NotificationController`, `TwoFactorController` — agregar `requiredPermission()` según la tabla §4.1.
- **Pseudocódigo:**
  ```
  // AdminController::__construct(), al final:
  $perm = $this->requiredPermission();
  if ($perm !== self::AUTH_ONLY) { $this->requirePermission($perm); }
  ```
- **Criterios de aceptación:**
  - [ ] `grep -L "function requiredPermission" system/app/Controllers/Admin/*.php` devuelve solo `AuthController.php` y `ForgotPasswordController.php`.
  - [ ] `grep -n "requirePermission(" system/app/Controllers/Admin/*.php` solo aparece en `AdminController.php` (definición y llamada).
  - [ ] Suite verde.
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php` + los dos grep.
- **Notas para el ejecutor:** no cambiar la firma ni la visibilidad de `requirePermission` (lo usan derivados). `requiredPermission()` es `protected`, no `static`.

### F3 — Dashboard con widgets filtrados, menú y buscador · estado: ✅ hecha
- **Objetivo:** aplicar §4.2: dashboard abierto, cada bloque y dato solo con su permiso; menú y topbar alineados.
- **Depende de:** F2
- **Archivos:**
  - `system/app/Services/Dashboard.php` — `public static function visibleBlocks(array $blocks, callable $can): array` (§4.2).
  - `system/app/Controllers/Admin/DashboardController.php` — `index()` según §4.2 (no calcular lo no permitido; pasar `null`); en `moduleGallery()` el ítem "Estado / Salud" (`/admin/health`) pasa de `'dashboard.view'` a `'logs.view'`; si la galería filtrada queda vacía, quitar `'modules'` de `$blocks`.
  - `system/app/Views/admin/dashboard.php` — botones "Estado" (L23) solo con `$canHealth` y "Buscar" (L24) solo con `$canSearch`; estado vacío si `$blocks === []`.
  - `system/app/Views/partials/dashboard/feed.php` — la tarjeta de salud (≈L28-40) dentro de `if ($health !== null)`.
  - `system/app/Views/partials/dashboard/recent.php` — tarjeta de auditoría dentro de `if ($recentAudit !== null)`; tarjeta de jobs dentro de `if ($recentJobs !== null)`.
  - `system/config/menu.php` — ítem `/admin/health`: permiso `'logs.view'`.
  - `system/app/Views/layouts/admin.php` — form de búsqueda (≈L193) dentro de `if ($can('modules.manage'))`.
  - `tests/unit/DashboardVisibilityTest.php` — crear.
- **Pseudocódigo:** §4.2. Test:
  ```
  can = fn($p) => in_array($p, ['dashboard.view'], true)             // perfil viewer
  assertEquals(['kpis','charts','feed','modules'], Dashboard::visibleBlocks(['alerts','kpis','charts','feed','recent','modules'], can))
  can2 = fn($p) => $p === 'audit.view'  → 'recent' visible
  html = View::partial('partials/dashboard/recent', ['recentAudit'=>null, 'recentJobs'=>[]]) → no contiene el título de la tarjeta de auditoría
  html = View::partial('partials/dashboard/feed', ['health'=>null, 'novedades'=>[...]]) → no contiene 'Disco libre'
  ```
- **Criterios de aceptación:**
  - [ ] Test nuevo verde.
  - [ ] Con un `viewer`: `/admin` 200 con KPIs y gráficos, sin alertas, sin salud, sin auditoría, sin jobs, sin botones Estado/Buscar, sin buscador en la topbar (verificado en F10).
  - [ ] Con `superadmin`: todo visible.
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php`
- **Notas para el ejecutor:** leer los parciales antes de envolver (las variables pueden no estar definidas si el preset no incluye el bloque: usar `($health ?? null) !== null`). El título exacto de cada tarjeta sale de los parciales; usarlo en las aserciones.

### F4 — Plantilla de `make-module` con permiso · estado: ✅ hecha
- **Objetivo:** que los controladores generados declaren su permiso explícitamente.
- **Depende de:** F2
- **Archivos:**
  - `system/console/make-module.php` — en la plantilla del controlador (≈L145), agregar `protected function requiredPermission(): string { return 'modules.manage'; }` con el comentario "cambialo por un permiso propio del módulo si querés separarlo" (respetar los `\$` del heredoc).
  - `tests/unit/ModuleScaffoldTest.php` — agregar: `assertTrue(str_contains((string) file_get_contents(BASE_PATH . '/console/make-module.php'), 'protected function requiredPermission(): string'))`.
- **Criterios de aceptación:** [ ] aserción nueva verde.
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php`
- **Notas para el ejecutor:** `make-module.php` escribe en un `BASE_PATH` fijo: **no** generar un módulo de prueba.

### F5 — Test de barrido de rutas admin · estado: ✅ hecha
- **Objetivo:** un test que falle si aparece una ruta de un controlador admin sin permiso efectivo o con un permiso que `viewer` no debería tener.
- **Depende de:** F2, F3, F4
- **Archivos:** `tests/unit/AdminPermissionsTest.php` — crear.
- **Pseudocódigo:**
  ```
  final class __DefaultPermProbe extends \App\Controllers\Admin\AdminController {}   // declarada a nivel archivo, NUNCA instanciada con new

  invocar(obj) = (new ReflectionMethod(obj, 'requiredPermission'))->invoke(obj)   // setAccessible no hace falta en PHP 8.1+
  router = new Router(); (require BASE_PATH.'/config/routes.php')(router)
  index  = Reflection(router)->getProperty('index')->getValue(router)    // ["GET /admin/cron" => pos]
  routes = Reflection(router)->getProperty('routes')->getValue(router)
  lock   = json_decode(core-lock.json)['files']
  AUTH_ONLY_OK = [Dashboard, Profile, Notification, TwoFactor]   // FQCN de App\Controllers\Admin
  viewerPerms  = (require BASE_PATH.'/config/permissions.php')['roles']['viewer']
  para cada key => pos en index:
      [method, path] = explode(' ', key, 2); h = routes[pos]['handler']
      clase = is_string(h) ? 'App\\Controllers\\' . explode('@', h)[0] : (is_array(h) ? h[0] : null)
      si clase === null o !is_subclass_of(clase, AdminController::class) → continuar   // login, forgot, reset, API, públicos
      obj = (new ReflectionClass(clase))->newInstanceWithoutConstructor()
      perm = invocar(obj)
      si perm === '' → assert clase ∈ AUTH_ONLY_OK  (mensaje: "path sin permiso")
      si no:
          assert perm ∉ viewerPerms (mensaje: "viewer accede a path")
          rel = ltrim(substr(str_replace('\\','/', getFileName()), strlen(str_replace('\\','/', PROJECT_PATH))), '/')
          si rel ∈ lock → assert perm ∈ array_keys(Rbac::catalog())   // solo controladores del core
  test 2: invocar(newInstanceWithoutConstructor(__DefaultPermProbe)) === 'modules.manage'
  test 3 (caso borde BR-001 #6): ReflectionProperty(Config::class,'cache')->setValue(null, ['security' => ['admin_default_permission' => null]])
          → invocar(probe) === '' ; finally Config::flush()
  ```
- **Criterios de aceptación:**
  - [ ] Los 3 tests verdes con el core de F2–F4.
  - [ ] Si `CronController::requiredPermission()` devolviera `''`, el test 1 falla (comprobar una vez a mano y revertir).
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php`
- **Notas para el ejecutor:** el nombre de la clase de prueba lleva prefijo `__` para que no choque con nada; el archivo de test no se considera controlador (no está en rutas). `routes.php` carga también `config/routes/*.php` y `routes.app.php` si existen: en derivados el barrido cubre sus rutas.

### F6 — `--check` en modo proyecto · estado: ✅ hecha
- **Objetivo:** que en derivados `--check` valide solo los archivos del lock y que siempre liste qué difiere.
- **Depende de:** —
- **Archivos:**
  - `system/console/core-manifest.php` — opción `--root=<dir>`; modo proyecto y listado de diferencias del modo master (§4.4); el guard de L32 usa el root resuelto.
  - `tests/unit/CoreManifestCheckTest.php` — crear.
- **Pseudocódigo:** §4.4. Test (árbol temporal en `sys_get_temp_dir()`, ejecutando el script con `exec(PHP_BINARY …)` como `CorePackageTest`):
  ```
  tmp/: nsproject.json {"role":"project"}; core-manifest.json (copia del real); core-lock.json con 2 archivos (a.txt, system/x.php) y sus sha256
        + system/config/routes.app.php y tests/app/MiTest.php sin lockear
  --check --root=tmp → exit 0
  modificar system/x.php → exit 1, salida contiene 'system/x.php' y '[modificado]'
  borrar a.txt → exit 1, salida contiene '[falta]'
  limpiar tmp en finally
  ```
- **Criterios de aceptación:**
  - [ ] Los tres casos del test.
  - [ ] En el master, `php system/console/core-manifest.php --check` sigue en 0 y `CoreBoundaryTest` verde.
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php`

### F7 — `modules_menu.php` del proyecto, `tests/app/` y updater sin ruido · estado: ✅ hecha
- **Objetivo:** que generar módulos no toque archivos del core, que los tests propios tengan su carpeta y que el updater no marque conflicto donde el core no cambió.
- **Depende de:** F6
- **Archivos:**
  - `core-manifest.json` — `app_paths` += `"system/config/modules_menu.php"`, `"tests/app/"`; nota en `notes` explicando ambos.
  - `tests/run.php` — recorrer también `tests/app/*.php` (si existe) después de `unit` y `feature`.
  - `tests/README.md` — sección "Tests del proyecto: `tests/app/`".
  - `system/app/Services/CoreUpdater.php` — en `plan()`, antes de clasificar `conflict`: `si ($old !== null && $old === $newHash) → SKIP 'editado por la app; el core no lo cambió'`.
  - `system/console/core-update.php` — el detalle del plan lista también los `skip` con esa razón (etiqueta "editado por la app (el core no lo cambió): se conserva").
  - `tests/unit/CoreUpdaterTest.php` — caso nuevo: `old == new`, local editado → `skip` con esa razón y `apply` no deja `.new`; el caso `b.txt` existente (old≠new) sigue dando `conflict`.
  - `tests/unit/CoreBoundaryTest.php` — aserción: `core-lock.json` no contiene `system/config/modules_menu.php` ni ninguna ruta bajo `tests/app/`.
  - `docs/CORE-UPDATE.md` — el skip nuevo y que después `--check` en modo proyecto lo reporta como `[modificado]` (es un archivo del core editado).
- **Criterios de aceptación:**
  - [ ] Lock sin `modules_menu.php` (el master conserva el archivo).
  - [ ] `php landing/build-core-package.php --out=<tmp>` no trae `system/config/modules_menu.php`.
  - [ ] Caso nuevo de `CoreUpdaterTest` verde; `CoreUpdateTest` verde.
  - [ ] `grep -rn modules_menu system --include=*.php` (excluyendo `system/storage/`) solo muestra lectores con `is_file()`.
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php`
- **Notas para el ejecutor:** en un derivado con `modules_menu.php` en su lock viejo, el update da `delete` si no lo editó (inocuo: lectores con `is_file()`, `make-module` lo recrea) o `delete_modified` (se conserva) si lo editó. Dejarlo razonado en el walkthrough.

### F8 — Rollback y fresh sobre core + app · estado: ✅ hecha
- **Objetivo:** que `rollback` sin directorio revierta en orden global y que `fresh` reaplique todo.
- **Depende de:** —
- **Archivos:**
  - `system/app/Services/Migrator.php` — `rollbackAcross()` + `rollback()` delegando (§4.5); docblocks.
  - `system/database/migrate.php` — parseo de `rollback [N] [--core|--app]` y `fresh` (§4.5); ayuda en el encabezado.
  - `tests/feature/MigratorTest.php` — casos nuevos con **dos dirs temporales** en `sys_get_temp_dir()` (core-tmp y app-tmp, cada uno con una migración con `-- @DOWN` y nombre único `29990101_…`), `it` + `finally`:
    ```
    migrate(coreTmp); migrate(appTmp)
    UPDATE schema_migrations SET applied_at='2000-01-01 00:00:00' WHERE migration IN (las dos)   // empate forzado
    rollbackAcross(1, [coreTmp, appTmp]) → revierte la de appTmp (aplicada después)
    rollbackAcross(1, [coreTmp, appTmp]) → revierte la de coreTmp
    rollback(1, coreTmp) mantiene el comportamiento de un solo dir
    finally: DROP de las tablas de prueba si quedaron, DELETE de sus filas, borrar dirs
    ```
- **Criterios de aceptación:**
  - [ ] Casos nuevos verdes; los casos existentes de `MigratorTest` sin cambios y verdes.
  - [ ] `php system/database/migrate.php status` lista core + app como antes.
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php`
- **Notas para el ejecutor:** **no** escribir archivos en `system/database/migrations/` ni correr `fresh` contra la base del master (borra datos). `fresh` se valida por el test de `rollbackAcross` + lectura del código.

### F9 — Documentación · estado: ✅ hecha
- **Objetivo:** dejar documentados los contratos nuevos.
- **Depende de:** F1–F8
- **Archivos:**
  - `agentic/knowledge/core-manual.md` — `AdminController` (`requiredPermission()`, `AUTH_ONLY`, `admin_default_permission`), dashboard con widgets filtrados (`Dashboard::visibleBlocks`), `Guard::user()` (activo, sin `totp_secret`), `Migrator::rollback` global / `rollbackAcross`, `core-manifest --check` modo proyecto, `tests/app/`.
  - `agentic/adapters/php-mvc/conventions.md` — "todo controlador admin declara `requiredPermission()`" y "prohibido construir controladores admin en tests".
  - `agentic/skills/module-generator/SKILL.md` — el controlador generado declara `modules.manage`; cómo cambiarlo.
  - (`docs/CORE-UPDATE.md` ya se tocó en F7.)
- **Criterios de aceptación:** [ ] cada contrato nuevo aparece en el manual del core.
- **Verificación:** `php system/console/core-manifest.php && php tests/run.php` (incluye `AgenticStructureTest`).

### F10 — Release 1.20.3, E2E y llevarlo a nsWMS · estado: ✅ hecha
- **Objetivo:** publicar, verificar por HTTP con un `viewer` real y actualizar nsWMS.
- **Depende de:** F1–F9
- **Archivos:** `VERSION`, `core-manifest.json` (`core_version`), `docs/CHANGELOG.md`, `README.md`, lock, paquetes.
- **Pasos:**
  1. `/release 1.20.3` (suite verde, lock sin drift, `landing/build-core-package.php --regen`, `landing/build-download.php`). Notas de actualización del CHANGELOG: default `modules.manage` y cómo volver al legado (`config/overrides/security.php` con `'admin_default_permission' => null`); dashboard con widgets por permiso; Health y su tarjeta ahora `logs.view`; `modules_menu.php` deja de ser core; `rollback` sin flag es global; `tests/app/`.
  2. E2E HTTP en una instalación de prueba (p. ej. `C:\xampp\htdocs\nsupd-e2e`, actualizada con el paquete 1.20.3) con un `viewer` y un `superadmin`:
     403 en `GET /admin/cron/create`, `POST /admin/cron`, `POST /admin/ai`, `GET /admin/health`, `GET /admin/search`;
     200 en `/admin` (KPIs y gráficos sí; alertas, salud, auditoría, jobs, botones Estado/Buscar y buscador de la topbar no) y en `/admin/profile`;
     desactivar al `viewer` con su sesión abierta → el siguiente request redirige al login con el aviso.
  3. nsWMS: `php system/console/core-update.php --source=<zip 1.20.3>` (dry-run → apply), suite de nsWMS, `core-manifest.php --check` en modo proyecto con sus extensiones → 0.
  4. Commit + tag + push del master **con confirmación del humano**. Commit en nsWMS **con confirmación**.
  5. Respuesta a nsWMS (artifact o nsBus si ya está) con el veredicto y la versión.
- **Criterios de aceptación:**
  - [ ] Checklist del paso 2 completo, con las salidas guardadas en el walkthrough.
  - [ ] nsWMS en 1.20.3, suite verde salvo sus tests propios ya conocidos, `--check` 0.
- **Verificación:** salidas del E2E en el walkthrough.
- **Notas para el ejecutor:** para el `viewer` del E2E: `php system/database/seed-demo.php` en la instalación de prueba (crea `viewer` / `demo1234`); no borrar datos al final sin aprobación del humano.

## 7. Plan de pruebas
- Nuevos: `GuardActiveTest` (feature, it + finally), `DashboardVisibilityTest` (unit), `AdminPermissionsTest` (unit, reflexión, 3 tests), `CoreManifestCheckTest` (unit, árbol temporal), casos nuevos en `CoreUpdaterTest`, `CoreBoundaryTest`, `MigratorTest` y `ModuleScaffoldTest`.
- Existentes que no deben cambiar de resultado: `GuardTest`, `RbacTest`, `CoreUpdateTest`, `RouterOverrideTest`, el resto de `MigratorTest`.
- Casos borde de BR-001 §10: 1, 2, 4, 5, 7 en F3/F5/F10; 6 en F5 test 3; 8–10 en F6/F7; 11–12 en F8.
- Manual: E2E HTTP de F10.

## 8. Migraciones y rollback
- Sin migraciones de esquema.
- Rollback del release: `core-update.php --rollback=<backup>` en derivados; en el master, revert del commit.
- Si un derivado pierde acceso a sus módulos: `config/overrides/security.php` con `'admin_default_permission' => null` mientras declara `requiredPermission()` en sus controladores.

## 9. Impacto y aislamiento
- Toca el núcleo del admin (todos los controladores y el dashboard): **no es aislable** por naturaleza (fix transversal de seguridad). El default configurable es la válvula de escape.
- Cambia comportamiento visible para roles limitados (más 403 y menos widgets, por diseño).
- `modules_menu.php` deja de viajar en el paquete de core; `rollback` sin flag pasa a global.

## 10. Riesgos técnicos
| Riesgo | Probabilidad | Mitigación |
|--------|--------------|------------|
| Rol custom de un derivado pierde acceso a módulos generados | media | Notas de actualización + `admin_default_permission` |
| Un controlador de app que llama `requirePermission('x')` exige además `modules.manage` | media | Mismo mecanismo; recomendar declarar `requiredPermission()` |
| `Auth::user()` agrega una consulta en cada request admin | baja | Ya la hacían la mayoría de las vistas; sin cache para no romper tests |
| Un test que construya un controlador admin corta la suite con falso verde | media | Regla 2 del encabezado + `newInstanceWithoutConstructor` |
| El barrido no ve rutas de módulos en el master | baja | En el master no hay módulos; en derivados `routes.php` carga los suyos |
| Variables de dashboard indefinidas según el preset | media | Guards con `?? null` en los parciales (F3) |

## 11. Fuera de alcance
NSK-04 (conector nubixstore) y NSK-05 (transacciones) → próximo plan. `cron.allow_shell`. Mover la salida de `make-module` a `app/`. Observaciones menores de documentación (`maybeDecrypt`, layout faltante, multi-sentencia).

## 12. Recomendación de ejecución
- Modelo estándar, esfuerzo medio; F2, F3 y F6 con atención.
- Apto para loop: sí (`loop_ready: true`), una fase por iteración, cortar ante cualquier `⛔`.
- **Fases secuenciales** (todas regeneran `core-lock.json`). Orden: F1 → F2 → F3 → F4 → F5 → F6 → F7 → F8 → F9 → F10.
- F10 requiere al humano (push y commit en nsWMS).

## 13. Revisión del plan
| Fecha | Revisor (agente/modelo) | Hallazgos | Resolución |
|-------|-------------------------|-----------|------------|
| 2026-11-08 | plan-architect (claude-opus-5-5), autorrevisión | (1) `Guard::check()` no puede consultar DB por `GuardTest`; (2) `requirePermission` hace `exit` → test por reflexión; (3) deny estricto rompe derivados; (4) `modules_menu.php` es core y lo escribe `make-module`; (5) `fresh` no se puede probar contra la base del master | Incorporados |
| 2026-11-08 | Revisor independiente (agente Plan, sin las conclusiones del autor; contrastó contra el código del master y de nsWMS) — veredicto **aprobable con cambios** | 16 hallazgos: **bloqueante** #1 test del default construía el controlador (`exit`); **importantes** #2 `Router::$routes` sin path (usar `$index`), #3 lock sin regenerar por fase y paralelismo falso, #4 cache de `Guard::user()` rompe tests, #5 test de F8 escribía en `system/` y desempate por nombre, #6 el dashboard expone `Health::full()` y botones fijos, #7 dashboard con `dashboard.view` deja sin salida a roles sin ese permiso, #8 aserción de catálogo rompe derivados; **menores** #9 afirmaciones de §3 (visibilidad, LogViewer, líneas, grep, empaquetador, grupos), #10 test de F4 indefinido, #11 caso borde 6 abierto, #12 parseo CLI de rollback, #13 skip invisible en la CLI, #14 CS7 sin aserción, #15 F3 sin test automático, #16 nombre `permission()` choca | Todos incorporados (encabezado de reglas, §3, §4.1–4.5, §5, F1–F8, §10, §12). #7 resuelto por decisión del humano: dashboard abierto con widgets filtrados (§4.2). El revisor simuló `--check` modo proyecto sobre nsWMS: 513 archivos, 0 drift. |

## 14. Registro de ejecución
| Fase | Fecha | Agente | Modelo | Resultado | Walkthrough |
|------|-------|--------|--------|-----------|-------------|
| F1 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ Guard::user() valida active_column y oculta password/totp_secret; AdminController cierra la sesión si user() es null; GuardActiveTest; suite 453/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F2 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ AdminController::requiredPermission() + AUTH_ONLY + security.admin_default_permission; 21 controladores declaran su permiso (12 constructores + LogViewer migrados, 8 nuevos); greps de aceptación OK; suite 453/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F3 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ Dashboard::visibleBlocks + BLOCK_PERMISSIONS; DashboardController calcula solo lo permitido (health/alerts/audit/jobs en null); parciales recent/feed con guards; botones y buscador de topbar por permiso; Estado → logs.view en menú y galería; DashboardVisibilityTest (6); smoke HTTP superadmin sin errores; suite 459/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F4 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ plantilla de make-module declara requiredPermission() = modules.manage (con nota para separarlo); aserción en ModuleScaffoldTest; suite 460/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F5 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ AdminPermissionsTest: barrido por reflexión de Router::$index/$routes (+50 rutas), default modules.manage, admin_default_permission=null; verificado a mano que un controlador AUTH_ONLY rompe el test; suite 463/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F6 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ core-manifest --check: modo proyecto (solo archivos del lock, [falta]/[modificado]) + --root + listado de diferencias en el master; CoreManifestCheckTest (2); nsWMS real: 513 verificados, sin drift; suite 465/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F7 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ modules_menu.php y tests/app/ en app_paths (fuera del lock y del zip de core); runner recorre tests/app/; CoreUpdater: skip "editado por la app; el core no lo cambió" (CLI y panel lo listan); tests nuevos en CoreUpdaterTest y CoreBoundaryTest; CORE-UPDATE.md; suite 467/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F8 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ Migrator::rollbackAcross (applied_at DESC + orden de aplicación) y rollback() global sin dir; migrate.php rollback [N] [--core|--app] con parseo explícito y fresh core+app; test con dirs temporales y empate forzado; suite 468/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F10 | 2026-11-08 | release-manager | claude-opus-5-5 | ✅ 1.20.3 publicado: commit + tag v1.20.3 + push (confirmado por el humano); paquetes core (517) + completo (647) + latest.json; suite 468/0/1; E2E HTTP viewer/editor OK; nsWMS en 1.20.3 (568/0/5, sin drift) — el humano decidió no commitear en el repo de nsWMS (lo hace su sesión); respuesta a nsWMS: https://claude.ai/artifact/XsaSa4p7amR4QsW2qSeEaL | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |
| F9 | 2026-11-08 | plan-executor | claude-opus-5-5 | ✅ core-manual (AdminController nuevo, Auth::user activo/sin totp_secret, Migrator rollback global/rollbackAcross, Dashboard visibleBlocks, core-manifest --check modo proyecto), conventions.md (permisos + tests/app), skill module-generator; suite 468/0/1 | `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` |

## 15. Desvíos
- **F10 · E2E** sobre el master (ya corre el código nuevo) en vez de `nsupd-e2e`; la actualización real se probó en nsWMS. Para el E2E se cargaron los datos demo en la base del master (`seed-demo.php`; se quitan con `--undo`).
- **F10 · test de F7 en derivados:** `CoreBoundaryTest` exigía `modules_menu.php` también en proyectos (donde el update lo borra a propósito) → falló en nsWMS. Se limitó al master, se regeneró el paquete 1.20.3 (todavía sin publicar) y se reaplicó en nsWMS (1 archivo). Sin impacto en el diseño.

## 16. Cierre
- [x] Todas las fases ✅.
- [x] CS1–CS10 de BR-001 verificados (CS1–CS9 por tests + E2E HTTP; CS10 = nsWMS actualizado y en verde).
- [x] Suite verde y lock sin drift.
- [x] BR-001 queda `🧭 Planificado`: NSK-04 y NSK-05 siguen abiertos para el próximo plan.
- [x] DoD de `sprints.md` (walkthrough `logs/walkthrough/2026-11-08_1525_plan-executor_PL-002.md` + logs).
