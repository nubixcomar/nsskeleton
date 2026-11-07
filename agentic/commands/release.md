---
name: release
usage: /release <version> [--notes]
spawns: [release-manager]
---

## Qué hace
Publica una versión del proyecto de forma reproducible y trazable.

Sirve en los dos tipos de árbol, con alcance distinto:
- **Master del skeleton**: versión del **core** (VERSION) + paquetes que consumen los derivados.
- **Proyecto derivado**: versión de **la app** (`APP_VERSION`, changelog y tag del proyecto).
  Nunca genera paquetes de core ni regenera `core-lock.json`.

## Proceso
0. Detectar el rol: `php system/console/project-role.php` (exit 0 = `master`, 10 = `project`).
   Un derivado viejo creado clonando el repo sale como `master`: si el humano confirma que es un
   proyecto, marcarlo con `php system/console/project-role.php --mark="<nombre>"`.
1. Invoca el agente `release-manager`.
2. Verifica que la suite de tests esté verde (`/test` → `php tests/run.php`).
3. Actualiza la versión:
   - **master**: `VERSION` → `<version>` (y `core_version` de `core-manifest.json`).
   - **project**: `APP_VERSION=<version>` en `.env` y `.env.example` del proyecto. **No** se
     toca `VERSION` (es la versión del core instalado; la mueve `/actualizar-core`).
   - En ambos: `docs/roadmap.md` (o `planning/roadmap/INDEX.md`) y `logs/features-resume.md`.
4. Genera las notas de la versión a partir de
   [`../templates/release-notes.template.md`](../templates/release-notes.template.md)
   y las agrega al changelog (master: `docs/CHANGELOG.md`; project: `CHANGELOG.md` en la raíz,
   porque `docs/CHANGELOG.md` es del core y lo pisa el actualizador).
5. **Solo master** (stack php-mvc) regenera los paquetes descargables:
   - **Lock del core** (frontera de actualización): `php system/console/core-manifest.php`
     (debe quedar sin drift: `--check` en verde).
   - **Paquete completo** (core + app + demo): `php landing/build-download.php`.
   - **Paquete de core** (lo que consume el actualizador): `php landing/build-core-package.php --regen`
     → `landing/downloads/nsSkeleton-core-<version>.zip` + `landing/downloads/latest.json`
     (índice de releases con sha256). Publicar **ambos** en el landing y conservar los zips
     de versiones anteriores (los usa `--seed-base=remote`).
   - Actualiza el/los enlace(s) de la landing si cambió el nombre.
   En un derivado este paso se **omite** (y los scripts se niegan si existe `nsproject.json`).
6. Registra el walkthrough y, si hay repo, deja listo el tag (no commitea sin permiso).

## Orden con /nuevo-proyecto
En el master: `/release` **antes** de `/nuevo-proyecto`, para que el proyecto nazca de una
versión publicada (su `VERSION` y `core-lock.json` son la base del actualizador). Ver
[`nuevo-proyecto.md`](nuevo-proyecto.md).

## Restricciones
- No publica si los tests fallan.
- No commitea ni pushea sin confirmación del humano.
- En un derivado no genera paquetes de core ni regenera `core-lock.json`.

## Ejemplo
`/release 1.2.0`
