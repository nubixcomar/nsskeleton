# Walkthrough — Fase B2: migraciones con rollback

**Fecha y hora:** 2026-06-23 01:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S3 (v1.2) | **Versión:** 1.1 → 1.2

---

## Resumen ejecutivo
Las migraciones ahora soportan reversa (`rollback`) y consulta de estado. Cada `.sql`
puede incluir una sección `-- @DOWN`. Verificado con un ciclo migrate/rollback real
sobre una migración aislada, sin afectar las tablas base.

## Cambios realizados
- **`App\Services\Migrator`**: `parse` (separa up/down por `-- @DOWN`), `migrate`,
  `rollback(N, dir)` (solo revierte migraciones del dir indicado, más recientes primero),
  `status` (aplicada + reversible), `fresh`.
- **`system/database/migrate.php`**: subcomandos `up` (default), `status`, `rollback [N]`,
  `fresh`.
- **`-- @DOWN`** agregado a las 7 migraciones base (DROP correspondiente, respetando FK).
- **Generador** (`make-module.php`): emite `-- @DOWN DROP TABLE` en los módulos nuevos.

## Verificación
- `php -l` OK (servicio, runner, generador, migraciones).
- **Suite**: `php tests/run.php` → **72/72 PASS** (+2 unit `parse`, +1 feature
  `migrate/rollback`).
- **Smoke (MySQL 3307)**:
  - `migrate status` → lista las 8 migraciones (la demo `productos`, previa al template,
    aparece "(sin rollback)").
  - El test feature aplica una migración aislada (crea tabla), valida `status`, y la
    revierte (dropea la tabla) — con limpieza garantizada en `finally`.
  - Tras todo, las migraciones base siguen aplicadas (8) y `admin_users` intacta.

## Decisiones de diseño
- `rollback` acotado al directorio indicado (no revierte migraciones ajenas) → seguro.
- Retrocompatible: una migración sin `-- @DOWN` simplemente no es reversible (se informa).
- `fresh` = rollback total + migrate (operación explícita).

## Pendientes / follow-ups
- **B3** Búsqueda + paginación reutilizable en listados — siguiente.

## Referencias
- `system/app/Services/Migrator.php`, `system/database/migrate.php`,
  `tests/feature/MigratorTest.php`.
