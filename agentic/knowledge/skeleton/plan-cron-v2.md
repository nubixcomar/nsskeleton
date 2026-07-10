# Plan — Cron v2 (cron master + historial + tareas URL)

**Origen:** módulo de scheduler de **nsCentral** (`C:\xampp\htdocs\impulso`, persistencia JSON) portado a nsSkeleton (PHP MVC + **MySQL**).
**Core base:** v1.18.0
**Objetivo:** llevar el cron de Skeleton al nivel del de nsCentral: disparador **cron master** por web, **historial** completo con filtros y borrado selectivo, y tareas que **pegan a URLs**.

> **No es copiar-pegar:** nsCentral guarda todo en JSON; Skeleton usa MySQL y **ya tiene** tablas y runner. Se **adaptan los conceptos** reusando lo existente.

---

## Comparación (qué hay vs. qué falta)

| Feature | Skeleton hoy | nsCentral | Acción |
|---|---|---|---|
| Definición de tareas | tabla `cron_tasks` (cron expr, prioridad, timeout) | catálogo PHP + estado JSON | ✅ ya está (mejor: editable desde panel) |
| Programación | expresión cron 5 campos + `ScheduleBuilder` | tipos daily/weekly/… | ✅ ya está |
| Runner | `CronRunner::runDue` (lock + throttle `min_interval`) | `SchedulerRunner::tick` | ✅ ya está |
| Prioridad / timeout | ✅ | ✅ | ✅ ya está |
| Historial (tabla) | `cron_runs` (inicio/fin/estado/exit/salida) | `runs-YYYYMM.json` | 🟡 falta trigger + duración + vista |
| **Cron master (web)** | ❌ (solo cron del SO) | ✅ `/cron/run?key=` + secreto + IP + toggle | ➕ **portar** |
| **Tareas URL (HTTP)** | ❌ (solo shell/`job:`) | parcial (handlers) | ➕ **agregar** (con `Core\Http`) |
| **Vista Historial + filtros** | ❌ (solo últimas por tarea) | ✅ pestaña con filtros | ➕ **portar** |
| **Limpiar OK / Vaciar** | ❌ | ✅ | ➕ **portar** (en MySQL es trivial) |
| Tipo de disparo (manual/auto/master) | ❌ | ✅ | ➕ **agregar** |

---

## v1.19 — Cron v2

### Fase 1 — Cron master (disparador web protegido)
**Meta:** poder disparar el cron desde EasyCron o un `curl` externo (no depender del cron del SO — clave en hostings compartidos).

- **`App\Services\CronMaster`** (config en tabla `settings`, grupo `cron`): `enabled()`, `secret()`, `rotateSecret()` (32 bytes → 64 hex), `verifyKey()` (constant-time `hash_equals`), `ipAllowed($ip)` (allowlist vacía = todas), `url()`/`curlCommand()`.
- **Endpoint público** `GET|POST /cron/run` → `App\Controllers\CronRunController` (SIN guard admin). Orden de validación: **enabled→503**, **secreto (`?key=` o header `X-Cron-Key`)→403**, **IP→403**, luego `CronRunner::runDue($now, 'master')` → responde JSON `{ok, ran, errors}`. Los accesos denegados se loguean con `Core\ErrorLog`.
- **UI pestaña "Cron master"** (`/admin/cron/master`): muestra URL protegida + comando curl (`*/5 * * * * curl -fsS "..."`), nota del header `X-Cron-Key`, toggle habilitado, intervalo mínimo (anti-martilleo), allowlist de IP, botón generar/rotar secreto. Métodos `master()`/`saveMaster()`/`rotateSecret()` en `CronController`.
- **Subnav** (Tareas / Cron master / Historial) en las vistas de cron.
- Tests: verifyKey, ipAllowed, endpoint 503/403/200 (con secreto seteado en settings).

### Fase 2 — Tareas que pegan a URLs (HTTP)
**Meta:** una tarea puede ser un ping/POST a una URL, no solo shell/`job:`.

- **`CronRunner::runTask`**: detecta prefijo **`url:`** en el comando (igual que el actual `job:`). `url:https://…` = GET; `url:POST https://… {json}` = POST. Usa **`App\Services\Http`** (timeouts + **reintentos** ya implementados). Éxito = respuesta 2xx.
- Ayuda + validación en el form de tarea (aceptar `url:`); ejemplos en el placeholder.
- Tests con **transporte inyectado** de `Http` (sin red): 2xx→success, 5xx→failed, timeout→failed.

### Fase 3 — Historial completo + borrado selectivo
**Meta:** pestaña de historial con filtros y los botones que pediste.

- **Migración**: `ALTER TABLE cron_runs ADD trigger VARCHAR(20) NOT NULL DEFAULT 'cron', ADD duration_ms INT UNSIGNED NULL` (+ índice por `started_at`). `CronRunner` graba `trigger` (manual/cron/master) y `duration_ms`.
- **Vista `/admin/cron/history`**: tabla con fecha, tarea, **disparo**, estado, **duración (ms)**, mensaje. Filtros: tarea (select), estado (todos/OK/error), búsqueda en mensaje. Paginación (reusa `Paginator`).
- **Borrado**:
  - **"Limpiar OK"** → `DELETE FROM cron_runs WHERE status = 'success'` (te queda **solo el log de errores**).
  - **"Vaciar registro"** → `DELETE FROM cron_runs` (todo). Ambos con confirmación.
- Métodos `history()`, `clearOk()`, `clearAll()` en `CronController` + `CronRun` model/consultas. Tests con `itDb()`.

---

## Notas de diseño
- **Reusar, no reescribir**: se mantienen `cron_tasks`/`cron_runs`, `CronRunner`, `CronExpression`, `ScheduleBuilder`. Se **suma**, no se rompe. El cron del SO (`system/cron/run.php`) sigue funcionando (trigger `'cron'`); el cron master es una vía **alternativa** (trigger `'master'`).
- **Seguridad**: secreto en `settings` (no en el repo), `hash_equals`, allowlist de IP, toggle. Endpoint devuelve 503/403 sin filtrar info. Denegaciones a `ErrorLog` (v1.17 #5).
- **Retrocompat**: tareas shell/`job:` existentes siguen igual; `url:` es aditivo. La migración de `cron_runs` es aditiva (columnas con default).
- Cada fase agrega tests versionados; se regenera `core-lock.json`; walkthrough + log por fase (metodología de siempre).

---

*Plan generado 2026-07-09 a partir del mapa técnico del scheduler de nsCentral, validado contra el cron actual de nsSkeleton v1.18.0.*
