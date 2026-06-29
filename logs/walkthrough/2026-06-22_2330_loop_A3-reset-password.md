# Walkthrough — Fase A3: recuperación de contraseña por email

**Fecha y hora:** 2026-06-22 23:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S2 (v1.1) | **Versión:** 1.0.0 → 1.1

---

## Resumen ejecutivo
Se agregó el flujo "olvidé mi contraseña": el admin recibe por email un enlace con un
token seguro (hash + expiración + un solo uso) para fijar una nueva contraseña.
Verificado end-to-end, incluido el rechazo de reutilización del token.

## Cambios realizados
- **Migración**: `20260622_0007_create_password_resets.sql` (guarda el HASH del token).
- **`PasswordReset`** (`App\Services`): `createToken` (TTL 1h), `valid` (constant-time),
  `consume` (aplica nueva contraseña + invalida token). No revela si el email existe.
- **`ForgotPasswordController`** (público): `showForgot`/`sendReset`/`showReset`/`doReset`.
  Envía el enlace con `Mailer`; en `APP_DEBUG` muestra el enlace en pantalla (dev).
- **Vistas** `admin/forgot` y `admin/reset` + enlace "¿Olvidaste tu contraseña?" en login
  + mensajes de éxito en el login.
- **Rutas** `/admin/forgot` y `/admin/reset` (GET/POST).

## Verificación
- `php -l` OK · migración 0007 aplicada.
- **Suite**: `php tests/run.php` → **49/49 PASS** (+3 feature de `PasswordReset`:
  crear/validar/consumir/invalidar, token expirado, email inexistente).
- **E2E (servidor + MySQL 3307)**:
  1. POST `/admin/forgot` → 302, mensaje neutral.
  2. Enlace abierto → form de nueva contraseña (200).
  3. POST `/admin/reset` → 302 a `/admin/login` (éxito).
  4. Login con la contraseña reseteada → `/admin` (OK).
  5. **Reutilizar el token → rechazado** (redirige a `/admin/forgot`).

## Decisiones de diseño
- Se persiste solo el hash del token (sha256); el token en claro solo viaja por email.
- Mensaje neutral en "forgot" para evitar enumeración de usuarios.
- Enlace absoluto construido desde `APP_URL` (independiente del subdirectorio).
- Dev-aid: el enlace se muestra en pantalla solo con `APP_DEBUG=true`.

## Pendientes / follow-ups
- **A4** Cabeceras de seguridad + páginas 404/500 estilizadas — siguiente.

## Referencias
- `system/app/Services/PasswordReset.php`, `system/app/Controllers/Admin/ForgotPasswordController.php`,
  `tests/feature/PasswordResetTest.php`.
