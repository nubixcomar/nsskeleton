# Walkthrough — Actualización de core: Fase 4 (el actualizador)

**Fecha y hora:** 2026-06-28 20:30 | **Agente:** core-updater | **Modelo:** claude-opus-4-8
**Sprint:** — | **Versión:** 1.13.0

---

## Resumen ejecutivo
Cuarta fase: el **actualizador de core** ejecutable. Con la frontera (Fase 1), el split (Fase 2)
y los puntos de extensión (Fase 3) ya en su lugar, esta fase aporta el motor que aplica una
versión nueva del core a un proyecto sin tocar lo de la app. Motor testeable
(`App\Services\CoreUpdater`) + CLI fino (`system/console/core-update.php`).

## Cambios realizados
- **`App\Services\CoreUpdater`** (nuevo):
  - `plan($oldLock,$newLock,$installRoot,$sourceRoot)`: clasifica cada archivo del core en
    add / update / skip / conflict / conflict_add / delete / delete_modified comparando el lock
    instalado (lo que el core envió antes), el árbol local (drift de la app) y el lock nuevo.
  - `apply($plan,...,$backupDir)`: respalda lo que toca, agrega/pisa/borra; en conflicto deja el
    nuevo como `.new` sin tocar el local. Escribe `applied.json`. Nunca lanza por archivo.
  - `rollback($backupDir,$installRoot)`: restaura pisados/borrados, quita agregados y `.new`.
  - `loadLockFiles()` / `summarize()`.
- **CLI `system/console/core-update.php`** (nuevo): resuelve `--source` (carpeta o **zip** vía
  ZipArchive), DRY-RUN por defecto (muestra el plan + detalle de conflictos/borrados), `--apply`
  (backup en `storage/backups/core-update-<ts>/`, confirmación salvo `--yes`), `--rollback=<dir>`.
  Tras aplicar corre `Migrator::migrateCore()` (best-effort) y actualiza
  `core-lock.json`/`core-manifest.json`/`VERSION` desde el paquete.
- **Manifest**: `core_version` → 1.13.0 y quitadas las entradas muertas del showcase Clientes
  (eliminado en trabajo paralelo).

## Decisiones de diseño
- **Motor separado del CLI**: `CoreUpdater` es PHP puro y testeable con fixtures (sin DB/red);
  el CLI solo orquesta source/IO/migraciones.
- **Comparación a 3 puntas** (instalado vs local vs nuevo) para distinguir "update limpio" de
  "la app editó el core" (conflicto). El conflicto nunca pisa: deja `.new`.
- **Backup + applied.json** habilitan rollback simple y verificable.
- **Solo toca el core**: lo que no está en ningún lock (overrides, módulos, datos) es untracked
  y queda intacto por construcción.

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| system/app/Services/CoreUpdater.php | nuevo (motor plan/apply/rollback) |
| system/console/core-update.php | nuevo (CLI dry-run/apply/rollback) |
| tests/unit/CoreUpdaterTest.php | nuevo (plan/apply/rollback con fixtures temp) |
| core-manifest.json | core_version 1.13.0 + limpieza Clientes |
| core-lock.json | regen |
| VERSION, docs/CHANGELOG.md | bump 1.13.0 |

## Testing / verificación
- `php -l` OK en motor y CLI; `--help` OK.
- Unit test del motor: add/update/skip/conflict/conflict_add/delete/delete_modified + apply
  (pisa, deja .new, agrega, borra, backups) + rollback (restaura) — verde.
- CLI dry-run smoke contra un source sintético (all-skip y 1-update) — ver corrida.
- Suite completa: ver corrida (objetivo verde, incluyendo `--check` del lock).

## Pendientes / follow-ups
- **Fase 5**: comando `/actualizar-core` + agente `core-updater` (interpreta conflictos y
  ayuda al merge de la capa agéntica) + `docs/CORE-UPDATE.md`.
- **Fase 6**: `/release` genera y publica `core-manifest.json` + `core-lock.json` +
  `nsSkeleton-core-x.y.z.zip`; opcional `--url` para bajar el paquete del landing.
- (Opcional) merge 3-way asistido para conflictos en vez de `.new`.

## Referencias
- `system/app/Services/CoreUpdater.php`, `system/console/core-update.php`.
