# Walkthrough — Fase I2: Cronmaster v2

**Fecha y hora:** 2026-06-27 11:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S10 (v1.9) | **Versión:** 1.9 · **Idea de:** nsCentral (impulso)

---

## Resumen ejecutivo
El cron ahora es usable por no-técnicos: un constructor de horario arma la expresión cron
desde presets, se muestra en lenguaje natural, y cada tarea tiene prioridad y timeout.

## Cambios realizados
- **`App\Services\ScheduleBuilder`**: `fromPreset()` (cada N min / horaria / diaria /
  semanal / mensual → cron de 5 campos, con clamp) y `describe()` (cron → texto humano).
- **Migración**: `priority` + `timeout` en `cron_tasks`.
- **`CronRunner` v2**: **throttle** (omite tick si pasó < `cron.min_interval` seg desde el
  último), **orden por prioridad** (DESC), **timeout por tarea** (`set_time_limit`).
- **`CronController`/`CronTask`**: leen y persisten `priority`/`timeout`.
- **Form**: constructor de horario (Alpine `cronBuilder`: parse/build/describe en vivo) +
  campos prioridad/timeout. La expresión cron queda editable para avanzados.
- **Índice**: muestra el `describe()` humano + la expresión cruda + badge de prioridad.

## Verificación
- `php -l` OK.
- **Suite**: **222/222 PASS** (+9 unit `ScheduleBuilder`: fromPreset de los 5 tipos + clamp,
  describe de patrones comunes, fallback, roundtrip).
- **E2E (MySQL 3307)**: creé "Demo diaria" con `30 3 * * *`, prioridad 5, timeout 30 → el form
  tiene el constructor; el índice muestra "Todos los días a las 03:30" + "prio 5"; se guardaron
  priority=5/timeout=30. Tarea demo limpiada.

## Pendientes / follow-ups
- **I3** Archivos: links públicos por token — siguiente.

## Referencias
- `system/app/Services/ScheduleBuilder.php`, `system/app/Services/CronRunner.php`,
  `system/app/Views/admin/cron/form.php`, `tests/unit/ScheduleBuilderTest.php`.
