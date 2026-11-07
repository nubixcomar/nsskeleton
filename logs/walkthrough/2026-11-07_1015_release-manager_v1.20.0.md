# Walkthrough — Release v1.20.0

**Fecha y hora:** 2026-11-07 10:15 | **Agente:** release-manager | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** 1.20.0 | **Trazas:** RM-020 · RM-021 · RM-022 (🟡) · PL-001

---

## Resumen ejecutivo
Se publicó la versión **1.20.0** del core de nsSkeleton desde el master (`project-role.php` →
`master`). Reúne el actualizador con merge 3-way + `latest.json` + pantalla de admin,
`/nuevo-proyecto`, manuales remotos y la metodología Roadmap → Brief → Plan. Durante el release
apareció y se corrigió una fuga en el paquete completo (sesiones y base del merge).

## Cambios realizados
- `VERSION` y `core_version` de `core-manifest.json` → `1.20.0`.
- `docs/CHANGELOG.md`: "[Sin publicar]" → `[1.20.0] — 2026-11-07` con resumen, notas de
  actualización y correcciones.
- `planning/roadmap/RM-020` y `RM-021`: `version: 1.20.0`. `docs/roadmap.md` (histórico) marcado.
  `logs/features-resume.md`: tabla "Releases posteriores". `README.md`: estado 1.20.0 + bullet v1.20.
- **Fix (paquete completo)** `landing/build-download.php`: excluía el runtime de storage salvo
  `sessions/`, y tampoco excluía `core-base/` → el zip llevaba archivos `sess_*` (datos de sesión
  de admins) y la base del merge. Ahora usa el mismo criterio que `ProjectCreator`. Nuevo
  `--out=<dir>` y test `tests/unit/DownloadPackageTest.php`.
- **Fix (test)** `tests/feature/CronMasterTest.php › deshabilitado por defecto`: dependía de que
  la DB local tuviera el cron master apagado. Ahora borra la clave dentro de la transacción de
  `itDb` (rollback); la configuración real (`cron.master_enabled = 1`) quedó intacta.

## Decisiones de diseño
- RM-022 se deja **🟡 En curso** (decisión de su dueño): `planning lint` sugiere pasarlo a
  entregado porque PL-001 está cerrado, pero el RM tiene entrega tentativa 2026-11-14. Se aclara
  en las notas que lo publicado es el helper y la metodología base.
- No se borraron los zips viejos de `landing/downloads/` (`--seed-base=remote` necesita los de
  versiones anteriores). `latest.json` indexa 1.20.0 y 1.14.0 (los únicos zips de core presentes).

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| `VERSION` · `core-manifest.json` | 1.20.0 |
| `core-lock.json` | regenerado (510 archivos core, sin drift) |
| `docs/CHANGELOG.md` · `README.md` · `docs/roadmap.md` | release |
| `planning/roadmap/RM-020-*.md` · `RM-021-*.md` | versión 1.20.0 |
| `logs/features-resume.md` | releases posteriores |
| `landing/build-download.php` | fix fuga sesiones/core-base + `--out` |
| `tests/unit/DownloadPackageTest.php` | nuevo |
| `tests/feature/CronMasterTest.php` | test independiente del estado de la DB |

## Testing / verificación
- `php tests/run.php`: **PASS 445 · FAIL 0 · SKIP 1** (antes del fix de CronMaster: 1 fallo de entorno).
- `core-manifest.php --check`: sin drift (510 archivos core).
- `planning.php lint`: 0 errores, 0 warnings, 1 sugerencia (RM-022, ver decisiones).
- Auditoría de contenido de los zips (sin `.env`, `.git/`, `planning/` raíz, `secrets/`,
  descargas, sesiones, `core-base/` ni runtime de storage salvo `.gitkeep`): ambos limpios.

## Informes / salidas (c)
- `landing/downloads/nsSkeleton-1.20.0.zip` — paquete completo (629 archivos, ~988 KB).
- `landing/downloads/nsSkeleton-core-1.20.0.zip` — paquete de core (511 archivos, ~738 KB).
- `landing/downloads/latest.json` — índice: latest 1.20.0; releases 1.20.0 y 1.14.0 con sha256.

## Pendientes / follow-ups
- **Publicar en el landing** (manual): subir `nsSkeleton-core-1.20.0.zip` + `latest.json` (y el
  paquete completo), conservando los zips anteriores. Por https.
- **Commit + tag** (no hechos, requieren OK): `git add -A && git commit` y luego
  `git tag -a v1.20.0 -m "nsSkeleton 1.20.0"` + `git push origin main --tags`.
- Instalaciones de prueba E2E del actualizador sin borrar: `C:\xampp\htdocs\nsupd-e2e{,-src,-landing}`.

## Referencias
- `docs/CHANGELOG.md` § [1.20.0]
- `logs/walkthrough/2026-11-07_0935_core-dev_actualizador-merge-latest-ui.md`
