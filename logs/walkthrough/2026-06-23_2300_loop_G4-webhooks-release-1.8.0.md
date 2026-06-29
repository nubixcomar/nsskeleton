# Walkthrough — Fase G4: webhooks salientes (cierra Track G / v1.8.0 / los 13 ítems)

**Fecha y hora:** 2026-06-23 23:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S9 (v1.8) | **Versión:** 1.7 → **1.8.0**

---

## Resumen ejecutivo
Se agregaron webhooks salientes: eventos del sistema se entregan a URLs externas con POST
firmado (HMAC), usando la cola de jobs (G3) para reintentos. Con esto se completan los 13
ítems del roadmap E/F/G.

## Cambios de G4
- **`Http::postRaw`**: POST de cuerpo crudo con headers (firma exacta).
- **Migración** `webhooks` (event, url, secret, active).
- **`App\Services\Webhook`**: `subscribe`/`all`/`toggle`/`delete`, `sign` (HMAC-SHA256) y
  `dispatch(event, payload)` que encola una entrega por webhook activo.
- **`App\Jobs\WebhookDeliverJob`** (handler `webhook:deliver`): POST firmado; lanza excepción
  si la respuesta no es 2xx → la cola reintenta.
- **`WebhookController`** (`/admin/webhooks`): alta (evento+URL), activar/desactivar,
  eliminar, "ping de prueba" + ítem de menú.
- **Evento real**: al crear un administrador se dispara `admin.created`.

## Verificación
- **Lint global**: 233 archivos PHP OK.
- **Suite**: **207/207 PASS** (+3: `sign` HMAC, firmas distintas, `dispatch` encola solo a
  los activos).
- **E2E (MySQL 3307 + receptor local)**: suscribí un webhook a un sink → `dispatch` encoló →
  el worker entregó el POST → el sink lo recibió con **firma HMAC válida** (recomputada) → el
  job quedó `done`.

## Track G — COMPLETO → Release 1.8.0
- G1 API+ ✅ · G2 OpenAPI ✅ · G3 cola de jobs ✅ · G4 webhooks ✅.
- `VERSION`=1.8.0, `CHANGELOG`, paquete `nsSkeleton-1.8.0.zip` (451 archivos), landing 1.8.0.

## Roadmap E/F/G — COMPLETO (13/13)
- v1.6 (E1-E5) ✅ · v1.7 (F1-F4) ✅ · v1.8 (G1-G4) ✅. **207 tests** verdes en total.

## Referencias
- `system/app/Services/Webhook.php`, `system/app/Jobs/WebhookDeliverJob.php`,
  `system/app/Controllers/Admin/WebhookController.php`, `tests/unit/WebhookTest.php`.
