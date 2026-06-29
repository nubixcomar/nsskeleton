---
name: release
usage: /release <version> [--notes]
spawns: [release-manager]
---

## Qué hace
Publica una versión del proyecto de forma reproducible y trazable.

## Proceso
1. Invoca el agente `release-manager`.
2. Verifica que la suite de tests esté verde (`/test` → `php tests/run.php`).
3. Actualiza la versión:
   - `VERSION` → `<version>`.
   - `docs/roadmap.md` y `logs/features-resume.md` (marca la versión liberada).
4. Genera las notas de la versión a partir de
   [`../templates/release-notes.template.md`](../templates/release-notes.template.md)
   y las agrega a `docs/CHANGELOG.md`.
5. (Si aplica al stack php-mvc) regenera los paquetes descargables:
   - **Lock del core** (frontera de actualización): `php system/console/core-manifest.php`
     (debe quedar sin drift: `--check` en verde).
   - **Paquete completo** (core + app + demo): `php landing/build-download.php`.
   - **Paquete de core** (lo que consume el actualizador): `php landing/build-core-package.php --regen`
     → `landing/downloads/nsSkeleton-core-<version>.zip`.
   - Actualiza el/los enlace(s) de la landing si cambió el nombre.
6. Registra el walkthrough y, si hay repo, deja listo el tag (no commitea sin permiso).

## Restricciones
- No publica si los tests fallan.
- No commitea ni pushea sin confirmación del humano.

## Ejemplo
`/release 1.2.0`
