# Walkthrough — Actualización de core: Fase 3 (puntos de extensión)

**Fecha y hora:** 2026-06-28 19:30 | **Agente:** core-updater | **Modelo:** claude-opus-4-8
**Sprint:** — | **Versión:** 1.12.0

---

## Resumen ejecutivo
Tercera fase del mecanismo de actualización de core. Aporta los **puntos de extensión** que
permiten a un proyecto personalizar el core **sin editar sus archivos**, de modo que un update
de core no pise lo del proyecto. Cuatro mecanismos: config overrides, rutas de la app, vistas
child-theme y migraciones core/app separadas. Cimiento del actualizador (Fase 4).

## Cambios realizados
- **Config overrides** — `Core\Config` (nuevo): `load($name)` mezcla `config/$name.php` (core)
  + `config/overrides/$name.php` (app), deep-merge, **app gana**. Wireado en `Core\App`
  (bootstrap `app`) y `FeatureFlags::defaults()` (`features`). Carpeta `config/overrides/` + README.
- **Rutas de la app** — `config/routes.php` carga además `config/routes.app.php` (después del
  core; la app agrega o gana rutas). El glob por-archivo `config/routes/*.php` ya existía.
- **Vistas child-theme** — `Core\View` resuelve `app/Views/overrides/{vista}.php` antes que la
  del core (método `resolve()` usado por `partial()`/`exists()`). Dir + README.
- **Migraciones core/app** — `Migrator`: `appDir()`, `files(null)` = core+app (core primero),
  `migrateCore()`/`migrateApp()`. Dir `database/migrations/app/` + README. El generador
  (`make-module.php`) emite la migración del módulo al dir de la app.
- **Manifest** — quitadas de `app_paths` las entradas de dirs de override (sus READMEs son
  core, se envían); lo que la app cree ahí queda untracked (el updater no lo toca). Notas y
  `core_version` actualizadas a 1.12.0.

## Decisiones de diseño
- **Override, no edición**: cada mecanismo deja el archivo del core intacto y resuelve "app
  primero". Es lo que hace limpio el update.
- **Adopción incremental de Config**: solo `app` y `features` pasan por `Core\Config` ahora;
  hacer overrideable otra config es cambiar su lectura a `Config::load()` (1 línea). No se
  migraron todas para no arriesgar regresiones.
- **Migraciones por directorio, no por prefijo**: evita renombrar las migraciones del core ya
  aplicadas en instalaciones existentes (rompería `schema_migrations`).
- **READMEs de override = core**: así un fresh install trae la guía; el contenido real del
  proyecto en esos dirs es untracked y a salvo.

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| system/app/Core/Config.php | nuevo (loader con overrides) |
| system/app/Core/App.php | usa Config::load('app') |
| system/app/Core/View.php | resolución child-theme (overrides → core) |
| system/app/Services/FeatureFlags.php | defaults vía Config::load('features') |
| system/app/Services/Migrator.php | appDir + files(core+app) + migrateCore/migrateApp |
| system/config/routes.php | carga config/routes.app.php |
| system/console/make-module.php | migración del módulo → database/migrations/app/ |
| system/config/overrides/README.md | nuevo (guía) |
| system/app/Views/overrides/README.md | nuevo (guía) |
| system/database/migrations/app/README.md | nuevo (guía) |
| agentic/adapters/php-mvc/conventions.md | tabla de puntos de extensión |
| tests/unit/ExtensionPointsTest.php | nuevo |
| core-manifest.json, core-lock.json | app_paths + notas + regen |
| VERSION, docs/CHANGELOG.md | bump 1.12.0 |

## Testing / verificación
- `php -l` OK en los 7 PHP tocados.
- Suite completa: ver corrida (objetivo: verde, incluyendo ExtensionPointsTest y `--check`).

## Pendientes / follow-ups
- **Fase 4**: updater `system/console/core-update.php` (dry-run, backup, apply-by-manifest,
  conflicto `.new` por drift, `Migrator::migrateCore()`, rollback).
- **Fase 5**: comando `/actualizar-core` + agente `core-updater` + `docs/CORE-UPDATE.md`.
- **Fase 6**: `/release` publica manifest + lock + zip core.
- (Opcional) Adoptar `Core\Config` en más configs (menu, ecommerce, dashboard, etc.).

## Referencias
- `agentic/adapters/php-mvc/conventions.md` (tabla de extensión), `system/config/overrides/README.md`.
