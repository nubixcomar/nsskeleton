---
code: RM-019
title: Cron v2 (cron master + tareas URL + historial)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-07-09
version: v1.19
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-019 — Cron v2 (cron master + tareas URL + historial)

> Migrado de `docs/roadmap.md` (sección «v1.19 — Cron v2 (cron master + tareas URL + historial) · ✅ COMPLETA (2026-07-09)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Portado del scheduler de **nsCentral** a MySQL. Plan: [`plan-cron-v2.md`](../../agentic/knowledge/skeleton/plan-cron-v2.md).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.19** · fecha de entrega: **2026-07-09**.

## Alcance macro (funcionalidades entregadas)
| # | Feature | Estado | Notas |
|---|---------|--------|-------|
| 1 | **Cron master** (disparador web protegido) | ✅ | `/cron/run` secreto/IP/toggle + `App\Services\CronMaster` + pantalla `/admin/cron/master`; 9 tests |
| 2 | **Tareas tipo URL** (HTTP con reintentos) | ✅ | prefijo `url:` en `CronRunner` vía `Core\Http`; 4 tests |
| 3 | **Historial** + Limpiar OK / Vaciar | ✅ | `/admin/cron/history` filtros + disparo + duración; `cron_runs` +trigger_type/+duration_ms; 4 tests |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-019 | Migración a la metodología Roadmap → Brief → Plan |
