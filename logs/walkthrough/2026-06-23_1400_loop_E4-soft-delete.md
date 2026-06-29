# Walkthrough — Fase E4: soft-delete + papelera

**Fecha y hora:** 2026-06-23 14:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S7 (v1.6) | **Versión:** 1.6

---

## Resumen ejecutivo
Los módulos generados ahora hacen borrado lógico: eliminar manda a la papelera, desde
donde se puede restaurar o borrar definitivamente.

## Cambios realizados
- **`Paginator`**: opción `filter` (fragmento SQL fijo) que se combina con la búsqueda con
  `AND`. Backward-compatible.
- **`make-module.php`**:
  - Migración con columna `deleted_at DATETIME NULL`.
  - `destroy()` ahora es soft (UPDATE `deleted_at = NOW()`); el índice filtra
    `deleted_at IS NULL`; el export también.
  - `trash()` (papelera), `restore()` y `forceDestroy()` (borrado real) + rutas.
  - Vista `trash.php` con Restaurar / Eliminar definitivo; link "🗑 Papelera" en el índice.

## Verificación
- `php -l` OK.
- **Suite**: **173/173 PASS** (+2 feature `PaginatorFilter`: filtro fijo y combinación
  AND con búsqueda).
- **E2E (MySQL 3307)**: módulo `Nota` → crear ("Nota viva") visible; soft-delete → sale del
  índice y aparece en papelera; restaurar → vuelve al índice; soft-delete + eliminar
  definitivo → fila realmente borrada de la tabla (COUNT=0).

## Notas
- Solo los módulos **nuevos** traen `deleted_at`; los previos siguen con borrado físico
  (no se alteran tablas existentes).

## Pendientes / follow-ups
- **E5** Búsqueda global + filtros por columna — cierra el Track E.

## Referencias
- `system/app/Services/Paginator.php`, `system/console/make-module.php`,
  `tests/feature/PaginatorFilterTest.php`.
