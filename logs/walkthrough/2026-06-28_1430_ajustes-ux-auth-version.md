# Walkthrough — Ajustes: password, toasts, login unificado, versionado dual

**Fecha y hora:** 2026-06-28 14:30 | **Agente:** dev (Claude Code) | **Modelo:** claude-opus-4-8
**Tipo:** Ajustes a pedido del usuario (5 ítems).

---

## 1. Password admin reseteado
- `admin@nsskeleton.local` → contraseña **admin1234**, **usuario `admin`**. Throttle y 2FA limpios.

## 2. Toasts (mensajes flash)
- Los mensajes de confirmación/error del backend ahora aparecen como **toasts abajo a la
  derecha**, con una **barra de progreso** que se encoge hasta que el toast se esfuma
  (auto-dismiss ~4.5s) + botón de cerrar.
- El layout lee `$success`/`$error` y los pasa a un componente Alpine `toasts()`; barra vía
  CSS `@keyframes toastbar` en `admin.css`.
- Se **quitaron 31 bloques flash inline** de las vistas del backend (script) y del
  **generador** (`make-module.php`), para que no se dupliquen. Login/forgot/reset/2FA/errores
  (que usan otro layout) conservan su error inline.

## 3. Login dual (usuario o email)
- `Auth::verifyCredentials`/`attempt` matchean por **email OR username**.
- `AuthController` lee el campo `login` (acepta ambos); la vista de login dice "Usuario o email".

## 4. Login único + tipo de usuario
- Migración: `username` (único) + `user_type` en `admin_users`.
- `config/user_types.php` + `App\Services\UserTypes` (registro extensible: admin, cliente,
  depósito, vendedor…). El mismo login sirve para todos; `user_type` identifica la clase de
  usuario (`role` sigue definiendo permisos). Form y listado de usuarios muestran ambos.

## 5. Versionado dual (core + app)
- `App\Services\Version`: `core()` = `VERSION` (nsSkeleton) · `app()` = settings `app.version`
  > `APP_VERSION` (.env) > `1.0.0`.
- Visible en: **footer** ("App vX · core nsSkeleton vY"), **/health** (`app_version` + `core`),
  panel **Estado**, y **campo editable** en Configuración → General.

## Verificación
- `php -l`: 54 vistas/generador OK. **Suite 228/228**.
- **E2E**: login con `admin` y con email → 200; toast tras guardar Configuración
  (`{"type":"success","msg":"Configuración guardada."}`) + barra, sin inline; footer 2
  versiones; editar app_version=1.2.0 → footer y /health lo reflejan; users index con
  columnas Usuario/Tipo. (Estado de prueba de flags restaurado.)

## Archivos
- `Core/Auth.php`, `Controllers/Admin/{AuthController,AdminUserController,SettingsController}.php`,
  `Services/{UserTypes,Version}.php`, `config/{user_types}.php`, `Views/layouts/admin.php`,
  `Views/admin/{login,users/*,settings,health/index}.php`, `public/assets/css/admin.css`,
  migración `add_username_usertype_to_users.sql`, `console/make-module.php`.
