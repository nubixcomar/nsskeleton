# Walkthrough — Release v1.20.2 (comentarios inline en .env)

**Fecha y hora:** 2026-11-07 11:20 | **Agente:** release-manager | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** 1.20.2 | **Trazas:** —

---

## Resumen ejecutivo
Patch: `Core\Env` no quitaba los comentarios al final de línea de `.env.example`, así que en
todo proyecto derivado `APP_ENV`, `APP_DEBUG`, `SESSION_LIFETIME`, `MAIL_ENCRYPTION`,
`AI_PROVIDER`, `AGENTIC_TARGET` y `GIT_TOKEN` quedaban con el comentario dentro del valor.
Detectado al crear nsWMS (walkthrough `2026-11-07_1105_installer_nuevo-proyecto-nswms.md`).

## Cambios realizados
- `system/app/Core/Env.php`: `parseValue()` (semántica dotenv) usado por `load()`.
- `tests/unit/EnvTest.php` (6 tests, incluye recorrer todo `.env.example`).
- `VERSION`/`core-manifest.json` 1.20.2, `docs/CHANGELOG.md`, `README.md`.

## Decisiones de diseño
- `#` sin espacio delante se conserva (contraseñas/URLs con ancla no se rompen); entre comillas
  el `#` es literal. Riesgo residual documentado: una contraseña sin comillas con ` #`.

## Testing / verificación
- Suite: PASS 452 · FAIL 0 · SKIP 1; lock sin drift (513); sin archivos en CRLF.
- Parser nuevo sobre el `.env` real de nsWMS: valores correctos.

## Informes / salidas
- `landing/downloads/nsSkeleton-core-1.20.2.zip`, `nsSkeleton-1.20.2.zip`, `latest.json` (→ 1.20.2).

## Pendientes / follow-ups
- Llevar 1.20.2 a nsWMS con `core-update.php` (primer update real con el actualizador nuevo).
- Subir zips + `latest.json` al landing.
