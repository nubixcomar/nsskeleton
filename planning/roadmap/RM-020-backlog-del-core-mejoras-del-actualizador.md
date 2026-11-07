---
code: RM-020
title: Backlog del core — mejoras del actualizador
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-11-07
version: 1.20.0
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-020 — Backlog del core — mejoras del actualizador

> Migrado de `docs/roadmap.md` (sección «Backlog del core — mejoras del actualizador · ✅ COMPLETO (2026-11-07, sin publicar)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Mejoras sobre el mecanismo de actualización de core. Implementadas juntas sobre un
orquestador común (`App\Services\CoreUpdate`) que usan el CLI y el panel. 23 tests nuevos +
prueba E2E por HTTP en una instalación aislada (update, merge, choque, rollback).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **1.20.0** · fecha de entrega: **2026-11-07**.

## Alcance macro (funcionalidades entregadas)
| # | Mejora                                                     | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **Merge 3-way asistido** para conflictos                  | ✅     | `ThreeWayMerge` (diff3 PHP puro) + base `storage/core-base/` (captura oportunista, rotación por update, `--seed-base[=remote]`); sin choques → mergeado, con choques → `.new` + `.merge` |
| 2 | **`latest.json`** en el landing                            | ✅     | `build-core-package.php` lo escribe; `CoreRelease` + `--check` (exit 3) / `--latest` / `--sha256`; descarga verificada por sha256 |
| 3 | **UI de admin** para disparar/monitorear el update         | ✅     | `/admin/core-update` (permiso `core.update`): buscar → preparar → plan con vista previa del merge → aplicar (confirmación con la versión) → historial + revertir; `CORE_UPDATE_UI=false` la apaga |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-020 | Migración a la metodología Roadmap → Brief → Plan |
