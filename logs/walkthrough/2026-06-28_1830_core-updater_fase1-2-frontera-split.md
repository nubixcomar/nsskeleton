# Walkthrough — Actualización de core: Fase 1 (frontera) + Fase 2 (split agéntico)

**Fecha y hora:** 2026-06-28 18:30 | **Agente:** core-updater | **Modelo:** claude-opus-4-8
**Sprint:** — | **Versión:** 1.11.0

---

## Resumen ejecutivo
Primeras dos fases del mecanismo de **actualización de core** para proyectos derivados de
nsSkeleton. Fase 1: frontera declarativa core/app (manifiesto + lock con checksums), bajo el
principio "el core es dueño solo de lo que lista; todo lo demás es del proyecto". Fase 2:
split de la capa agéntica en `agentic/` (core, se actualiza) vs `app-agentic/` (proyecto,
jamás se pisa), con precedencia app > core y override por nombre. Decisiones del usuario:
distribución por zip+manifiesto, frontera "solo manifiesto+lock" (sin mover archivos),
renombrar `project-rules.md` → `core-rules.md` + agregar `app-rules.md`.

## Cambios realizados
- **Fase 1**
  - `core-manifest.json` (raíz): reglas de propiedad `core_paths` / `app_paths` / `exclude`.
  - `system/console/core-manifest.php`: generador del lock; recorre el árbol, clasifica y
    emite `core-lock.json` (sha256 por archivo). Flag `--check` (drift, exit 1).
  - `core-lock.json`: 401 archivos core. Showcase **Clientes** y docs de proyecto → app.
- **Fase 2**
  - Árbol `app-agentic/` (rules/agents/skills/knowledge/templates/modules) con READMEs y
    `app-rules.md` (plantilla del proyecto).
  - `agentic/rules/project-rules.md` → `core-rules.md` (encabezado: es core, no editar; usar app-rules).
  - `agentic/rules/rules.md`: orden de carga core→app, sección "Core vs proyecto" + override por nombre.
  - `AGENTS.md`: sección "Core vs proyecto", catálogo con app-agentic y precedencia.
  - Referencias actualizadas: skills architect/dev-backend/ecommerce-integration/installer,
    `installer/questions.yml`, `installer/README.md`.

## Decisiones de diseño
- **Frontera por lista, no por ubicación**: no se movió ningún archivo; el límite lo define el
  manifiesto + lock. Adopción inmediata; el updater (Fase 4) usará el lock para detectar drift.
- **Override por nombre** en la capa agéntica: el proyecto cambia comportamiento del core sin
  editar archivos del core → el update queda limpio.
- `core-lock.json` se autoexcluye del lock; `system/storage/`, `.env`, `secrets/`, `logs/`,
  `landing/` nunca son core.

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| core-manifest.json | nuevo (reglas de propiedad) |
| core-lock.json | nuevo (snapshot generado, 401 core) |
| system/console/core-manifest.php | nuevo (generador + --check) |
| app-agentic/** (README + rules/app-rules.md + 5 subdirs con README) | nuevo (capa proyecto) |
| agentic/rules/core-rules.md | nuevo (ex project-rules.md, reglas core) |
| agentic/rules/project-rules.md | eliminado (renombrado) |
| agentic/rules/rules.md, AGENTS.md | orden core→app + precedencia + override |
| agentic/skills/{architect,dev-backend,ecommerce-integration,installer}/SKILL.md | refs core-rules/app-rules |
| installer/questions.yml, installer/README.md | escriben app-rules.md (no editan core) |
| tests/unit/CoreBoundaryTest.php | nuevo (12 tests Fase 1+2) |
| VERSION, docs/CHANGELOG.md | bump 1.11.0 |

## Testing / verificación
- `php -l` OK en el generador.
- Generador: 401 core / 9 app / 0 sin clasificar.
- Suite completa: ver corrida (objetivo: todos verdes, incluyendo CoreBoundaryTest y `--check`).

## Pendientes / follow-ups (próximas fases)
- **Fase 3**: puntos de extensión (`Core\Config` con overrides, `routes.app.php`, vistas
  child-theme, migraciones core namespaced `core_*`).
- **Fase 4**: `system/console/core-update.php` (dry-run, backup, apply-by-manifest, conflictos,
  migraciones, rollback).
- **Fase 5**: comando `/actualizar-core` + agente `core-updater` + `docs/CORE-UPDATE.md`.
- **Fase 6**: integrar a `/release` (publicar manifest + lock + zip core).
- Migrar el código del core de `system/` a su propia clasificación dura (opcional, a futuro).

## Referencias
- `core-manifest.json`, `core-lock.json`, `app-agentic/README.md`.
