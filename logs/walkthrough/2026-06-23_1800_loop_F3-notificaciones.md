# Walkthrough — Fase F3: notificaciones in-app

**Fecha y hora:** 2026-06-23 18:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S8 (v1.7) | **Versión:** 1.7

---

## Resumen ejecutivo
Se agregó un sistema de notificaciones in-app: campanita con contador en el topbar y una
bandeja para verlas y marcarlas como leídas.

## Cambios realizados
- **`App\Services\Notifier`** (nuevo): `notify`, `notifyAll` (a todos los admins activos,
  con exclusión opcional), `unreadCount`, `forUser`, `markRead`, `markAllRead`. Best-effort.
- **Migración** `notifications` (user_id, title, body, url, read_at, índice user+read).
- **`NotificationController`** + bandeja (`admin/notifications`) con marcar leída / todas.
- **Campanita** en el topbar (Alpine dropdown) con badge de no leídas + recientes.
- **Disparador real**: al crear un administrador se notifica a todos (`notifyAll`).

## Verificación
- `php -l` OK.
- **Suite**: **190/190 PASS** (+2 feature `Notifier`: alta/conteo/markRead/markAllRead;
  markRead no cruza usuarios).
- **E2E (MySQL 3307)**: notifiqué al admin → la campanita muestra badge; la bandeja lista
  "Bienvenido"; "marcar todas como leídas" deja el contador en 0. (Notif demo limpiada.)

## Pendientes / follow-ups
- **F4** Auditoría con diff (antes/después) — cierra el Track F.

## Referencias
- `system/app/Services/Notifier.php`, `system/app/Controllers/Admin/NotificationController.php`,
  `system/app/Views/layouts/admin.php`, `tests/feature/NotifierTest.php`.
