# Walkthrough — Fase B3: búsqueda + paginación reutilizable

**Fecha y hora:** 2026-06-23 02:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S3 (v1.2) | **Versión:** 1.1 → 1.2

---

## Resumen ejecutivo
Se agregó paginación + búsqueda reutilizable a los listados. Un servicio `Paginator`
(con cálculo de páginas puro y consulta con `LIKE`) + dos partials. Integrado en el
listado de perfiles y en el **generador de módulos** (los módulos nuevos ya lo traen).

## Cambios realizados
- **`App\Services\Paginator`**: `meta()` (math pura: páginas, from/to, prev/next con
  clamp) y `paginate()` (count + LIMIT/OFFSET + búsqueda `LIKE` sobre columnas).
- **Partials**: `partials/search` (form GET) y `partials/pagination` (anterior/siguiente,
  preserva el término de búsqueda en la query).
- **Perfiles**: `AdminUserController::index` y su vista usan búsqueda+paginación.
- **Generador** (`make-module.php`): el controlador usa `Paginator` (searchable = campos
  string/text) y la vista incluye los partials.

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **80/80 PASS** (+5 unit `meta`, +3 feature `paginate`).
- **Smoke (servidor + MySQL 3307)**:
  - `/admin/users` muestra la caja de búsqueda y "Mostrando … de …".
  - `?search=admin` encuentra al admin; `?search=zzznope` → "Sin resultados".
  - Un módulo generado nuevo (`Articulo`) incluye el partial de paginación y usa
    `Paginator` en el controlador (lint OK).

## Notas
- El smoke generó el módulo demo **Articulo** (tabla `articulos_tmp`). Para no dejar un
  ítem de menú roto, se **migró** y queda funcional. Ahora hay **2 módulos demo vivos**
  (Producto, Articulo) como ejemplos del generador; se pueden eliminar cuando se quiera.

## Pendientes / follow-ups
- **B4** Settings generales por panel (nombre, zona horaria, branding) — siguiente.

## Referencias
- `system/app/Services/Paginator.php`, `system/app/Views/partials/{search,pagination}.php`,
  `tests/unit/PaginatorTest.php`, `tests/feature/PaginatorTest.php`.
