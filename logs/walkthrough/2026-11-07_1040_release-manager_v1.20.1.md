# Walkthrough — Release v1.20.1 (saltos de línea LF)

**Fecha y hora:** 2026-11-07 10:40 | **Agente:** release-manager | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** 1.20.1 | **Trazas:** —

---

## Resumen ejecutivo
Patch sobre 1.20.0: se agrega `.gitattributes` para fijar LF. Sin él, un clon por git en Windows
con `core.autocrlf=true` convertía los archivos a CRLF, los sha256 dejaban de coincidir con
`core-lock.json` y el actualizador veía todo el core como editado por el proyecto.

## Cambios realizados
- `.gitattributes` nuevo: `* text=auto eol=lf`, binarios marcados, `.bat`/`.cmd` en CRLF.
- `core-manifest.json`: `.gitattributes` en `core_paths`; `core_version` 1.20.1.
- `VERSION` 1.20.1, `docs/CHANGELOG.md` [1.20.1], `README.md`.
- `git add --renormalize .`: sin cambios (el índice ya estaba todo en LF).

## Testing / verificación
- `php tests/run.php`: PASS 446 · FAIL 0 · SKIP 1 (test nuevo `LineEndingsTest`). Lock sin drift (512 archivos core).
- Hallazgo: 6 archivos del core en CRLF en el disco del master (lock ≠ git). Normalizados con `git checkout`.
- Paquete de core 1.20.1 incluye `.gitattributes`; `latest.json` → 1.20.1 (3 releases).
- Clon real con `core.autocrlf=true` + `core-manifest.php --check`: ver línea de log.

## Informes / salidas
- `landing/downloads/nsSkeleton-1.20.1.zip`, `nsSkeleton-core-1.20.1.zip`, `latest.json`.

## Pendientes / follow-ups
- Push a GitHub (bloqueado esperando credenciales de Git Credential Manager).
- Subir zips + `latest.json` al landing.
