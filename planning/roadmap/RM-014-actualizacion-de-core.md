---
code: RM-014
title: Actualización de core (v1.11–v1.15)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-06-28
version: v1.11–v1.15
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-014 — Actualización de core (v1.11–v1.15)

> Migrado de `docs/roadmap.md` (sección «Actualización de core (v1.11–v1.15) · ✅ COMPLETO (2026-06-28)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Mecanismo para que un proyecto derivado actualice su core **sin afectar lo ya desarrollado**.
Detalle en [`CORE-UPDATE.md`](../../docs/CORE-UPDATE.md). Decisiones: distribución zip+manifiesto,
frontera por manifiesto+lock (sin mover archivos), regla "override no edición".

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.11–v1.15** · fecha de entrega: **2026-06-28**.

## Alcance macro (funcionalidades entregadas)
| # | Fase                                                       | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | Frontera declarativa (`core-manifest.json` + `core-lock.json`) | ✅ | generador `core-manifest.php` + `--check` drift |
| 2 | Split agéntico (`core-rules`/`app-rules`/`app-agentic/`)   | ✅     | precedencia app > core + override por nombre |
| 3 | Puntos de extensión (Config overrides, `routes.app.php`, vistas child-theme, migraciones app) | ✅ | overridear sin editar el core |
| 4 | Updater (`CoreUpdater` + `core-update.php`)               | ✅     | plan/apply/rollback + conflictos `.new` |
| 6 | Publicación (`build-core-package.php` + `/release` + `--url`) | ✅  | zip de core derivado del lock |
| 5 | Capa asistida (`/actualizar-core` + agente/skill + doc)   | ✅     | dry-run, triage, verificación |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-014 | Migración a la metodología Roadmap → Brief → Plan |
