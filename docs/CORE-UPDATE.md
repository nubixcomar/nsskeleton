# Actualización de core de nsSkeleton

Cómo un proyecto construido sobre nsSkeleton **actualiza su core** a una versión nueva
**sin afectar lo ya desarrollado**. Es el mecanismo completo (Fases 1–6).

> TL;DR: el core es dueño solo de lo que envía (listado en `core-lock.json`); **todo lo demás
> es tuyo y el actualizador no lo toca**. Para personalizar el core, **no lo edites**:
> overridealo. Así un update es limpio.

---

## 1. El modelo: core vs proyecto

| | **Core** (lo actualiza nsSkeleton) | **Proyecto** (tuyo, nunca pisado) |
|---|---|---|
| Código | `system/app/Core/**`, servicios del core, controllers base, `system/console/*` | módulos generados, código propio, `system/app/Modules/**` |
| Config | defaults `system/config/*.php` | `system/config/overrides/*.php`, `routes.app.php`, credenciales en `settings` |
| Vistas | `system/app/Views/**` | `system/app/Views/overrides/**` |
| Migraciones | `system/database/migrations/*.sql` | `system/database/migrations/app/*.sql` |
| Agéntico | `agentic/**` (reglas core, methodology, agentes/skills genéricos) | `app-agentic/**` (reglas, agentes, skills, knowledge del proyecto) |
| Datos | — | `system/storage/**`, `.env`, secretos |

La frontera la define **`core-manifest.json`** (reglas `core_paths`/`app_paths`/`exclude`) y el
snapshot con checksums **`core-lock.json`**. Regla operativa: *el core es dueño exactamente de
los archivos del lock; lo que no está, es del proyecto* (untracked = a salvo).

---

## 2. La regla de oro: override, no edición

Para que el update sea indoloro, **no edites archivos del core**. Personalizá desde los puntos
de extensión:

| Querés cambiar… | Hacé esto (proyecto) | Mecanismo |
|---|---|---|
| Una config (`config/x.php`) | `config/overrides/x.php` con las claves a pisar | `Core\Config::load('x')` (merge, app gana) |
| Agregar rutas | `config/routes.app.php` (o un archivo en `config/routes/`) | se cargan después del core |
| Vista/layout/parcial | `app/Views/overrides/{mismo-path}.php` | `Core\View` resuelve override → core |
| Migraciones del proyecto | `database/migrations/app/*.sql` | `Migrator::migrate()` corre core→app |
| Regla / agente / skill | `app-agentic/…` homónimo | precedencia **app > core** (override por nombre) |

Si alguna vez **tenés** que editar un archivo del core, sabé que el updater lo detectará como
**conflicto** y no lo pisará (te deja la versión nueva como `.new` para mergear).

---

## 3. Cómo actualizar (uso)

Asistido (recomendado): el comando `/actualizar-core <dir|zip|url>` (agente `core-updater`)
hace dry-run, te explica el plan y los conflictos, aplica con backup, resuelve los `.new` y
corre la suite.

Manual (CLI):

```bash
# 1) Ver qué cambiaría (DRY-RUN, no toca nada):
php system/console/core-update.php --source=../nsSkeleton-core-1.15.0.zip
#    o desde el landing:
php system/console/core-update.php --url=https://misitio/downloads/nsSkeleton-core-1.15.0.zip

# 2) Aplicar (hace backup automático + corre migraciones del core):
php system/console/core-update.php --source=../nsSkeleton-core-1.15.0.zip --apply

# 3) Si algo quedó mal, revertir:
php system/console/core-update.php --rollback=system/storage/backups/core-update-YYYYmmdd_HHMMSS
```

El `--source` puede ser una **carpeta** o un **`.zip`** con `core-lock.json` en la raíz (el
paquete que produce `/release`). El DRY-RUN es el modo por defecto: `--apply` es explícito.

---

## 4. El plan: qué hace con cada archivo

El motor (`App\Services\CoreUpdater`) compara **tres estados** — el lock instalado (lo que el
core te envió antes), tu árbol local (para ver si editaste el core) y el lock nuevo:

| Acción | Significado |
|---|---|
| `add` | archivo nuevo del core → se agrega |
| `update` | archivo del core que no tocaste → se pisa (limpio) |
| `skip` | ya está igual al nuevo → nada |
| `conflict` | **editaste un archivo del core** → el nuevo se deja como `archivo.new` (no se pisa) |
| `conflict_add` | creaste un archivo donde ahora el core trae uno → `.new` |
| `delete` | el core eliminó un archivo que no tocaste → se borra |
| `delete_modified` | el core lo eliminó pero vos lo editaste → **se conserva** + aviso |

Solo se tocan archivos del core. Tus overrides, módulos, migraciones de app y datos son
**untracked** → intactos por construcción.

---

## 5. Conflictos: cómo resolverlos

Un conflicto significa que editaste un archivo del core. Tras `--apply`, tenés
`archivo` (tu versión) y `archivo.new` (el core nuevo):

1. Mirá el diff entre ambos.
2. Mergeá a mano lo que necesites en `archivo` y **borrá** `archivo.new`.
3. **Mejor aún**: mové tu cambio a un override (sección 2) para que el próximo update sea
   limpio y no vuelva a conflictuar.

Para la capa agéntica: si tu `app-agentic/` sombrea un skill/agente del core que cambió,
revisá que el override siga teniendo sentido con la versión nueva.

---

## 6. Rollback

`--apply` respalda todo lo que toca en `system/storage/backups/core-update-<ts>/` y escribe un
`applied.json`. `--rollback=<esa carpeta>` restaura pisados/borrados, quita agregados y limpia
los `.new`. Tu código (lo de la app) nunca se tocó, así que el rollback solo revierte el core.

> Después de actualizar, **corré la suite** (`php tests/run.php`). Mantené el backup hasta
> confirmar que quedó sano.

---

## 7. Publicar una versión de core (lado nsSkeleton)

`/release <version>` (agente `release-manager`):

```bash
php system/console/core-manifest.php          # regenera core-lock.json (--check sin drift)
php landing/build-download.php                # paquete completo (core+app+demo) → arrancar proyectos
php landing/build-core-package.php --regen    # paquete de CORE → nsSkeleton-core-<v>.zip (actualizar)
```

El **paquete de core** es lo que consume el actualizador: trae solo los archivos del lock + el
propio lock. Subilo al landing y el update puede tirarlo con `--url`.

---

## 8. Piezas (referencia rápida)

| Pieza | Qué es |
|---|---|
| `core-manifest.json` | reglas de propiedad core/app |
| `core-lock.json` | snapshot con sha256 de cada archivo core |
| `system/console/core-manifest.php` | genera el lock (`--check` = drift) |
| `App\Services\CoreUpdater` | motor: plan / apply / rollback |
| `system/console/core-update.php` | CLI del actualizador (`--source/--url/--apply/--rollback`) |
| `landing/build-core-package.php` | empaqueta el zip de core |
| `/actualizar-core` · agente/skill `core-updater` | flujo asistido |
| `Core\Config`, `routes.app.php`, `app/Views/overrides/`, `database/migrations/app/`, `app-agentic/` | puntos de extensión |
