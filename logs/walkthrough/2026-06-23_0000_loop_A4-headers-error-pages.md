# Walkthrough — Fase A4: cabeceras de seguridad + páginas de error

**Fecha y hora:** 2026-06-23 00:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S2 (v1.1) | **Versión:** 1.0.0 → 1.1

---

## Resumen ejecutivo
Todas las respuestas ahora llevan cabeceras de seguridad (incluida CSP), y los errores
404/500 se muestran con páginas estilizadas. Verificado con tests y smoke real.

## Cambios realizados
- **`Core\Security`**: `headers()` (nosniff, X-Frame-Options SAMEORIGIN, Referrer-Policy,
  Permissions-Policy, CSP; HSTS solo en HTTPS) + `isHttps()`.
- **`App` kernel**: aplica las cabeceras a toda respuesta (éxito o excepción) y renderiza
  `errors/500` (con detalle solo en `APP_DEBUG`).
- **`Router`**: renderiza `errors/404` cuando la vista existe.
- **Vistas**: `layouts/error`, `errors/404`, `errors/500`.

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **56/56 PASS** (+7: cabeceras, presencia de CSP/HSTS,
  render de 404/500, ocultar trace sin debug).
- **Smoke (servidor real)**: `/health` devuelve las 5 cabeceras de seguridad; una ruta
  inexistente → 404 con la página estilizada ("Volver al inicio").

## Decisiones de diseño
- CSP permisiva con los CDN actuales (Tailwind/Alpine/Chart.js) — se endurecerá en **A5**
  cuando los assets sean locales (ahí se podrá sacar `unsafe-eval` y los hosts externos).
- Páginas de error con layout propio sin dependencias de sesión/DB (no fallan en error).
- Detalle del 500 visible solo con `APP_DEBUG=true`.

## Pendientes / follow-ups
- **A5** Assets locales (Tailwind standalone + Alpine/Chart.js) → cerrar v1.1 y endurecer CSP.

## Referencias
- `system/app/Core/Security.php`, `system/app/Core/App.php`, `system/app/Views/errors/`.
