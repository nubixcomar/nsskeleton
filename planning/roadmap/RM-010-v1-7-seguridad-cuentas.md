---
code: RM-010
title: Seguridad & cuentas (Track F)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-06-23
version: v1.7
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-010 — Seguridad & cuentas (Track F)

> Migrado de `docs/roadmap.md` (sección «v1.7 — Seguridad & cuentas (Track F) · ✅ COMPLETA (2026-06-23)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Versión del core de nsSkeleton (ver detalle en `docs/CHANGELOG.md`).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.7** · fecha de entrega: **2026-06-23**.

## Alcance macro (funcionalidades entregadas)
| # | Funcionalidad                                              | Estado | Notas |
|---|------------------------------------------------------------|--------|-------|
| 1 | **F1** 2FA / TOTP para el login admin                      | ✅     | Totp RFC6238 + challenge en login + setup/enable/disable + secreto cifrado; 185/185 |
| 2 | **F2** RBAC editable por panel + permisos por usuario      | ✅     | catálogo + override de rol (settings) + user_permissions allow/deny + matriz/tri-estado; 188/188 |
| 3 | **F3** Notificaciones in-app (campanita) + bandeja         | ✅     | Notifier + tabla + campanita/badge + bandeja + notifyAll al crear admin; 190/190 |
| 4 | **F4** Auditoría con diff (antes/después)                  | ✅     | Audit::diff/logChange + columna changes + visor con old→new; 194/194 |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-010 | Migración a la metodología Roadmap → Brief → Plan |
