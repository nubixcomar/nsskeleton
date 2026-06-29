# Walkthrough — Fase E1: relaciones FK en el generador

**Fecha y hora:** 2026-06-23 11:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S7 (v1.6) | **Versión:** 1.5 → 1.6

---

## Resumen ejecutivo
El generador de módulos (`/nuevo-modulo`) ahora soporta relaciones `belongsTo`: un campo
`cliente_id:fk:clientes` genera la FK en la base, un `<select>` poblado en el formulario y
muestra la etiqueta del registro relacionado en el listado.

## Cambios realizados
- **`ModuleScaffold`**: tipo `fk` (SQL `INT UNSIGNED`, input `select`) + `parseRelations()`
  (campo => tabla). `parseFields` ahora corta en 3 partes (`name:type:ref`).
- **`RelationOptions`** (nuevo servicio): `labelColumn()` (elige nombre/name/titulo/… por
  convención, con guard anti-inyección de nombre de tabla) y `forTable()` (id => etiqueta).
- **`make-module.php`**: FK + índice + `CONSTRAINT … ON DELETE SET NULL` en la migración;
  el controlador embebe `RELATIONS` y un `options()`; el form emite `<select>`; el índice
  resuelve la etiqueta; `data()` castea fk a int|null; encabezados sin sufijo `_id`.

## Verificación
- `php -l` OK.
- **Suite**: **158/158 PASS** (+4 unit `ModuleRelations`, +4 feature `RelationOptions`).
- **E2E (MySQL 3307)**: generé `Pedido` con `titulo total cliente_id:fk:clientes` →
  migración con FK aplicada; el form tiene `<select name="cliente_id">` con la opción
  "ACME SA"; creé un pedido y el índice muestra **"ACME SA"** (no el id) bajo el encabezado
  "Cliente".

## Notas
- El módulo demo `Pedido` y el cliente "ACME SA" quedan en la base (no se borran, según la
  regla de no-cleanup destructivo).

## Pendientes / follow-ups
- **E2** Validaciones por campo (requerido/email/numérico/único) — siguiente.

## Referencias
- `system/app/Services/{ModuleScaffold,RelationOptions}.php`, `system/console/make-module.php`,
  `tests/unit/ModuleRelationsTest.php`, `tests/feature/RelationOptionsTest.php`.
