# Walkthrough — Iteración 2: núcleo MVC del sistema base

**Fecha y hora:** 2026-06-22 16:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S1 | **Versión:** 0.1.0

---

## Resumen ejecutivo
Se implementó el micro-framework MVC propio (PHP 8.2, sin dependencias) que sostiene
el sistema base, con front controller, enrutamiento, capa de datos PDO, sesión/CSRF,
autenticación y vistas con layout Tailwind/Alpine. Verificado end-to-end con el
servidor PHP integrado.

## Cambios realizados
- **Núcleo** (`system/app/Core/`): `autoload.php`, `Env`, `App`, `Router`, `Request`,
  `Response`, `Session`, `Database`, `Model`, `Controller`, `View`, `Auth` + `README.md`.
- **Front controller**: `system/public/index.php` + `system/public/.htaccess`.
- **Config**: `system/config/app.php`, `database.php`, `routes.php`.
- **Ejemplo funcional**: `HomeController`, vista `home.php`, layout `layouts/app.php`,
  ruta `/health`.
- **Seguridad**: `.htaccess` deny en `app/`, `config/`, `database/`, `storage/`, y
  raíz protegiendo `.env`/dotfiles.

## Verificación (servidor PHP integrado)
- `php -l` sobre todos los `.php` → sin errores de sintaxis.
- `GET /health` → 200 `{"status":"ok",...}`.
- `GET /` → 200 HTML con layout renderizado.
- `GET /noexiste` → 404.

## Decisiones de diseño
- Autoloader propio (sin Composer) con prefijos `Core\` y `App\`.
- Router con parámetros `{param}`; handlers como `Controller@metodo`, array o closure.
- Request normaliza el subdirectorio (funciona en `htdocs/skeleton/system/public`).
- Vistas con Tailwind/Alpine vía CDN para arrancar sin build (en prod: binario
  standalone de Tailwind, ver `docs/stack.md`).
- `Auth` apunta a `admin_users` (la tabla se crea en la iteración 3).

## Pendientes / follow-ups
- **Iteración 3**: módulo de login admin + gestión de perfiles (migración
  `admin_users`, AuthController, middleware de sesión, CRUD de perfiles, vistas).
- Reemplazar Tailwind CDN por build standalone antes de producción.

## Referencias
- `system/app/Core/README.md`, `system/config/routes.php`, `docs/architecture.md`.
