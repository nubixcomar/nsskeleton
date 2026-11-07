---
name: nuevo-proyecto
usage: /nuevo-proyecto [<nombre>] [--path=<dir>] [--db=<base>] [--remote=<repo>] [--demo]
spawns: [installer]
---

## Qué hace
Crea un **proyecto nuevo** a partir del skeleton master (`c:\xampp\htdocs\skeleton`): copia
limpia en su propia carpeta, base de datos creada y migrada, `.env` propio y **git propio**
(desvinculado del repo de nsSkeleton). Queda listo para `/instalar`.

Se corre **desde el master**. En un proyecto derivado se niega (copiaría su código).

## Proceso
1. Si no vino el nombre, preguntarlo al humano (y opcionalmente: carpeta, base, repo propio,
   datos demo). Defaults: carpeta hermana del master `<slug>`, base `<slug>` con `_`.
2. **Recomendado antes**: si el master tiene cambios sin commitear o el lock tiene drift,
   sugerir `/release` en el master (el proyecto hereda `VERSION` + `core-lock.json`, que son la
   base del actualizador de core). Ver [`release.md`](release.md).
3. Mostrar el plan sin tocar nada:
   `php system/console/new-project.php "<nombre>" [opciones] --dry-run`
4. Con la confirmación del humano, ejecutar con `--yes`:
   `php system/console/new-project.php "<nombre>" [opciones] --yes`
   - Copia limpia (sin `.git`, `.env`, `landing/`, `tools/bin/`, logs ni runtime del master).
   - Archivos del proyecto: `nsproject.json` (marcador de derivado), `docs/roadmap.md`,
     `logs/features-resume.md`, `logs/bugs-resume.md` y `logs/walkthrough/` vacíos.
   - `.env` con `APP_NAME`, `APP_URL`, `DB_*` (credenciales MySQL del master), `APP_KEY` nueva
     y `CORE_UPDATE_URL` heredada.
   - Crea la base + `migrate.php` + `seed.php` (+ `seed-demo.php` con `--demo`).
   - Guarda los originales del core (`core-update.php --seed-base`) para que el primer
     `/actualizar-core` pueda mergear.
   - Crea `planning/` vacío desde el scaffold (`planning.php init`); el `planning/` del master
     (roadmap del propio skeleton) **no** se copia.
   - `git init -b main` + commit inicial; `origin` solo si se pasó `--remote` (nunca el repo
     de nsSkeleton; no hace push).
5. Indicarle al humano que abra la carpeta nueva con su IA y corra `/instalar` (brief, stack,
   reglas del proyecto).

## Opciones
| Opción | Default | |
|---|---|---|
| `--path=<dir>` | `<padre del master>/<slug>` | Debe no existir o estar vacía |
| `--db=<base>` | `<slug>` con `_` | Debe **no existir** |
| `--url=<url>` | `http://localhost/<carpeta>/system/public` | `APP_URL` |
| `--db-host/--db-port/--db-user/--db-pass` | los del `.env` del master | |
| `--remote=<url>` | sin remoto | Repo propio; se agrega como `origin` |
| `--demo` | no | Datos demo |
| `--no-db` / `--no-git` | | Omite esos pasos |
| `--strict` | no | Los avisos del master abortan |
| `--dry-run` / `--yes` | | Plan sin tocar nada / sin confirmación |

## Restricciones
- No sobrescribe nada: aborta si la carpeta destino tiene archivos o si la base ya existe.
- Nunca deja como remoto el repo de nsSkeleton; nunca hace push.
- No edita el master (solo lo lee).
- Trazabilidad: escribe `logs/new-project.log` en el proyecto nuevo; el agente registra además
  su walkthrough en el master según [`../methodology/logging.md`](../methodology/logging.md).

## Ejemplo
`/nuevo-proyecto "Jardín Botánico" --remote=https://github.com/nubixcomar/jardin.git`
→ `c:\xampp\htdocs\jardin-botanico`, base `jardin_botanico`, admin en
`http://localhost/jardin-botanico/system/public/admin/login`.
