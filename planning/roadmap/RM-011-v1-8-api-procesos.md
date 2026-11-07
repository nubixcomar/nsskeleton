---
code: RM-011
title: API & procesos (Track G)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-06-23
version: v1.8
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-011 — API & procesos (Track G)

> Migrado de `docs/roadmap.md` (sección «v1.8 — API & procesos (Track G) · ✅ COMPLETA (2026-06-23)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Versión del core de nsSkeleton (ver detalle en `docs/CHANGELOG.md`).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.8** · fecha de entrega: **2026-06-23**.

## Alcance macro (funcionalidades entregadas)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **G1** API+ (paginación, filtros, scopes por token, rate-limit) | ✅ | Paginator meta + filtros/orden/q + scopes read/write + RateLimiter 429; 196/196 |
| 2 | **G2** Doc OpenAPI/Swagger autogenerada                    | ✅     | OpenApiGenerator 3.0.3 + /api/openapi(.json) + visor /api/docs; 200/200 |
| 3 | **G3** Cola de jobs generalizada (no solo emails) + panel  | ✅     | JobQueue + contrato Job + reintentos/backoff + CLI + cron queue:work + panel; 204/204 |
| 4 | **G4** Webhooks salientes (eventos → URLs externas)        | ✅     | Webhook + entrega por cola + firma HMAC + panel + evento admin.created; 207/207 |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-011 | Migración a la metodología Roadmap → Brief → Plan |
