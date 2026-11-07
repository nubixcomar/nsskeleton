# Walkthrough — /nuevo-proyecto + /release según el rol (master vs derivado)

**Fecha y hora:** 2026-11-07 09:57 | **Agente:** core-dev | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** [Sin publicar] (sobre 1.19.0) | **Trazas:** —

---

## Resumen ejecutivo
No había forma de crear un proyecto derivado en un paso: el Paso 0 de `docs/INSTALL.md` era
manual (clonar, borrar `.git`, `/instalar`, migrar, `git init`). Se agregó
`php system/console/new-project.php` + comando `/nuevo-proyecto`, que desde el master deja un
proyecto listo (copia limpia, `.env`, base creada y migrada, git propio sin remoto del skeleton).
Además `/release` ahora distingue master de derivado para no generar paquetes de core desde un
proyecto.

## Cambios realizados
- **`system/console/new-project.php`**: pregunta el nombre (o lo recibe), verifica (carpeta libre,
  base inexistente, MySQL arriba, remoto ≠ nsSkeleton; avisa de cambios sin commitear / drift
  del lock; `--strict` aborta) → copia limpia → `nsproject.json` + roadmap/logs vacíos → `.env`
  (APP_KEY nueva, DB del master, `CORE_UPDATE_URL` heredada) → crea DB + migrate + seed (`--demo`)
  → verifica que la base tenga tablas → `core-update.php --seed-base` → `planning.php init` →
  `git init -b main` + commit, `origin` solo con `--remote`, nunca push. `--dry-run`, `--yes`.
- **`App\Services\ProjectCreator`**: `slug`, `dbName`, `shouldCopy`/`copyTree` (excluye `.git/`,
  `.env`, `.claude/`, `landing/`, `tools/bin/`, `planning/`, `logs/*`, runtime de storage,
  `core-base/`), `projectFiles`, `marker`, `role`/`isMaster`, `remoteIsSkeleton`, `destIsFree`.
- **`system/console/project-role.php`**: `master` (exit 0) | `project` (exit 10); `--mark` para
  derivados creados a mano (clones).
- **Guardas**: `core-manifest.php` (sin `--check`) y `landing/build-core-package.php` se niegan si
  existe `nsproject.json` (salvo `--force`). `CorePackageTest` se saltea fuera del master.
- **`Installer::buildEnv()`**: plantilla explícita, claves `APP_KEY` y `CORE_UPDATE_URL`; fix de
  `preg_replace` que interpretaba `$1`/`\1` en valores (contraseñas).
- **Docs/capa agéntica**: `agentic/commands/nuevo-proyecto.md` (nuevo), `release.md`,
  `instalar.md`, `commands/README.md`, `skills/release-manager/SKILL.md`, `docs/INSTALL.md`
  (forma rápida en el Paso 0), `agentic/knowledge/core-manual.md`, `docs/CHANGELOG.md`.
- `core-lock.json` regenerado (509 archivos core, sin drift).

## Decisiones de diseño
- **Copia desde el árbol del master** (no desde el zip de `build-download.php`): el zip trae los
  logs del skeleton y obliga a tener el paquete generado. El filtro es propio y testeado.
- **Marcador `nsproject.json`** (archivo de la app, fuera de `core_paths`) en vez de inferir por
  el remoto git: es explícito, viaja con el repo del proyecto y el core nunca lo pisa. Fallback:
  sin `landing/build-core-package.php` también es derivado.
- **`/release` en derivado** versiona `APP_VERSION` y escribe `CHANGELOG.md` de la raíz:
  `VERSION` y `docs/CHANGELOG.md` son del core y los mueve/pisa `/actualizar-core`.
- **Seed de la base del merge al crear**: el proyecto nace con los 509 originales del core, así el
  primer `/actualizar-core` puede hacer merge 3-way sin `--seed-base=remote`.
- Se avisa (no se bloquea) si el master tiene cambios sin publicar: recomendación
  `/release` → `/nuevo-proyecto`; `--strict` lo vuelve bloqueante.

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| `system/console/new-project.php` | nuevo |
| `system/console/project-role.php` | nuevo |
| `system/app/Services/ProjectCreator.php` | nuevo |
| `tests/unit/ProjectCreatorTest.php` | nuevo (12 tests) |
| `agentic/commands/nuevo-proyecto.md` | nuevo |
| `system/app/Services/Installer.php` | plantilla/APP_KEY/CORE_UPDATE_URL + fix `$` |
| `system/console/core-manifest.php` | guarda en derivados |
| `landing/build-core-package.php` | guarda en derivados |
| `tests/unit/CorePackageTest.php` | skip fuera del master |
| `agentic/commands/{release,instalar,README}.md`, `agentic/skills/release-manager/SKILL.md` | rol master/project, orden |
| `docs/INSTALL.md`, `docs/CHANGELOG.md`, `agentic/knowledge/core-manual.md` | docs |
| `core-lock.json` | regenerado |

## Testing / verificación
- Suite del master: **443 PASS, 1 FAIL, 1 SKIP**. El FAIL (`CronMasterTest › deshabilitado por
  defecto`) es preexistente: depende del estado de la DB local (cron master habilitado), no de
  este cambio.
- E2E en el scratchpad (fuera de htdocs):
  - `--dry-run`: plan + aviso de cambios sin commitear; conecta a MySQL 3307.
  - `--no-db`: 536 archivos, 8.7 MB, `nsproject.json`, rol `project` (exit 10), lock `--check` OK,
    `core-manifest.php` se niega, git `main` con 1 commit y sin remoto, suite del derivado
    **377 PASS / 0 FAIL / 68 SKIP** (DB y empaquetado salteados).
  - `--remote=https://github.com/nubixcomar/nsskeleton.git` → rechazado (exit 1).
  - Con DB (`--db=zz_nsnp_prueba_db --demo`): 22 migraciones, 19 tablas, admin + 4 admins demo
    en la base nueva; la base del master intacta (2 admins). Base del merge 509/509.
  - `planning.php init` crea `planning/` vacío en el derivado.
- **Bug encontrado y corregido durante el E2E**: el `migrate.php` del hijo heredaba el entorno
  del padre (`Env::load` usa `putenv` y no pisa variables existentes) y migraba contra la base del
  **master**. No hubo daño (sin migraciones pendientes; el seed se salteó). Fix: limpiar las
  claves del `.env` del master y cargar el del proyecto antes de los subprocesos + verificación
  posterior de tablas en la base nueva.

## Pendientes / follow-ups
- Artefactos de prueba que quedan (no se borran sin confirmación): base MySQL
  `zz_nsnp_prueba_db` y carpetas `prueba-e2e`, `prueba-db`, `prueba-db2`, `prueba-planning` en el
  scratchpad de la sesión.
- Derivados viejos creados clonando el repo se detectan como `master` hasta marcarlos con
  `php system/console/project-role.php --mark`.
- Trabajo concurrente: otra sesión estaba implementando `planning/` (PL-001) en el mismo árbol;
  se integró (`planning/` excluido de la copia + `planning.php init` en el derivado).
- Paso 5/6 del checklist de logging (roadmap `planning/roadmap/INDEX.md`): **omitido a propósito**
  porque esa otra sesión está editando `planning/` en este momento (RM-022 en curso); cargar el
  RM de este feature cuando termine, para no pisar índices.

## Referencias
- [`agentic/commands/nuevo-proyecto.md`](../../agentic/commands/nuevo-proyecto.md)
- [`agentic/commands/release.md`](../../agentic/commands/release.md)
- [`docs/INSTALL.md`](../../docs/INSTALL.md) § 0
