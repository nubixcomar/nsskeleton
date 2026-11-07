# Walkthrough — Metodología Roadmap → Brief → Plan (RM → BR → PL)

**Fecha y hora:** 2026-11-07 09:36 | **Agente:** core-dev | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** [Sin publicar] (core 1.19.0) | **Trazas:** —

---

## Resumen ejecutivo
Se incorporó al core una filosofía de trabajo nativa para implementaciones medianas y grandes:
**entender → decidir → codear**, materializada en tres artefactos codificados con estado e
índice (Roadmap `RM-`, Brief `BR-`, Plan `PL-`), tres comandos y seis agentes. El objetivo es
que los planes sean contratos ejecutables (casi pseudocódigo) que permitan ejecución agéntica
por fases, en loop, con modelos más económicos ("pensar caro, ejecutar barato").

## Cambios realizados
- **Metodología** `agentic/methodology/roadmap-brief-plan.md`: principios, artefactos, codificación
  e índices, estados y propagación, Definition of Ready (brief/plan), agentes del brief,
  modelo/esfuerzo por fase, tamaños (M → plan; L/XL → RM+BR+PL), excepciones (urgencia,
  imprevisto, cambios de fechas) y contrato de iteración de `/plan run`.
- **Comandos** `/roadmap` (`/rm`), `/brief` (`/br`), `/plan` (`/pl`) con subcomandos.
- **Skills + agentes**: `product/` (brief-discovery, creative-explorer, devils-advocate) y
  `planning/` (roadmap-manager, plan-architect, plan-executor). Campo opcional `effort_hint`.
- **Scaffold** `agentic/templates/planning/` (README, 3 INDEX, RM/BR/PL templates). `/roadmap init`
  copia README + índices a `planning/` (proyecto); las plantillas de ítem quedan en core.
- **Integración**: rules.md §2b, sprints.md (planning + DoD con plan), logging.md (paso 6 +
  ejemplo con códigos), walkthrough template (Trazas), AGENTS.md, README, empezar.md, INSTALL.md,
  /instalar, sprint-manager, índices de agents/commands/templates/methodology, CHANGELOG.
- `core-manifest.json`: `planning/` en `app_paths` + nota. `core-lock.json` regenerado.

## Decisiones de diseño
- `planning/` en la raíz como capa de **proyecto** (no en `docs/`, que es core): el actualizador
  nunca la toca y no hace falta excepcionar archivos sueltos.
- El scaffold **no** se crea en el skeleton master (evita que el roadmap del skeleton viaje a los
  proyectos derivados); se crea on-demand con `/roadmap init` o durante `/instalar`.
- `docs/brief.md` queda como **brief maestro** del producto; los `BR-` son briefs de iniciativa.
  `docs/roadmap.md` queda como legado / vista de releases; la fuente de verdad pasa a ser
  `planning/roadmap/INDEX.md` en proyectos que adoptan el flujo.
- **Sugerir, no bloquear**: las fases se pueden saltear, pero queda escrito (`origin`/`exception`)
  y se sugiere regularizar al cerrar.
- Alias cortos (`/rm`, `/br`, `/pl`) por si la IA tiene un `/plan` nativo.
- Markdown puro (agnóstico al stack); front-matter pensado para un linter/reindexador futuro.

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| agentic/methodology/roadmap-brief-plan.md | nuevo |
| agentic/commands/{roadmap,brief,plan}.md | nuevos |
| agentic/skills/{roadmap-manager,brief-discovery,creative-explorer,devils-advocate,plan-architect,plan-executor}/SKILL.md | nuevos |
| agentic/agents/{product,planning}/*.md | nuevos (6 agentes + 2 README) |
| agentic/templates/planning/** | nuevo scaffold (7 archivos) |
| AGENTS.md, README.md, docs/empezar.md, docs/INSTALL.md, docs/CHANGELOG.md | enlaces y flujo |
| agentic/rules/rules.md, methodology/{README,sprints,logging}.md | integración |
| agentic/agents/{INDEX,README}.md, commands/{README,instalar}.md, templates/{README,walkthrough.template}.md, skills/sprint-manager/SKILL.md | integración |
| core-manifest.json, core-lock.json | app_paths + lock regenerado |
| tests/unit/AgenticStructureTest.php | 5 tests nuevos |

## Testing / verificación
- `php tests/run.php`: 420 PASS · 1 FAIL · 1 SKIP. Los 5 tests nuevos pasan y el drift del lock
  quedó resuelto. Fallo preexistente y ajeno: `CronMaster: deshabilitado por defecto` (depende de
  que el cron master esté habilitado en la DB local).
- Chequeo de links relativos de todos los .md nuevos/tocados: sin rotos.

## Pendientes / follow-ups
- Opcional: CLI `planning lint|reindex` que valide front-matter/vínculos y regenere índices de
  forma determinística (útil para loops largos).
- Opcional: adoptar el flujo en el propio skeleton (migrar `docs/roadmap.md` a `RM-`).
- Nota: el working tree tenía trabajo sin commitear de otra sesión (manuales remotos, core-update
  3-way); no se tocó. El lock regenerado incluye esos archivos.

## Referencias
- agentic/methodology/roadmap-brief-plan.md
