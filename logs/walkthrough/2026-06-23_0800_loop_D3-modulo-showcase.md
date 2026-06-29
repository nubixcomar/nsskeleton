# Walkthrough — Fase D3: módulo showcase (Clientes)

**Fecha y hora:** 2026-06-23 08:00 | **Agente:** loop/module-generator (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S5 (v1.4) | **Versión:** 1.3 → 1.4

---

## Resumen ejecutivo
Se generó un módulo showcase completo ("Clientes") con el generador B1, funcional por
web y por API, documentado. Demuestra el flujo end-to-end del acelerador de módulos.

## Cambios realizados
- **Generador**: el stamp de las migraciones pasó a `date('Ymd_His')` (orden único).
- **Módulo Clientes** (generado): migración, modelo, controlador CRUD, vistas (con
  búsqueda+paginación), rutas, ítem de menú.
- **API**: `clientes` registrado en `config/api.php`.
- **Doc**: `docs/modules/clientes.md` (cómo se generó, qué incluye, cómo extender).

## Verificación
- `php -l` OK · migración aplicada.
- **Suite**: `php tests/run.php` → **133/133 PASS** (+3 feature `ShowcaseModule`: CRUD del
  modelo, registro en API, presencia en el menú).
- **Smoke e2e (servidor + MySQL 3307)**:
  - Web: menú muestra "Cliente"; `/admin/clientes` 200 con búsqueda; crear → aparece.
  - API: `GET /api/clientes` 200 (devuelve el cliente); `POST` crea; `DELETE` 200.
  - Token y cliente de prueba limpiados al final.

## Decisiones de diseño
- "Clientes" elegido por su relevancia para los sistemas reales del usuario
  (facturación / WMS / stock): es la base para generar el resto de las entidades.
- El módulo queda **vivo** como showcase (no se elimina).

## Notas
- Conviven módulos demo previos (Producto, Articulo); el canónico/showcase es Clientes.
  No se eliminan automáticamente (preferencia del usuario).

## Pendientes / follow-ups
- **D4** Deploy FTP/git desde el `.env` — cierra la v1.4 y todo el roadmap.

## Referencias
- `system/app/Models/Cliente.php`, `docs/modules/clientes.md`,
  `tests/feature/ShowcaseModuleTest.php`.
