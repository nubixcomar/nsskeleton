# Walkthrough — Fase I4: sistema de avisos/alertas (cierra Track I / v1.9.0)

**Fecha y hora:** 2026-06-27 13:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S10 (v1.9) | **Versión:** 1.8 → **1.9.0** · **Idea de:** nsCentral (impulso)

---

## Resumen ejecutivo
Se agregó un sistema de alertas computadas con patrón de providers y un widget en el
dashboard que las muestra ordenadas por severidad.

## Cambios de I4
- **`App\Alerts\AlertProvider`** (contrato) + **`App\Services\AlertService`** (recolecta de
  todos los providers registrados, ordena danger > warning > info, best-effort).
- **4 providers genéricos**: `FailedJobsAlertProvider` (jobs fallidos, danger),
  `FailedCronAlertProvider` (cron con error, warning), `PendingQueueAlertProvider` (cola
  saturada ≥20, warning), `OldBackupAlertProvider` (sin backup o > 7 días, warning).
- **`config/alert_providers.php`** (registry — agregar provider = clase + 1 línea).
- **Dashboard**: `DashboardController` pasa `alerts`; el view renderiza un widget con estilo
  por severidad y link a la sección.

## Verificación
- **Lint global**: 249 archivos PHP OK.
- **Suite**: **228/228 PASS** (+3 unit `AlertService` orden/registry, +1 feature
  `AlertProviders`: job failed → alerta danger e incluida en `AlertService::all`).
- **E2E (MySQL 3307)**: inyecté un job `failed` → el dashboard muestra "job(s) fallidos en la
  cola" con estilo **danger** (rojo). Limpiado.

## Track I — COMPLETO → Release 1.9.0
- I1 Anthropic ✅ · I2 Cronmaster v2 ✅ · I3 links públicos ✅ · I4 avisos/alertas ✅.
- `VERSION`=1.9.0, `CHANGELOG`, paquete `nsSkeleton-1.9.0.zip` (473 archivos), landing 1.9.0.

## Referencias
- `system/app/Alerts/*`, `system/app/Services/AlertService.php`,
  `system/config/alert_providers.php`, `tests/unit/AlertServiceTest.php`,
  `tests/feature/AlertProvidersTest.php`.
