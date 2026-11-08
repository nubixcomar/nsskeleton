# Walkthrough — Revisión de PL-002

**Fecha y hora:** 2026-11-08 10:30 | **Agente:** plan-architect (+ revisor independiente) | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** core 1.20.2 → 1.20.3 (planificado) | **Trazas:** BR-001 · PL-002

---

## Resumen ejecutivo
`/plan review PL-002`. La revisión la hizo un agente independiente (sin las conclusiones del autor),
contrastando cada fase con el código del master y de nsWMS. Veredicto: **aprobable con cambios**,
16 hallazgos (1 bloqueante, 7 importantes, 8 menores). Todos incorporados al plan. El humano decidió
el único punto abierto: **dashboard abierto con widgets filtrados por permiso**. Sin código.

## Cambios realizados
- `planning/plans/PL-002-hardening-core-1-20-3.md`: reescrito con los 16 cambios — reglas generales
  (regenerar lock por fase, prohibido construir controladores admin en tests, suite completa, fases
  secuenciales), §3 corregido, `permission()` → `requiredPermission()`, §4.2 nuevo (dashboard con
  widgets filtrados + `Dashboard::visibleBlocks`), Guard sin cache, F3 reescrita con test propio, F4
  con aserción exacta, F5 con clase de prueba con nombre + `Router::$index` + aserción de catálogo solo
  para el core + caso borde de config, F7 con skip visible y aserción de CS7, F8 con `rollbackAcross`,
  dirs temporales y desempate por orden de aplicación, §13 con la fila de revisión.
- `planning/briefs/BR-001-…md`: casos borde 2 y 12, decisión del dashboard (§19), ronda 2 de entrevista.
- Índices al día (`planning.php reindex`).

## Hallazgos verificados en persona
- `Router` guarda el path en `$index` (`"GET /admin/x" => pos`), no en `$routes`.
- `LogViewerController` pide el permiso en `index()`, no en el constructor.
- `DashboardController` pasa `Health::full()` a cualquier usuario y `dashboard.php:23-24` tiene botones fijos a Estado/Buscar.

## Testing / verificación
- `planning.php lint`: 0 errores, 0 warnings (sugerencias: PL-002 sin RM = excepción documentada; RM-022 ajeno).
- Chequeos del plan: 0 menciones de `permission()` suelto; `new class` solo en la regla que lo prohíbe;
  F8 sin escritura en `system/database/migrations/`; 10 fases, §12 secuencial.

## Pendientes / follow-ups
- `/plan approve PL-002` (humano) → `/plan run PL-002`.
