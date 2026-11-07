---
code: RM-002
title: Sistema base operativo
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-06-22
version: v0.2
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-002 — Sistema base operativo

> Migrado de `docs/roadmap.md` (sección «v0.2 — Sistema base operativo») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Versión del core de nsSkeleton (ver detalle en `docs/CHANGELOG.md`).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v0.2** · fecha de entrega: **2026-06-22**.

## Alcance macro (funcionalidades entregadas)
| # | Funcionalidad                                   | Estado | Sprint | Notas |
|---|--------------------------------------------------|--------|--------|-------|
| 1 | Login admin + gestión de perfiles               | ✅     | S1     | render+guard probados |
| 2 | Diseño backend (Tailwind + Alpine, responsive)  | ✅     | S1     | layout admin + sidebar responsive |
| 3 | Cron / programador de tareas (cronmaster)       | ✅     | S1     | parser cron 11/11 tests; guard OK |
| 4 | Configuración y envío de emails                 | ✅     | S1     | SMTP propio; error-path OK; guard OK |
| 5 | Backup y restauración automática                | ✅     | S1     | ZIP real probado (163 entradas); guard OK |
| 6 | Librería de gráficos (barras, torta, dashboards)| ✅     | S1     | Chart.js + dashboard con datos reales; partial probado |
| 7 | File manager (carpetas/subcarpetas, uploads)    | ✅     | S1     | 13/13 tests reales; anti-traversal; guard OK |
| 8 | Conector de IA (OpenAI/Deepseek, credenciales)  | ✅     | S1     | 7/7 tests; error-path OK; guard OK |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-002 | Migración a la metodología Roadmap → Brief → Plan |
