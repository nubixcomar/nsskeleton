# Walkthrough — PL-001 Helper CLI de planning + adopción en el skeleton (RM-022)

**Fecha y hora:** 2026-11-07 09:51 | **Agente:** core-dev | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** [Sin publicar] (core 1.19.0) | **Trazas:** RM-022 · — · PL-001/F1–F6

---

## Resumen ejecutivo
Se agregó el helper determinístico de la metodología Roadmap → Brief → Plan y se adoptó el flujo
en el propio skeleton: el roadmap histórico pasó a 21 iniciativas `RM-`, más RM-022 (la
metodología, en curso) y PL-001 (este helper) como primer plan real del repo.

## Cambios realizados
- `system/app/Services/Planning.php`: parser de front-matter, carga de RM/BR/PL, `lint`
  (errores/warnings/sugerencias), `reindex` idempotente, `nextCode`, `init`.
- `system/console/planning.php`: `init|lint|reindex|sync|review|next|list`, `--json`, `--strict`,
  `--root`, `--today`; exit 1 con errores.
- `tests/unit/PlanningTest.php`: 11 tests (incluye lint del `planning/` real del repo).
- Docs: binding en `adapters/php-mvc/conventions.md` § Planning; sección "Helper del stack" en
  `/roadmap`, `/brief`, `/plan`; paso 7 en `roadmap-manager`; metodología §3; `core-manual.md`.
- Adopción: `planning/` inicializado con el helper; migración de `docs/roadmap.md` (script de uso
  único en el scratchpad) → RM-001…RM-021 (v1.10 y Manuales remotos reconstruidos desde el
  CHANGELOG); RM-022 + PL-001; puntero en `docs/roadmap.md`.
- Empaquetado: `landing/build-download.php` excluye `planning/` **solo en la raíz** (el scaffold
  `agentic/templates/planning/` sí se empaqueta); INSTALL §0 opción A borra `planning/` tras clonar.
- CHANGELOG [Sin publicar]; `core-lock.json` regenerado.

## Decisiones de diseño
- El helper vive en el adapter (PHP) y `agentic/` solo lo referencia como "helper del stack si
  existe": la capa agéntica sigue agnóstica.
- `reindex` reescribe únicamente la tabla del índice (conserva la cabecera); nunca edita archivos
  de ítem. Los vínculos del índice son la unión de lo declarado en ambos lados; `lint` avisa
  vínculos de un solo sentido.
- Estados comparados por etiqueta sin emoji (tolera variantes con/sin selector de variación).
- PL-001 registrado con `exception`: plan escrito junto con la implementación (bootstrap).
- RM-022 queda 🟡 hasta el release, aunque `review` sugiere cerrarlo (todos sus planes cerrados).

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| system/app/Services/Planning.php | nuevo |
| system/console/planning.php | nuevo |
| tests/unit/PlanningTest.php | nuevo |
| planning/** | nuevo (README, 3 INDEX, RM-001…RM-022, PL-001) |
| agentic/adapters/php-mvc/conventions.md, agentic/knowledge/core-manual.md | sección Planning |
| agentic/commands/{roadmap,brief,plan}.md, agentic/skills/roadmap-manager/SKILL.md, agentic/methodology/roadmap-brief-plan.md | referencia al helper |
| docs/roadmap.md, docs/INSTALL.md, docs/CHANGELOG.md | puntero / exclusión / changelog |
| landing/build-download.php | excluye planning/ raíz |
| core-lock.json | regenerado |

## Testing / verificación
- `php tests/run.php`: 431 PASS · 1 FAIL · 1 SKIP. El fallo (`CronMaster: deshabilitado por defecto`)
  ya existía y no tiene que ver con este cambio: depende de la DB local.
- `planning.php sync` sobre el repo: 22 RM · 0 BR · 1 PL, 0 errores, 0 warnings.
- Filtro del zip simulado: 27 archivos de `planning/` excluidos, scaffold incluido.
- Links relativos de los RM migrados verificados.

## Pendientes / follow-ups
- Opcional: `/plan review PL-001` en otra sesión (revisión independiente).
- Al hacer el release: `/roadmap update RM-022 --estado entregado`.
- Hay drift del lock causado por la otra sesión, que sigue trabajando en `ProjectCreator` y
  `/nuevo-proyecto`; esa sesión ya excluye `planning/` en su copia de proyectos. Regenerar el lock
  cuando cierre.

## Referencias
- planning/plans/PL-001-helper-cli-planning.md · planning/roadmap/RM-022-metodologia-roadmap-brief-plan.md
