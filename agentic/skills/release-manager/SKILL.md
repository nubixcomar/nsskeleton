---
name: release-manager
summary: Publica versiones de forma reproducible: tests verdes, versionado, changelog y paquete.
generic: true
---

## Rol
Responsable de releases: convierte un conjunto de cambios verificados en una versión
publicable y trazable.

## Entrada
- Número de versión (semántico) y el estado actual del proyecto.

## Tarea
0. Detectar el rol del árbol: `php system/console/project-role.php` → `master` (skeleton) o
   `project` (derivado, tiene `nsproject.json`). Si un derivado viejo sale como `master`,
   confirmarlo con el humano y marcarlo (`project-role.php --mark="<nombre>"`).
1. Asegurar que la suite de tests esté **verde** (`php tests/run.php`). Si falla, abortar.
2. Versionar según el rol:
   - **master**: `VERSION` (+ `core_version` de `core-manifest.json`).
   - **project**: `APP_VERSION` en `.env`/`.env.example`. **No** tocar `VERSION` (es el core
     instalado; solo lo mueve `/actualizar-core`).
   - Ambos: `docs/roadmap.md` (o `planning/roadmap/INDEX.md`) y `logs/features-resume.md`.
3. Generar notas de la versión desde
   [`../../templates/release-notes.template.md`](../../templates/release-notes.template.md)
   y agregarlas al changelog: master → `docs/CHANGELOG.md`; project → `CHANGELOG.md` de la raíz
   (`docs/CHANGELOG.md` es del core y lo pisa el actualizador).
4. **Solo master** (stack php-mvc) — regenerar los paquetes y la frontera de actualización de
   core. En un derivado se omite entero (los scripts se niegan si existe `nsproject.json`):
   - `php system/console/core-manifest.php` (regenera `core-lock.json`; `--check` sin drift).
   - `php landing/build-download.php` (paquete completo core+app+demo).
   - `php landing/build-core-package.php --regen` (paquete **de core** que consume el
     actualizador → `nsSkeleton-core-<version>.zip` + `latest.json`, el índice de releases con
     sha256 que leen `core-update.php --check|--latest` y `/admin/core-update`). Publicar ambos y
     conservar los zips anteriores.
   - Actualizar el/los enlace(s) de descarga en la landing si cambió el nombre.
5. Dejar listo el tag de git (sin commitear/pushear sin permiso del humano).

## Reglas
- **No** publicar con tests en rojo.
- **No** commitear/pushear/tag sin confirmación explícita.
- Versionado semántico (MAJOR.MINOR.PATCH).
- **Nunca** generar paquetes de core ni regenerar `core-lock.json` en un proyecto derivado.
- En el master, sugerir `/release` antes de `/nuevo-proyecto` si hay cambios sin publicar.

## Salida
- `VERSION` + changelog + paquete + walkthrough, según
  [`../../methodology/logging.md`](../../methodology/logging.md).
