---
name: actualizar-core
usage: /actualizar-core <dir|zip|url>
spawns: [core-updater]
---

## Qué hace
Actualiza el **core de nsSkeleton** del proyecto a una versión nueva, **sin afectar lo ya
desarrollado** (overrides, módulos, datos). Envuelve `system/console/core-update.php`.

## Proceso
1. Invoca el agente `core-updater`.
2. **DRY-RUN**: `php system/console/core-update.php --source=<dir|zip>` (o `--url=<url>`) y
   muestra el plan: agregados/actualizados/eliminados limpios y, sobre todo, **conflictos**
   (archivos del core que la app editó → quedarán como `.new`).
3. Pide confirmación. Si hay conflictos, explica cada uno y propone moverlos a un punto de
   extensión (`config/overrides/`, `routes.app.php`, `app/Views/overrides/`, `app-agentic/…`).
4. **Aplica**: `… --apply` (backup automático en `storage/backups/` + migraciones del core).
5. Resuelve los archivos `.new` (merge) y **corre la suite** (`php tests/run.php`).
6. Si algo quedó mal: `… --rollback=<backupDir>`.
7. Registra el walkthrough (versión origen→destino, conflictos y resolución, backupDir).

## Restricciones
- **Dry-run antes de aplicar.** No aplica sin confirmación del humano ante conflictos.
- No toca lo de la app (solo gestiona archivos del core, los del `core-lock.json`).
- No commitea/pushea sin permiso.

## Ejemplos
- `/actualizar-core ../nsSkeleton-core-1.15.0.zip`
- `/actualizar-core https://misitio/downloads/nsSkeleton-core-1.15.0.zip`

> Detalle completo del modelo (core vs app, conflictos, rollback, publicación):
> [`../../docs/CORE-UPDATE.md`](../../docs/CORE-UPDATE.md).
