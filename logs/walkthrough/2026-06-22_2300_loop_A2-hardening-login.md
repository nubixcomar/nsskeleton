# Walkthrough — Fase A2: hardening de login + mi perfil

**Fecha y hora:** 2026-06-22 23:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S2 (v1.1) | **Versión:** 1.0.0 → 1.1

---

## Resumen ejecutivo
Se endureció el login con rate-limit/lockout por intentos fallidos y se agregó "Mi
perfil" para que el admin logueado edite sus datos y cambie su contraseña. Verificado
end-to-end (cambio de contraseña y bloqueo por intentos).

## Cambios realizados
- **Migración**: `20260622_0006_create_login_attempts.sql`.
- **`LoginThrottle`** (`App\Services`): 5 intentos → bloqueo 15 min; `hit`, `clear`,
  `tooManyAttempts`, `secondsRemaining`; reset al expirar el bloqueo.
- **`AuthController::login`**: bloquea si hay demasiados intentos; `hit` en fallo;
  `clear` en éxito.
- **`ProfileController`** + vista `admin/profile`: editar nombre/email y cambiar la
  contraseña (requiere la actual; valida longitud y confirmación).
- **Rutas** `/admin/profile` (show/update/password) + link "Mi perfil" en la topbar.

## Verificación
- `php -l` OK · migración 0006 aplicada.
- **Suite**: `php tests/run.php` → **46/46 PASS** (+2 feature de `LoginThrottle`).
- **E2E (servidor real + MySQL 3307)**:
  - Login normal → `/admin`; `/admin/profile` 200.
  - **Cambio de contraseña** admin1234 → admin5678 (login OK con la nueva) → revertido a
    admin1234 (login OK). El seed queda con la clave original.
  - **Lockout**: tras 5 fallos, incluso la contraseña correcta se bloquea (→ `/admin/login`)
    y `/admin` responde 302. Throttle del admin limpiado tras la prueba.

## Decisiones de diseño
- Throttle por identificador (email normalizado), bloqueo temporal con auto-reset.
- El cambio de contraseña exige la contraseña actual (defensa ante sesión secuestrada).
- "Mi perfil" en la topbar (no en el sidebar de módulos).

## Pendientes / follow-ups
- **A3** Recuperación de contraseña por email (token + expiración) — siguiente.
- A futuro: incluir IP en la clave del throttle; captcha tras N intentos.

## Referencias
- `system/app/Services/LoginThrottle.php`, `system/app/Controllers/Admin/ProfileController.php`,
  `tests/feature/LoginThrottleTest.php`.
