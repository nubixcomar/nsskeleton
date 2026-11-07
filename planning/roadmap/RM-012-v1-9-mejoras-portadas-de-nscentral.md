---
code: RM-012
title: Mejoras portadas de nsCentral (Track I)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-06-27
version: v1.9
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-012 — Mejoras portadas de nsCentral (Track I)

> Migrado de `docs/roadmap.md` (sección «v1.9 — Mejoras portadas de nsCentral (Track I) · ✅ COMPLETA (2026-06-27)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Ideas tomadas del proyecto nsCentral (`C:\xampp\htdocs\impulso`, persistencia JSON) y
adaptadas a la arquitectura MVC + MySQL de skeleton. Cada fase: build + tests + verificación + walkthrough.

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.9** · fecha de entrega: **2026-06-27**.

## Alcance macro (funcionalidades entregadas)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **I1** Conector IA: proveedor Anthropic (Claude) + adapters | ✅    | style openai/anthropic + buildRequest/extractContent/parseSseAnthropic; 213/213 |
| 2 | **I2** Cronmaster v2 (presets + describe + prioridad/timeout/throttle) | ✅ | ScheduleBuilder + constructor Alpine + orden por prioridad + timeout/throttle en runner; 222/222 |
| 3 | **I3** Archivos: links públicos por token (`/a/{token}`)   | ✅     | FileShare + PublicFileController + toggle compartir/revocar + badge; 224/224 |
| 4 | **I4** Sistema de avisos/alertas (providers) + widget dashboard | ✅ | AlertProvider + AlertService + 4 providers (jobs/cron/cola/backup) + widget; 228/228 |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-012 | Migración a la metodología Roadmap → Brief → Plan |
