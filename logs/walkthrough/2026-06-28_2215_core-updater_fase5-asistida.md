# Walkthrough — Actualización de core: Fase 5 (capa asistida) — roadmap COMPLETO

**Fecha y hora:** 2026-06-28 22:15 | **Agente:** core-updater | **Modelo:** claude-opus-4-8
**Sprint:** — | **Versión:** 1.15.0

---

## Resumen ejecutivo
Quinta y última fase del mecanismo de actualización de core: la **capa asistida** (comando +
agente + skill + documentación) sobre el motor (Fase 4) y la publicación (Fase 6). Con esto el
roadmap de actualización de core queda **COMPLETO** (Fases 1–6).

## Cambios realizados
- **Skill `core-updater`** (`agentic/skills/core-updater/SKILL.md`): guía el update —
  dry-run obligatorio, triage de conflictos (y propuesta de moverlos a overrides), apply con
  backup, resolución de `.new`, verificación con la suite, rollback.
- **Agente `core-updater`** (`agentic/agents/pm/core-updater.md`): wrapper fino del skill.
- **Comando `/actualizar-core <dir|zip|url>`** (`agentic/commands/actualizar-core.md`): orquesta
  el flujo y spawnea el agente.
- **`docs/CORE-UPDATE.md`**: documento de usuario completo — modelo core vs proyecto, regla
  "override no edición", tabla de puntos de extensión, uso del CLI (source/url/apply/rollback),
  el plan por archivo, resolución de conflictos, rollback y publicación de core.
- **INDEX de agentes**: agregado `core-updater` en pm/.
- Test: bloque "Fase 5" en `CoreBoundaryTest` (comando/skill/agente/doc presentes).

## Decisiones de diseño
- **Asiste, no automatiza la decisión**: el agente nunca auto-mergea lógica de negocio; ante
  conflicto propone y pide OK. Dry-run siempre antes de aplicar.
- **Empuja a la regla de oro**: cuando detecta que la app editó un archivo del core, recomienda
  migrar ese cambio a un override para que el próximo update sea limpio.
- Categoría `pm/` (junto a `release-manager`): publicar y actualizar son dos caras del ciclo
  de vida del core.

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| agentic/skills/core-updater/SKILL.md | nuevo |
| agentic/agents/pm/core-updater.md | nuevo |
| agentic/commands/actualizar-core.md | nuevo |
| docs/CORE-UPDATE.md | nuevo (doc completa del mecanismo) |
| agentic/agents/INDEX.md | + core-updater en pm/ |
| tests/unit/CoreBoundaryTest.php | + presencia de piezas Fase 5 |
| core-manifest.json, core-lock.json | core_version 1.15.0 + regen |
| VERSION, docs/CHANGELOG.md | bump 1.15.0 |

## Testing / verificación
- Suite completa: ver corrida (objetivo verde, incluyendo el bloque Fase 5 y `--check` del lock).

## Pendientes / follow-ups
- Roadmap de actualización de core: **COMPLETO** (1–6). 
- (Opcional, futuro) merge 3-way asistido para conflictos; índice `latest.json` para que
  `--url` resuelva la última versión; UI de admin para disparar el update.

## Referencias
- `docs/CORE-UPDATE.md`, `agentic/commands/actualizar-core.md`, `agentic/skills/core-updater/SKILL.md`.
