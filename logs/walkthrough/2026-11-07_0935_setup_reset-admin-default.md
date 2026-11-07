# Walkthrough — Reset de admin a credenciales default

Fecha y hora: 2026-11-07 09:35 | Agente: setup | Modelo: claude-opus-5-5

## Pedido
Resetear usuario y contraseña del admin al default del sistema.

## Qué se hizo
- Default tomado de `system/database/seed.php`: `admin@nsskeleton.local` / `admin1234`.
- `UPDATE admin_users` sobre **id=1** (BD local): username=`admin`, email=`admin@nsskeleton.local`,
  password=`Auth::hash('admin1234')`, role=`superadmin`, active=1, totp_enabled=0.
- Verificado con `password_verify` → OK.
- `login_attempts`: sin bloqueos sobre `admin`; no se tocó.
- El usuario id=83 (`admin@nsskeleton.local2`) quedó intacto.

## Archivos del repo modificados
Ninguno (solo datos en BD local + este log).
