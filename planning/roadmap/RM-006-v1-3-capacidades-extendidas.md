---
code: RM-006
title: Capacidades extendidas
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-06-23
version: v1.3
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-006 — Capacidades extendidas

> Migrado de `docs/roadmap.md` (sección «v1.3 — Capacidades extendidas · ✅ COMPLETA (2026-06-23)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Versión del core de nsSkeleton (ver detalle en `docs/CHANGELOG.md`).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.3** · fecha de entrega: **2026-06-23**.

## Alcance macro (funcionalidades entregadas)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **C1** Capa API REST (tokens + CRUD desde modelos)         | ✅     | Bearer tokens + CRUD genérico; e2e OK; 94/94 |
| 2 | **C2** Roles/permisos real (RBAC)                          | ✅     | Rbac + guards + menú filtrado + visor; e2e 403/200 OK; 100/100 |
| 3 | **C3** Cron con jobs internos (callables) + lock           | ✅     | Jobs registry + job:<n> + flock anti-solapamiento; 105/105 |
| 4 | **C4** Emails con plantillas HTML + cola de envío          | ✅     | layout+plantilla, email_queue+worker job:email:queue; 109/109 |
| 5 | **C5** File manager: extensiones/tamaño, renombrar, preview | ✅    | whitelist+límite, rename/move anti-traversal, thumbnails; 118/118 |
| 6 | **C6** IA: librería de prompts + system prompt + streaming | ✅     | prompts {{var}} + system prompt; streaming diferido (cliente no-streaming); 125/125 |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-006 | Migración a la metodología Roadmap → Brief → Plan |
