# Walkthrough — Fase C5: file manager mejorado

**Fecha y hora:** 2026-06-23 06:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S4 (v1.3) | **Versión:** 1.2 → 1.3

---

## Resumen ejecutivo
Se reforzó el file manager: validación de uploads (extensiones + tamaño), renombrar y
mover (con anti path-traversal), miniaturas de imágenes y guard por permiso.

## Cambios realizados
- **`config/files.php`**: `max_upload_bytes` (5 MB) + whitelist de extensiones.
- **`FileManager`**: `upload` valida extensión y tamaño; nuevos `rename`, `move`
  (no permite mover una carpeta dentro de sí misma; resuelve dentro de la raíz) y
  `isImage`.
- **`FilesController`**: guard `files.manage`; `raw` (stream inline con `nosniff` para
  previews), `rename`, `move`.
- **Vista**: miniatura para imágenes (vía `/admin/files/raw`) + botones Renombrar / Mover
  (prompt JS) en archivos y carpetas.
- **Rutas**: `/admin/files/raw`, `/rename`, `/move`.

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **118/118 PASS** (+9 unit `FileManagerAdvanced`:
  rechazo de `.php` y de archivos grandes, aceptación de `.txt`, rename, move a
  subcarpeta, no-mover-en-sí-misma, neutralización de traversal, `isImage`).
- **Smoke (servidor + sesión)**:
  - `/admin/files` 200; los botones Renombrar/Mover renderizan.
  - Upload de `.php` por web → rechazado (flash "no permitida").
  - `/admin/files/raw?path=../../.env` → **404** (anti-traversal).
  - Carpeta demo del smoke creada y eliminada.

## Decisiones de diseño
- Toda la seguridad de paths sigue centralizada en `FileManager` (resolve/within).
- Preview por endpoint `raw` con `Content-Disposition: inline` + `X-Content-Type-Options`.
- Rename/move con prompts JS (simple) y validación server-side real.

## Pendientes / follow-ups
- **C6** IA: librería de prompts + system prompt + streaming — cierra la v1.3.

## Referencias
- `system/app/Services/FileManager.php`, `system/app/Controllers/Admin/FilesController.php`,
  `tests/unit/FileManagerAdvancedTest.php`.
