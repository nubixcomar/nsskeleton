# Walkthrough — BR-001 + PL-002 (hardening del core 1.20.3)

**Fecha y hora:** 2026-11-08 09:45 | **Agente:** plan-architect | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** core 1.20.2 → 1.20.3 (planificado) | **Trazas:** BR-001 · PL-002 (sin RM, excepción)

---

## Resumen ejecutivo
Por pedido del humano, el informe de nsWMS (artifact 2LkSg7ob2wpsJctW8P3DSW) quedó como brief
**BR-001** (✅ Listo, transcripto en el anexo A) y de ahí se armó el plan **PL-002** (🔍 En revisión,
10 fases). Sin RM: es hardening, no una funcionalidad visible (decisión del humano). Sin código.

## Cambios realizados
- `planning/briefs/BR-001-hallazgos-core-nswms-1-20-2.md` (nuevo).
- `planning/plans/PL-002-hardening-core-1-20-3.md` (nuevo).
- `planning/briefs/INDEX.md`, `planning/plans/INDEX.md` (regenerados con `planning.php reindex`).

## Decisiones de diseño (detalle en PL-002 §5)
- Permiso declarado por controlador (`permission()`) con default `modules.manage` configurable
  (`security.admin_default_permission`), en vez del deny estricto del informe (rompería los módulos
  generados de los derivados existentes).
- `Guard::check()` queda puro (GuardTest); la validación de usuario activo va en `user()` + `AdminController`.
- `--check` en modo proyecto = solo lo del lock (opción B).
- Hallazgos nuevos al planificar: `modules_menu.php` es core y lo escribe `make-module` (→ app_paths);
  el updater marca conflicto aunque el core no cambie el archivo (→ skip); la galería del dashboard
  y el menú muestran Estado con `dashboard.view` (→ `logs.view`); `Rbac::config()` es privado.

## Testing / verificación
- `planning.php lint`: 0 errores, 0 warnings (sugerencias: PL-002 sin RM — excepción documentada;
  RM-022 — ajeno).

## Pendientes / follow-ups
- `/plan review PL-002` en otra sesión (recomendado) y `/plan approve PL-002` del humano.
- Ejecución: `/plan run PL-002` (loop-ready; F1 ∥ F6 ∥ F8).
- NSK-04 / NSK-05: próximo plan.
