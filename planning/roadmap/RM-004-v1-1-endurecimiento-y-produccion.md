---
code: RM-004
title: Endurecimiento y producción
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-06-23
version: v1.1
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-004 — Endurecimiento y producción

> Migrado de `docs/roadmap.md` (sección «v1.1 — Endurecimiento y producción · ✅ COMPLETA (2026-06-23)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Verificación: **cada fase agrega tests versionados** a `tests/` (la suite es la fase 1).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.1** · fecha de entrega: **2026-06-23**.

## Alcance macro (funcionalidades entregadas)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **A6** Suite de tests formal (runner `tests/run.php`)      | ✅     | 37/37 tests verdes; fundación lista |
| 2 | **A1** Cifrado en reposo de credenciales (SMTP, IA) + `APP_KEY` | ✅ | Crypto AES-256-GCM; 44/44 tests |
| 3 | **A2** Hardening login: rate-limit/lockout, mi-perfil, cambiar contraseña | ✅ | lockout 5 intentos; e2e OK; 46/46 tests |
| 4 | **A3** Recuperación de contraseña por email                | ✅     | token hash+TTL, un uso; e2e OK; 49/49 |
| 5 | **A4** Cabeceras de seguridad + páginas 404/500 estilizadas | ✅    | 5 headers + CSP; 404/500; 56/56 tests |
| 6 | **A5** Assets locales (Tailwind standalone + Alpine/Chart.js) | ✅  | CSS 28KB compilado + JS vendor; CSP endurecida; 62/62 |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-004 | Migración a la metodología Roadmap → Brief → Plan |
