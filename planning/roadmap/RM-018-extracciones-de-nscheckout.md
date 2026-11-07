---
code: RM-018
title: Extracciones de nsCheckout (sección 2 del informe)
status: ✅ Entregado
priority: P2
start_tentative: —
due_tentative: 2026-07-09
version: —
briefs: []
plans: []
owner: nubixcomar
created: 2026-11-07
updated: 2026-11-07
---

# RM-018 — Extracciones de nsCheckout (sección 2 del informe)

> Migrado de `docs/roadmap.md` (sección «Extracciones de nsCheckout (sección 2 del informe) · ✅ RESUELTAS (2026-07-09)») al adoptar la metodología RM → BR → PL.
> Iniciativa histórica: se ejecutó antes de que existieran briefs y planes codificados
> (los planes de entonces, si hubo, están en `agentic/knowledge/skeleton/` y en los walkthroughs).

## Descripción
Piezas construidas en nsCheckout evaluadas para el core. Detalle en el plan.

## Objetivo y valor
Entregado como parte del core de nsSkeleton. Versión: **—** · fecha de entrega: **2026-07-09**.

## Alcance macro (funcionalidades entregadas)
| Pieza | Resolución |
|-------|-----------|
| `Money` (multi-moneda) | ✅ extraída → `Core\Money` (8 tests) |
| `Hmac` (firma payloads) | ✅ extraída → `Core\Security\Hmac`; `SignatureVerifier` convergió (6 tests) |
| `DomainStatus` (DNS+TLS) | ✅ extraída → `App\Services\DomainStatus` (5 tests) |
| Exportadores CSV/Excel/PDF | ✅ ya en core (`App\Services\Exporter`) |
| Helper CSRF JSON/fetch | ✅ ya en core (`Session::csrf`/`verifyCsrf`) |
| `payments` (PaymentGateway) | ⏭️ plantilla `/nuevo-modulo` (app-específico); core ya aporta Http/CSP/Money/webhooks |
| `integrations` (BaseCatalogConnector) | ⏭️ ya existe framework ecommerce (`Services/Ecommerce/`, v1.10) |
| `marketing/consent` (GTM/GA4/Pixel) | ⏭️ plantilla (front/marketing, no core) |

## Dependencias
- —

## Briefs y planes vinculados
| Código | Nombre | Estado |
|--------|--------|--------|
| — | Iniciativa anterior a la metodología | — |

## Historial de re-planificación
| Fecha | Cambio (de → a) | Motivo |
|-------|-----------------|--------|
| 2026-11-07 | `docs/roadmap.md` → RM-018 | Migración a la metodología Roadmap → Brief → Plan |
