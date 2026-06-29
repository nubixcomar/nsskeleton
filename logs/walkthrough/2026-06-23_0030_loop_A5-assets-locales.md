# Walkthrough — Fase A5: assets locales (cierra v1.1)

**Fecha y hora:** 2026-06-23 00:30 | **Agente:** loop/dev-web (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S2 (v1.1) | **Versión:** 1.0.0 → 1.1

---

## Resumen ejecutivo
Los assets de frontend ahora son **locales** (sin depender de CDN en runtime): Tailwind
compilado con el binario standalone (solo las clases usadas) y Alpine/Chart.js
vendorizados. La CSP se endurece en modo local (sin orígenes externos). Cierra la v1.1.

## Cambios realizados
- **Vendorizado**: `system/public/assets/js/alpine.min.js` (44 KB),
  `chart.umd.min.js` (206 KB).
- **Tailwind**: `resources/css/app.css` (`@import "tailwindcss"` + `@source` a vistas y
  landing) → compilado a `system/public/assets/css/app.css` (**28 KB**).
- **`Core\Assets`**: modo `local`/`cdn` (`ASSETS_MODE`), `head()` emite las etiquetas
  correctas, con fallback a CDN si no hay CSS local.
- **`Core\Security`**: CSP sin `cdn.*` ni hosts externos en modo local.
- **Layouts** (`app`, `admin`, `auth`, `error`): usan `Assets::head()`.
- **`index.php`**: bajo `cli-server` sirve archivos estáticos existentes (en Apache lo
  hace el `.htaccess`).
- **Pipeline**: `tools/build-css.sh` (descarga el binario si falta y compila);
  `tools/bin/` en `.gitignore` y excluido del ZIP de descarga; `resources/README.md`.
- **.env**: `ASSETS_MODE=local`.

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **62/62 PASS** (+6 de `Assets`/CSP).
- **Smoke (servidor real)**:
  - `/admin/login` referencia `/assets/css/app.css` y **ya no** `cdn.tailwindcss.com`.
  - `app.css` 200 `text/css` (28 KB); `alpine.min.js` 200; `chart.umd.min.js` 200.
  - CSP en `/health` sin referencias a `jsdelivr` en modo local.
  - Páginas siguen OK (login 200, health 200, 404 404).

## Decisiones de diseño
- Se commitea el CSS compilado y los JS vendorizados; el binario de Tailwind (112 MB)
  no se versiona (se baja con el script).
- `ASSETS_MODE=cdn` permite volver a CDN sin compilar (dev rápido).
- La landing (`landing/index.html`) sigue con CDN por ser una página estática autónoma.

## Estado
**v1.1 (Endurecimiento) COMPLETA**: A6 tests, A1 cifrado, A2 login hardening, A3 reset,
A4 headers/errores, A5 assets locales. Siguiente: **Track B (v1.2)** — empieza por el
generador de módulos CRUD (B1).

## Referencias
- `system/app/Core/Assets.php`, `resources/css/app.css`, `tools/build-css.sh`.
