---
code: RM-017
title: DRY, DX y comodidad (P2)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-07-09
version: v1.18
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-017 — DRY, DX y comodidad (P2)

> Migrado de `docs/roadmap.md` (sección «v1.18 — DRY, DX y comodidad (P2) · ✅ COMPLETA (2026-07-09)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Versión del core de nsSkeleton (ver detalle en `docs/CHANGELOG.md`).

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **v1.18** · fecha de entrega: **2026-07-09**.

## Alcance macro (funcionalidades entregadas)
| # | Feature | Estado | Notas |
|---|---------|--------|-------|
| 1 | `Core\Http` client (1.10) | ✅ | `Http` con transporte inyectable + `get`/`request`/retries; 7 tests |
| 2 | `Core\Webhooks\SignatureVerifier` entrantes (1.10) | ✅ | HMAC simple + timestamped (Stripe/MP) anti-replay; 6 tests |
| 3 | `db:doctor` | ✅ | `DbDoctor` + `console/db.php`; detecta caching_sha2_password; 4 tests |
| 4 | Migración remota protegida por token | ✅ | `/_migrate` token-gated (404 por defecto); `RemoteMigrate`; 4 tests |
| 5 | `git init` en el installer | ✅ | `Installer::gitInit` + `install.php --git` + recordatorio; 4 tests |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-017 | Migración a la metodología Roadmap → Brief → Plan |
