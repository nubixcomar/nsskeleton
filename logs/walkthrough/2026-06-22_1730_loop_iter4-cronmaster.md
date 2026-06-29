# Walkthrough — Iteración 4: programador de tareas / cron (cronmaster)

**Fecha y hora:** 2026-06-22 17:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S1 | **Versión:** 0.1.0

---

## Resumen ejecutivo
Se implementó el cronmaster: gestión de tareas programadas con expresiones cron de 5
campos, despachador CLI invocado por minuto desde el SO, ejecución con captura de
salida/exit code, historial de corridas y panel CRUD. El motor de evaluación cron fue
verificado con 11 pruebas unitarias.

## Cambios realizados
- **Migración**: `20260622_0002_create_cron_tasks.sql` (`cron_tasks` + `cron_runs`).
- **Motor cron**: `App\Services\CronExpression` (isValid, isDue, nextRunAfter; soporta
  `*`, `*/n`, `a-b`, `a-b/n`, listas; semántica estándar dom/dow).
- **Ejecución**: `App\Services\CronRunner` (runDue / runTask con guard anti-doble-corrida).
- **Despachador CLI**: `system/cron/run.php` + `system/cron/README.md` (cómo cablearlo
  a cron de Linux y al Programador de tareas de Windows/XAMPP).
- **Modelo**: `App\Models\CronTask` (+ recentRuns).
- **Controlador**: `Admin\CronController` (index, create, store, edit, update, toggle,
  runNow, destroy).
- **Vistas**: `admin/cron/index`, `admin/cron/form` (con historial y última salida).
- **Rutas + menú**: registradas; ítem "Tareas / Cron" activado en el sidebar.

## Verificación
- `php -l` sobre todo `system/` → sin errores.
- **CronExpression: 11/11 tests** (validación, isDue para `0 3 * * *`, `*/5`, día-mes,
  listas; nextRunAfter para `0 0 1 * *` → 2026-07-01 y `*/15` → 10:15).
- `GET /admin/cron` y `/admin/cron/create` sin sesión → 302 a login (guard OK).
- ⚠️ La corrida real (`run.php`, runNow) y el CRUD persistente requieren MySQL arriba;
  pendiente de verificar con la base levantada.

## Decisiones de diseño
- Patrón estándar: una sola entrada de cron del SO (cada minuto) → scheduler interno
  que decide qué está vencido. Evita una entrada por tarea.
- Comando ejecutado vía shell con captura `2>&1`; salida truncada a 5000 chars.
- `next_run_at` se recalcula al guardar y tras cada corrida.

## Pendientes / follow-ups
- **Iteración 5**: configuración y envío de emails (SMTP).
- Verificar corrida real del cron con MySQL + un job de prueba.

## Referencias
- `system/app/Services/Cron*`, `system/cron/`, `system/app/Controllers/Admin/CronController.php`.
