# Walkthrough — Iteración 3: login admin + gestión de perfiles

**Fecha y hora:** 2026-06-22 17:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S1 | **Versión:** 0.1.0

---

## Resumen ejecutivo
Se implementó el backend de administración: login con sesión + CSRF, guard de rutas
protegidas, dashboard y CRUD completo de perfiles de administrador, con vistas
responsivas (Tailwind/Alpine). Incluye migración de `admin_users` y runners de
migración/seed. Render del login y guard de sesión verificados; el flujo que toca la
base quedó listo en código pero su verificación está pendiente de MySQL levantado.

## Cambios realizados
- **Migración**: `database/migrations/20260622_0001_create_admin_users.sql`.
- **Runners CLI**: `database/migrate.php` (crea DB + aplica migraciones con control en
  `schema_migrations`) y `database/seed.php` (admin por defecto) + `database/README.md`.
- **Modelo**: `App\Models\AdminUser` (count, emailTaken).
- **Controladores**: `Admin\AdminController` (guard de sesión + verifyCsrf),
  `Admin\AuthController` (login/logout), `Admin\DashboardController`,
  `Admin\AdminUserController` (CRUD con validación, hash de password, no auto-eliminación).
- **Vistas**: `layouts/auth`, `layouts/admin` (sidebar responsivo), `admin/login`,
  `admin/dashboard`, `admin/users/index`, `admin/users/form`.
- **Core**: nuevo helper `Core\Url` (links seguros en subdirectorio).
- **Rutas**: backend admin registrado en `config/routes.php`.

## Verificación
- `php -l` en todo `system/` → sin errores.
- `GET /admin/login` → 200, formulario con campo email + token CSRF.
- `GET /admin`, `/admin/users`, `/admin/users/5/edit` sin sesión → 302 a `/admin/login`
  (guard OK).
- ⚠️ `migrate.php`/`seed.php` y el POST de login NO se pudieron probar: el MySQL de
  XAMPP no estaba corriendo (conexión rechazada). No se auto-arrancó el servicio.

## Cómo completar la verificación (cuando MySQL esté arriba)
```
# 1. Iniciar MySQL en XAMPP
php system/database/migrate.php
php system/database/seed.php
# 2. Login en /admin/login con admin@nsskeleton.local / admin1234
```

## Decisiones de diseño
- Guard por herencia: controladores protegidos extienden `AdminController` (su
  constructor exige sesión y redirige). `AuthController` no lo extiende.
- CSRF en todos los formularios (login, logout, CRUD).
- Passwords con `password_hash` (Auth::hash); el modelo nunca expone el hash en lecturas
  de Auth::user().

## Pendientes / follow-ups
- **Iteración 4**: programador de tareas / cron (cronmaster).
- Verificar flujo DB cuando MySQL esté disponible.

## Referencias
- `system/app/Controllers/Admin/*`, `system/app/Views/admin/*`, `system/database/*`.
