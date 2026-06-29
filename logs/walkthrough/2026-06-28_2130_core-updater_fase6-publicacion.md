# Walkthrough — Actualización de core: Fase 6 (publicación del core)

**Fecha y hora:** 2026-06-28 21:30 | **Agente:** core-updater / release-manager | **Modelo:** claude-opus-4-8
**Sprint:** — | **Versión:** 1.14.0

---

## Resumen ejecutivo
Sexta fase: la **publicación** del paquete de core. Cierra el círculo del mecanismo de
actualización — antes podíamos *consumir* un paquete (Fase 4) pero no había paso que lo
*produjera*. Ahora `/release` regenera el lock y empaqueta el core, y el actualizador puede
bajarlo por URL. Flujo end-to-end: **publicar → distribuir → actualizar**.

## Cambios realizados
- **`landing/build-core-package.php`** (nuevo): arma el zip de CORE empaquetando exactamente los
  archivos de `core-lock.json` + el propio lock en la raíz → `nsSkeleton-core-<version>.zip`.
  Opciones `--regen` (regenera el lock antes) y `--out=<dir>` (para testear sin tocar downloads).
  Avisa y falla si el lock está desfasado (archivos faltantes).
- **`/release` integrado**: el comando `release.md` y el skill `release-manager` ahora, en el
  paso de empaquetado, regeneran `core-lock.json` (`--check` sin drift) y generan **dos**
  paquetes: completo (`build-download.php`) y de core (`build-core-package.php --regen`).
- **Updater `--url`**: `core-update.php` acepta `--url=<zip>` y descarga el paquete (cURL) a
  `storage/cache/` antes de aplicarlo. Así el update puede tirar del landing directamente.

## Decisiones de diseño
- **El paquete de core se deriva del lock**, no de un listado aparte: una sola fuente de verdad.
  Como el lock excluye lo de la app, el zip de core nunca arrastra contenido del proyecto.
- **Sin prefijo de versión** dentro del zip de core: `core-lock.json` queda en la raíz, que es
  donde el actualizador lo busca.
- **Dos paquetes, dos propósitos**: el completo sirve para arrancar un proyecto nuevo; el de
  core, para actualizar uno existente sin pisar la app.

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| landing/build-core-package.php | nuevo (empaquetador de core) |
| system/console/core-update.php | opción --url (descarga del paquete) |
| agentic/commands/release.md | paso de empaquetado: lock + 2 paquetes |
| agentic/skills/release-manager/SKILL.md | idem |
| tests/unit/CorePackageTest.php | nuevo (empaqueta a temp y verifica contenido) |
| core-manifest.json | core_version 1.14.0 |
| core-lock.json | regen |
| VERSION, docs/CHANGELOG.md | bump 1.14.0 |

## Testing / verificación
- `php -l` OK en packager y CLI.
- Packager a carpeta temporal: el zip contiene `core-lock.json` y archivos del core, y NO
  contiene `app-agentic/` ni `.env` (verificado por test e inspección).
- E2E del ciclo: empaquetar core actual → `core-update.php --source=<zip>` en dry-run contra el
  propio proyecto → "Nada para actualizar" (el paquete coincide con lo instalado).
- Suite completa: ver corrida (objetivo verde, incluyendo `--check` del lock).

## Pendientes / follow-ups
- **Fase 5** (única pendiente del roadmap): comando `/actualizar-core` + agente `core-updater`
  (interpreta el reporte de conflictos y asiste el merge de la capa agéntica) + `docs/CORE-UPDATE.md`.
- (Opcional) publicar un índice de versiones (latest.json) para que `--url` resuelva "la última".

## Referencias
- `landing/build-core-package.php`, `system/console/core-update.php`, `agentic/commands/release.md`.
