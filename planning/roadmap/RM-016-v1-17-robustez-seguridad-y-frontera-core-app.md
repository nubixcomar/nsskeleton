---
code: RM-016
title: Robustez, seguridad y frontera core/app (P1)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-07-09
version: v1.17
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-016 — Robustez, seguridad y frontera core/app (P1)

> Migrado de `docs/roadmap.md` (sección «v1.17 — Robustez, seguridad y frontera core/app (P1) · ✅ COMPLETA (2026-07-09)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Versión del core de nsSkeleton (ver detalle en `docs/CHANGELOG.md`).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.17** · fecha de entrega: **2026-07-09**.

## Alcance macro (funcionalidades entregadas)
| # | Feature | Estado | Notas |
|---|---------|--------|-------|
| 1 | Frontera código-app fuera de core-lock (1.6) | ✅ | autoloader multi-dir + `app/` raíz + README; 0 drift; 4 tests |
| 2 | Paquete de deploy con allowlist (1.5) | ✅ | `config/deploy.php` + `--production`; 532→251 archivos; 5 tests |
| 3 | Aislamiento de tests (rollback/DB test) (1.7) | ✅ | `Database::transaction` + helper `itDb()`; 4 tests + doc |
| 4 | Test de nombre por `APP_NAME` (1.7) | ✅ | unit+feature leen `APP_NAME`; rename no rompe |
| 5 | Error-log server-side siempre-on + rotación (1.8) | ✅ | `Core\ErrorLog` (traza+rotación+tail); `App` lo usa; 4 tests |
| 6 | Visor de logs en /admin (1.8) | ✅ | `/admin/logs` + `LogViewerController` + permiso `logs.view`; 5 tests |
| 7 | Auth multi-guard / login por rol (1.9) | ✅ | `config/auth.php` + `Core\Guard` (sesiones aisladas); `Auth` fachada retrocompat; 7 tests |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-016 | Migración a la metodología Roadmap → Brief → Plan |
