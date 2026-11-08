# Walkthrough — Triage de hallazgos de nsWMS sobre el core 1.20.2

**Fecha y hora:** 2026-11-08 09:00 | **Agente:** security-audit | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** core 1.20.2 | **Trazas:** — (propuesta: RM nuevo "Hardening 1.20.3")

---

## Resumen ejecutivo
El proyecto nsWMS reportó 5 hallazgos + 5 observaciones sobre el core
(artifact `https://claude.ai/artifact/2LkSg7ob2wpsJctW8P3DSW`). Se verificaron todos contra el
código del master **sin modificar nada**. Los 5 hallazgos son reales; NSK-01 es más amplio de lo
reportado. De las observaciones, 1 vale la pena (ligada a NSK-01) y 4 son menores/documentación.

## Veredicto por hallazgo
| ID | Veredicto | Severidad real | ¿Arreglar? |
|----|-----------|----------------|------------|
| NSK-01 | ✅ confirmado y **ampliado** | Crítica | Sí, ya (1.20.3) |
| NSK-02 | ✅ confirmado (riesgo de lock ya mitigado) | Alta (DX) | Sí (1.20.3) |
| NSK-03 | ✅ confirmado | Alta (destructivo) | Sí (1.20.3) |
| NSK-04 | ✅ confirmado (4 puntos) | Media | Sí, siguiente |
| NSK-05 | ✅ confirmado | Media | Sí, siguiente |

### NSK-01 — permisos en controladores admin
- Sin `requirePermission`: `CronController`, `AiController`, `HealthController`, `SearchController`.
  No hay control por ruta ni middleware (`Router`/`App` no chequean permisos).
- `CronRunner.php:108` → `exec()` del comando: cualquier admin logueado (incluido `viewer`) crea
  y ejecuta shell. Confirmado.
- **Ampliación 1:** `AiController::saveSettings` también está abierto → un `viewer` puede
  **cambiar proveedor y API key** de IA, no solo usarla.
- **Ampliación 2:** los controladores que genera `make-module.php` (plantilla L145) tampoco piden
  permiso; el menú los oculta por `modules.manage` pero las rutas quedan accesibles. Afecta a
  todo módulo de negocio de los derivados (nsWMS los va a generar así).
- `DashboardController`: no exige `dashboard.view` y `recentAudit()` muestra `audit_log` sin
  `audit.view`. `GlobalSearch` no filtra por permiso.
- OK sin permiso (por usuario): `Profile`, `Notification`, `TwoFactor`, `Auth`, `ForgotPassword`.
- Fix recomendado: permiso declarado por controlador con **deny por defecto** en
  `AdminController` + permisos puntuales (cron.manage, ai.manage, dashboard.view, audit.view para
  el widget, filtro en búsqueda) + plantilla de `make-module` con permiso + test que recorra todas
  las rutas `/admin/*` con un `viewer`. El flag `cron.allow_shell` es buena idea (opcional).

### NSK-02 — `--check` y puntos de extensión
- `core-manifest.php --check` regenera el lock completo y lo compara: todo archivo nuevo bajo
  `core_paths` (`system/`, `tests/`) que no esté en `app_paths` es drift → `CoreBoundaryTest`
  en rojo permanente en derivados (routes.app.php, overrides, migrations/app, módulos generados,
  tests propios).
- Riesgo "regenerar el lock mete archivos de app como core": **ya mitigado** —
  `core-manifest.php` sin `--check` se niega si existe `nsproject.json`, y el actualizador opera
  solo sobre lo del lock.
- Fix recomendado: opción **B** del informe (en derivados, `--check` valida solo los archivos del
  lock: faltantes/modificados = drift; nuevos = de la app). Coincide con la semántica real del
  actualizador. Agregar además una carpeta de tests de app que el runner descubra (`tests/app/`).

### NSK-03 — rollback/fresh y migraciones de app
- `migrate.php rollback` → `Migrator::rollback($n)` sin dir → solo core. `fresh` cuenta core+app
  y revierte solo core. Confirmado.
- Fix: rollback por orden global (`schema_migrations` sobre core+app) con `--core/--app`
  opcional; `fresh` revierte todo y reaplica todo. Chico.

### NSK-04 — NubixstoreConnector
- Token solo en memoria (login por request; el login es determinístico y no revoca, así que el
  costo es 1 llamada extra por request). `put()`/`delete()` sin `ensureToken()`. `ok` = solo
  HTTP 2xx: la referencia viva (2.6.0) exige `status === 0` y la API devuelve 903/905 con HTTP
  200. Docblock de `errorFrom` engañoso. Clase `final`. Todo confirmado.
- Descartado (sospecha propia): `expires_in` **sí** es timestamp UNIX según la referencia; el
  cálculo de expiración está bien.
- Fix: `isSuccess()` por driver (`status === 0`), `ensureToken()` en todos los verbos, re-login
  ante 903, TokenStore opcional (cache por credencial), quitar `final`.

### NSK-05 — transacciones
- `transaction()` llama a `$pdo->beginTransaction()` sin chequear → lanza dentro de `itDb` o de
  otra transacción; `commit()`/`rollback()` internos cierran la externa. Confirmado.
- Fix: contador de profundidad + savepoints; `transaction()` reentrante.

## Observaciones menores
| Observación | Veredicto | Acción |
|-------------|-----------|--------|
| `Guard` no revalida `active` | ✅ real; **sube de prioridad** por NSK-01: `check()` solo mira la sesión, un usuario desactivado o borrado sigue pasando `AdminController` y llega a los controladores sin permiso | Arreglar con NSK-01 |
| `Auth::user()` devuelve `totp_secret` | ✅ real; el secreto se guarda cifrado → riesgo bajo | Quitarlo (1 línea), con NSK-01 |
| `Crypto::maybeDecrypt()` devuelve `''` si no puede descifrar | ✅ real, comportamiento deliberado | Documentar; no urge |
| `View::render()` sin layout no avisa | ✅ real | Warning en debug; baja |
| `Migrator` multi-sentencia en un `exec` | plausible (comportamiento conocido de PDO/MySQL) | Documentar "una sentencia por archivo" o iterar sentencias; baja |

## Archivos tocados
Ninguno de código (solo este walkthrough y la línea de log).

## Pendientes / follow-ups
- Release **1.20.3** (hardening): NSK-01 (+ Guard `active`, `totp_secret`), NSK-02, NSK-03.
  Por tamaño (M+), conviene un RM + PL antes de codear (metodología RM → BR → PL).
- Release siguiente: NSK-04, NSK-05, observaciones menores.
- Responderle a nsWMS con este veredicto (cuando esté en nsBus, por ahí).
