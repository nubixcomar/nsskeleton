# Walkthrough — Fase C4: emails con plantillas + cola

**Fecha y hora:** 2026-06-23 05:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S4 (v1.3) | **Versión:** 1.2 → 1.3

---

## Resumen ejecutivo
Se agregaron plantillas HTML de email y una cola de envío con reintentos, drenada por un
job de cron. Verificado (render + mecánica de cola).

## Cambios realizados
- **Migración**: `20260623_0010_create_email_queue.sql` (con `-- @DOWN`).
- **Plantillas**: `emails/layout.php` (shell HTML con branding) + `emails/notification.php`
  (título, cuerpo, botón opcional).
- **`Mailer`**: `render(view, data, layout)` y `queue(to, subject, html)`.
- **`EmailQueue`**: `push`, `process` (reintenta hasta 3 → `failed`), `recent`, `counts`.
- **Job** `email:queue` (cron) que drena la cola.
- **Panel**: `MailController` con guard `mail.manage` + acciones `queue`/`processQueue`,
  vista `admin/mail/queue` (estado + "procesar ahora") y link desde settings.

## Verificación
- `php -l` OK · migración 0010 aplicada.
- **Suite**: `php tests/run.php` → **109/109 PASS** (+2 unit `Mailer::render`, +2 feature
  `EmailQueue`: encolar pending; process → 3 intentos → `failed` sin SMTP).
- **Smoke**: `Mailer::render('emails/notification', …)` → HTML con título dentro del
  layout; `job:email:queue` procesa (1 con error, esperado sin SMTP); `/admin/mail/queue`
  → 200 con el estado; link "Ver cola" en settings.

## Decisiones de diseño
- La cola usa `Mailer::send` (que ya audita en `email_log`); reintentos con tope.
- Drenaje desacoplado del request: se programa `job:email:queue` en el cron.
- Plantillas como vistas PHP (consistente con el resto), con branding desde `AppSettings`.

## Pendientes / follow-ups
- **C5** File manager: extensiones/tamaño, renombrar/mover, preview — siguiente.

## Referencias
- `system/app/Services/{EmailQueue,Mailer}.php`, `system/app/Views/emails/*`,
  `tests/feature/EmailQueueTest.php`.
