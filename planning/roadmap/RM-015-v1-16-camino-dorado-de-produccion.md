---
code: RM-015
title: Camino dorado de producción (P0)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-07-09
version: v1.16
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-015 — Camino dorado de producción (P0)

> Migrado de `docs/roadmap.md` (sección «v1.16 — Camino dorado de producción (P0) · ✅ COMPLETA (2026-07-09)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Deriva de la **auditoría de nsCheckout** (deploy real nginx + integración MP embebido).
Plan detallado: [`agentic/knowledge/skeleton/plan-mejoras-post-nscheckout.md`](../../agentic/knowledge/skeleton/plan-mejoras-post-nscheckout.md).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.16** · fecha de entrega: **2026-07-09**.

## Alcance macro (funcionalidades entregadas)
| # | Feature                                                    | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **CSP configurable** por env/config + merge por-respuesta (1.1) | ✅ | `config/security.php`, `Security::csp()`, `Response::withCsp/hasHeader`, `App` merge; 9 tests |
| 2 | **Presets de CSP** por integración (mercadopago/stripe/recaptcha) (1.1) | ✅ | `preset_definitions` + `CSP_PRESETS` env; incluido en los 9 tests |
| 3 | **Router: override real** app→core (last-wins) + doc (1.2) | ✅     | `Router::add` reemplaza por method+path; app al final de routes.php; 4 tests |
| 4 | **Templates nginx + Apache** + docroot=`system/public` (1.3) | ✅   | `docs/deploy/` (nginx/apache/README camino dorado) |
| 5 | **`APP_BASE_PATH`** explícito por env (1.3)                | ✅     | `Url::base()` override + `Request` lo reutiliza; 5 tests |
| 6 | **`session.save_path`** → `storage/sessions/` (1.4)       | ✅     | `Session::configureSavePath`; `SESSION_PATH`; gitignore corregido; 3 tests |
| 7 | **`deploy:doctor`** (docroot/rewrite/php/permisos) (1.3)  | ✅     | `DeployDoctor` + `deploy.php doctor`; 4 tests |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-015 | Migración a la metodología Roadmap → Brief → Plan |
