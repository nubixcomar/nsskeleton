# Walkthrough — Nuevo proyecto nsWMS

**Fecha y hora:** 2026-11-07 11:05 | **Agente:** installer | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** core 1.20.1 | **Trazas:** —

---

## Resumen ejecutivo
Se creó el proyecto derivado **nsWMS** con `/nuevo-proyecto` desde el master 1.20.1 (`19fe01c`,
árbol limpio, sin drift). Descripción dada por el humano: *sistema WMS para nubixstore y otras
plataformas de ecommerce, multitenant*. Falta el brief (`/instalar` en el proyecto).

## Cambios realizados
- `php system/console/new-project.php "nsWMS" --yes` (dry-run previo + confirmación del humano:
  sin remoto, sin datos demo).
- Carpeta `C:\xampp\htdocs\nswms` (539 archivos), `nsproject.json`, `.env` con APP_KEY nueva,
  base `nswms` (22 migraciones, 19 tablas, admin inicial), base del merge 512/512, `planning/`
  vacío, git `main` con commit `d317f11`, sin remoto.

## Testing / verificación
- `project-role.php` → `project`; `core-manifest.php --check` sin drift (512); `/health` OK
  (db true, core 1.20.1); login HTTP admin → 302 al dashboard → 200; `/admin/core-update` carga.

## Hallazgo (bug del core, no del proyecto)
- `Core\Env::load` no quita comentarios al final de línea y `.env.example` los usa: en el
  proyecto quedan `APP_ENV`, `APP_DEBUG`, `SESSION_LIFETIME`, `MAIL_ENCRYPTION`, `AI_PROVIDER`,
  `AGENTIC_TARGET` y `GIT_TOKEN` con el comentario dentro del valor (warning de
  `session.gc_maxlifetime` en cada request). El master no lo sufre porque su `.env` no los tiene.
  Afecta a todo derivado creado desde `.env.example`. Propuesto: fix en core (1.20.2) y llevarlo
  a nsWMS con el actualizador.

## Pendientes / follow-ups
- Fix de `Core\Env` en el master + release 1.20.2 + `/actualizar-core` en nsWMS.
- `/instalar` en nsWMS (brief: WMS multitenant, integración nubixstore + otras plataformas).
- Cambiar la contraseña del admin inicial; `CORE_UPDATE_URL` vacía (configurar cuando el landing publique latest.json).
