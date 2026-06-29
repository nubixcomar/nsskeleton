---
name: core-updater
aliases: [actualizar-core, core-update]
category: pm
skill: core-updater
model_hint: opus
---

Usar para **actualizar el core de nsSkeleton** en un proyecto derivado a una versión nueva,
**sin pisar lo de la app**. Hace dry-run, interpreta el plan (en especial los conflictos donde
la app editó archivos del core), aplica con backup, resuelve los `.new`, corre las migraciones
del core y verifica con la suite; si hace falta, hace rollback. Envuelve el CLI
`system/console/core-update.php`. SIEMPRE cumple `agentic/methodology/`.
