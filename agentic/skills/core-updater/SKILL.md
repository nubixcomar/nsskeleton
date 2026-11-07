---
name: core-updater
summary: Actualiza el core de nsSkeleton en un proyecto derivado sin pisar lo de la app; interpreta conflictos y verifica.
generic: true
---

## Rol
Responsable de **actualizar el core** de un proyecto construido sobre nsSkeleton a una versión
nueva, **sin afectar lo ya desarrollado**. Maneja el plan, los conflictos y la verificación.
Es el envoltorio humano/agéntico del CLI `system/console/core-update.php` (Fase 4) y del
paquete que produce `/release` (Fase 6).

## Cuándo usar
- Hay una versión nueva del core (paquete `nsSkeleton-core-x.y.z.zip`) y querés traerla al proyecto.
- El usuario pide "actualizar el core / el framework / nsSkeleton".

## Conocimiento base (leer antes)
- [`../../../docs/CORE-UPDATE.md`](../../../docs/CORE-UPDATE.md) — el modelo completo (core vs app,
  frontera, conflictos, rollback, publicación).
- Frontera y locks: `core-manifest.json` / `core-lock.json`. Motor: `App\Services\CoreUpdater`.

## Entrada
- El paquete del core nuevo: una carpeta, un `.zip`, una URL (`--url` + `--sha256`) o la
  última publicada en `latest.json` (`--latest`, usa `CORE_UPDATE_URL`).
- El proyecto instalado (raíz con `core-lock.json` + `VERSION`).

## Tarea
0. **Base del merge**: `php system/console/core-update.php --seed-base` muestra cuántos
   originales hay guardados y qué archivos editados no tienen. Si hay editados sin original,
   completarlos con `--seed-base=remote` (o `=<zip de la versión instalada>`) antes del update:
   así esos conflictos se pueden mergear en vez de quedar solo como `.new`.
1. **DRY-RUN primero** (nunca aplicar a ciegas):
   `php system/console/core-update.php --latest` (o `--source=<dir|zip>` / `--url=<url>`).
   Leer el plan: `add` / `update` / `delete` (limpios) y, sobre todo, **`conflict` /
   `conflict_add` / `delete_modified`** (donde la app tocó archivos del core). Cada `conflict`
   trae su vista previa: `merge 3-way limpio`, `merge con choques`, `sin base` o `binario`.
2. **Triage de conflictos** (lo que el humano necesita decidir): por cada conflicto, entender
   por qué la app editó ese archivo del core. Si el cambio de la app debería haber sido un
   **override** (regla de oro: no editar el core), proponer migrarlo al punto de extensión que
   corresponda (`config/overrides/`, `routes.app.php`, `app/Views/overrides/`,
   `app-agentic/…`) — ver la tabla de extensión en `agentic/adapters/{stack}/conventions.md`.
3. **Aplicar** con confirmación: `… --apply` (hace backup en `storage/backups/core-update-<ts>/`
   y corre las migraciones del core). Los conflictos que combinan sin choques quedan
   **mergeados** en el archivo; el resto queda como `.new` (+ `.merge` con marcadores si hubo choques).
4. **Revisar los mergeados y resolver los `.new`**: listar al humano los archivos mergeados
   automáticamente (revisión de diff contra el backup). Por cada `archivo.new`, partir del
   `archivo.merge` si existe (marcadores `<<<<<<< proyecto` / `||||||| base` / `=======` /
   `>>>>>>> core`), dejar el resultado en el archivo real y borrar `.new` y `.merge`. Para la **capa agéntica** (skills/agentes/reglas del core que la app
   sombrea), confirmar que el override por nombre de `app-agentic/` sigue teniendo sentido con la
   versión nueva.
5. **Verificar**: correr la suite (`php tests/run.php`). Si algo se rompe por el update,
   evaluar rollback.
6. **Rollback si hace falta**: `… --rollback=<backupDir>` (restaura el estado previo).

## Reglas
- **Dry-run antes de aplicar.** Siempre. Mostrar el plan al humano y pedir OK ante conflictos.
- **No tocar lo de la app**: el updater solo gestiona archivos del core (lo del lock). Lo
  untracked (overrides, módulos, datos) no se toca por construcción.
- **Choques = decisión humana**: el merge automático solo combina cambios que no se pisan;
  donde hay marcadores, no elijas un lado por tu cuenta en lógica de negocio. Proponé, no impongas.
- **Mergeados = revisión obligatoria**: que combine sin choques no garantiza que el resultado
  tenga sentido; mostralos al humano y validá con la suite.
- **Backups intactos** hasta confirmar que el update quedó sano (suite verde).
- Si la app editó archivos del core, **recomendar moverlo a un override** para que el próximo
  update sea limpio.

## Salida
- Core actualizado + mergeados revisados + `.new`/`.merge` resueltos + suite verde (o rollback documentado).
- Walkthrough + línea en `logs/core-updater.log`, según
  [`../../methodology/logging.md`](../../methodology/logging.md): versión origen→destino,
  conflictos encontrados y cómo se resolvieron, backupDir.
