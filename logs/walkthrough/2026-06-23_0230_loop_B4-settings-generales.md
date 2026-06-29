# Walkthrough — Fase B4: settings generales por panel

**Fecha y hora:** 2026-06-23 02:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S3 (v1.2) | **Versión:** 1.1 → 1.2

---

## Resumen ejecutivo
Se agregó una pantalla de configuración general (nombre del sistema, tagline, zona
horaria y logo) que aplica al instante en el branding y el kernel. Verificado e2e.

## Cambios realizados
- **`App\Services\AppSettings`**: `name/tagline/timezone/logo`, leen del grupo `app` de
  settings con fallback a `.env`; resilientes si no hay base.
- **`Admin\SettingsController`** + vista `admin/settings`: edita nombre/tagline/TZ y sube
  logo (png/jpg/webp/svg, máx 1 MB; opción "quitar logo").
- **Kernel** (`App`): aplica la zona horaria de settings (con fallback a config/.env).
- **Branding**: layouts (admin/auth), login y home usan `AppSettings::name()`; el sidebar
  muestra el logo si está cargado.
- **Menú**: ítem "Configuración"; `.gitignore` del logo subido + `.gitkeep`.

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **85/85 PASS** (+3 unit fallback, +2 feature set/get).
  - Nota: un test inicial dependía de la caché en-proceso de `Settings` tras un DELETE
    crudo; se corrigió para restaurar vía `Settings::set` (invalidación correcta). En
    requests web no aplica (cada request es fresco).
- **Smoke (servidor + MySQL 3307)**:
  - Menú muestra "Configuración"; `/admin/settings` 200 con form (nombre + select TZ).
  - Guardar nombre "Distribuidora Sur" → el layout lo muestra; revertir a "nsSkeleton" OK.

## Decisiones de diseño
- Settings de app en el grupo `app`; `AppSettings` centraliza el acceso con fallback.
- Logo único en `system/public/assets/branding/logo.<ext>` (servible), referenciado por
  setting `app.logo`; no se versiona el archivo subido.

## Pendientes / follow-ups
- **B5** Auditoría de acciones de admin + visor — siguiente.

## Referencias
- `system/app/Services/AppSettings.php`, `system/app/Controllers/Admin/SettingsController.php`,
  `tests/feature/AppSettingsTest.php`.
