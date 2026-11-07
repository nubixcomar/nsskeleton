---
code: RM-009
title: Generador++ y datos (Track E)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-06-23
version: v1.6
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-009 — Generador++ y datos (Track E)

> Migrado de `docs/roadmap.md` (sección «v1.6 — Generador++ y datos (Track E) · ✅ COMPLETA (2026-06-23)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Cada fase: build + tests versionados + verificación + walkthrough.

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.6** · fecha de entrega: **2026-06-23**.

## Alcance macro (funcionalidades entregadas)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **E1** Relaciones en el generador (FK `belongsTo` + select) | ✅    | tipo fk + parseRelations + RelationOptions + FK en migración + select + etiqueta; 158/158 |
| 2 | **E2** Validaciones por campo (requerido/email/numérico/único) | ✅ | Validator + parseRules + errores/old en form; 165/165 |
| 3 | **E3** Exportar listados (CSV / Excel / PDF)               | ✅     | Exporter + Response::download + botones en índice; 171/171 |
| 4 | **E4** Soft-delete + papelera (restaurar)                  | ✅     | deleted_at + Paginator filter + trash/restore/force + vista papelera; 173/173 |
| 5 | **E5** Búsqueda global + filtros por columna               | ✅     | GlobalSearch + SearchController + buscador en topbar; 178/178 |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-009 | Migración a la metodología Roadmap → Brief → Plan |
