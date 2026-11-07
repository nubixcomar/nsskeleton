---
code: RM-022
title: Metodología Roadmap → Brief → Plan nativa
status: 🟡 En curso
priority: P1
start_tentative: 2026-11-07
due_tentative: 2026-11-14
version: v1.20 (tentativa)
briefs: []
plans: [PL-001]
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-022 — Metodología Roadmap → Brief → Plan nativa

## Descripción
Incorporar al core una filosofía de trabajo para implementaciones medianas y grandes:
**entender → decidir → codear**. Tres artefactos codificados con estado e índice (`RM-`, `BR-`,
`PL-`), comandos `/roadmap`, `/brief`, `/plan`, agentes de descubrimiento (curioso, creativo,
escéptico) y de planificación/ejecución, y un helper determinístico para mantener los índices.

## Objetivo y valor
- Que los planes sean **contratos ejecutables** (casi pseudocódigo) y la ejecución pueda correr
  por fases, en loop, con modelos más económicos ("pensar caro, ejecutar barato").
- Que las dudas se despejen en el brief y no mientras se codea.
- Que cualquier proyecto derivado, sin importar producto, stack o tamaño de equipo, herede una
  forma ordenada de avanzar.

## Alcance macro
- **Incluye:** metodología (`agentic/methodology/roadmap-brief-plan.md`), comandos, 6 agentes +
  skills, scaffold `agentic/templates/planning/`, integración con reglas/sprints/logging, helper
  `planning` (lint/reindex/next/init/review) y adopción en el propio skeleton (este roadmap).
- **No incluye:** UI de admin para planning; integración con gestores externos (ClickUp, Jira).

## Dependencias
- Ninguna técnica. Release: entra en la próxima versión del core junto con lo que hay en
  `[Sin publicar]` (RM-020, RM-021).

## Hitos
| Hito | Fecha tentativa | Estado |
|------|-----------------|--------|
| Metodología + comandos + agentes + scaffold | 2026-11-07 | ✅ |
| Helper `planning` (PL-001) | 2026-11-07 | ✅ |
| Adopción en el skeleton (migración de `docs/roadmap.md`) | 2026-11-07 | ✅ |
| Release del core con la metodología | 2026-11-14 | 📋 |

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| PL-001 | Helper CLI de planning | ✅ Completado |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | creado | Pedido del humano: metodología ordenada para implementaciones medianas/grandes |

## Notas
- La metodología en sí (primer bloque) se construyó **sin brief ni plan**: era el bootstrap
  (no existían todavía los artefactos para planificarla). Queda registrado como excepción.
