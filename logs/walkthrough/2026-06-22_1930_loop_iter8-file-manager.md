# Walkthrough — Iteración 8: file manager

**Fecha y hora:** 2026-06-22 19:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S1 | **Versión:** 0.1.0

---

## Resumen ejecutivo
Se implementó el file manager interno: navegación de carpetas/subcarpetas, subida de
archivos, creación/borrado de carpetas (recursivo), descarga y eliminación de archivos,
todo acotado a `system/storage/uploads` con protección anti path-traversal en tres
capas. Verificado por completo con 13 pruebas reales (no requiere DB).

## Cambios realizados
- **FileManager** (`App\Services\FileManager`): `root`, `normalizeRel`, `safeDir`,
  `resolve`, `within`, `cleanName`, `list`, `breadcrumb`, `upload`, `makeDir`, `delete`
  (recursivo), `uniquePath`.
- **Controlador**: `Admin\FilesController` (index con ?path, upload, mkdir, delete,
  download streaming).
- **Vista** `admin/files/index`: breadcrumb navegable, formularios de subida y nueva
  carpeta, listado de carpetas/archivos con acciones.
- **Rutas + menú**: registradas; ítem "Archivos" activado.

## Verificación (sin DB, real)
- `php -l` en todo `system/` → sin errores.
- **FileManager: 13/13 tests** — crear carpeta y subcarpeta, subir archivo, listar root
  y subcarpeta, resolve, breadcrumb (3 niveles), y **anti-traversal**: `normalizeRel`,
  `safeDir`, `resolve` y `cleanName` rechazan `..`; borrado recursivo de carpeta.
- `GET /admin/files` sin sesión → 302 a login; el intento de descarga con traversal
  también redirige (guard) antes de tocar nada.

## Decisiones de diseño
- Toda la seguridad vive en el servicio: cada operación resuelve rutas y verifica
  `within(root)` (anti-traversal). Nombres sanitizados con `cleanName`.
- `path` se pasa como query/oculto (relativo a la raíz de uploads), nunca rutas absolutas.
- Colisiones de nombre al subir se resuelven con sufijo `_n`.
- `upload` usa `move_uploaded_file` con fallback a `rename` (tmp_name siempre lo provee PHP).

## Pendientes / follow-ups
- **Iteración 9**: conector de IA (OpenAI/Deepseek) con credenciales API.
- A futuro: límite de tamaño/whitelist de extensiones configurable.

## Referencias
- `system/app/Services/FileManager.php`, `system/app/Controllers/Admin/FilesController.php`,
  `system/app/Views/admin/files/index.php`.
