# Walkthrough — Actualizador de core: merge 3-way + latest.json + pantalla de admin

**Fecha y hora:** 2026-11-07 09:35 | **Agente:** core-dev | **Modelo:** claude-opus-5-5
**Sprint:** — | **Versión:** sin publicar (sobre core 1.19.0) | **Trazas:** backlog del core #1 #2 #3 (`docs/roadmap.md`)

---

## Resumen ejecutivo
Se implementaron las tres mejoras del backlog del actualizador de core: merge 3-way automático
de conflictos, índice `latest.json` con descarga verificada por sha256, y la pantalla
`/admin/core-update`. Las tres se apoyan en un orquestador común (`App\Services\CoreUpdate`),
así el CLI y el panel hacen exactamente lo mismo. Verificado con 23 tests nuevos y una prueba de
punta a punta por HTTP en una instalación aislada.

## Cambios realizados
- **`ThreeWayMerge`** (nuevo): diff3 por líneas en PHP puro (Myers con recorte de prefijo/sufijo +
  tramos estables). Conserva CRLF y el final de archivo. Marcadores `<<<<<<< proyecto` /
  `||||||| base` / `=======` / `>>>>>>> core`.
- **`CoreUpdater`**: el plan guarda el hash original (`old`) en los `conflict`; `apply()` acepta
  `['base_dir','merge']` (firma compatible). Sin choques → acción `merged` (con backup). Con
  choques → local intacto + `.new` + `.merge`. `rollback()` restaura `merged` y limpia `.merge`.
  Helpers nuevos: `storeBase`, `baseStatus`, `mergePreview`.
- **`CoreUpdate`** (nuevo, orquestador): `prepareSource` (dir/zip, defensa zip-slip, lock en raíz
  o un nivel abajo), `inspect` (plan + vista previa del merge + rutas sin permiso de escritura),
  `run` (lock `flock` anti-concurrencia, backup de metadatos, apply, VERSION/lock/manifest,
  rotación de la base, migraciones, `report.json`), `rollback` (archivos + metadatos + base),
  `history`, `backupPath` (whitelist de nombre), base: `captureBase`, `seedBaseFrom`,
  `editedWithoutBase`.
- **`CoreRelease`** (nuevo): `latest.json` (validación, `check`, `release`, `resolveUrl`,
  `buildIndex`) y `download` con verificación sha256 (borra el archivo si no coincide). Usa
  `Http` (testeable con `setTransport`).
- **CLI `core-update.php`**: reescrito sobre `CoreUpdate`. Nuevos `--check` (exit 3 = hay update),
  `--latest[=url]`, `--sha256`, `--no-merge`, `--seed-base[=<dir|zip>|remote]`. Se mantienen
  `--source/--url/--apply/--yes/--rollback` y la salida anterior.
- **`landing/build-core-package.php`**: escribe `latest.json` junto al zip (índice recalculado
  desde los zips en disco).
- **Panel**: `CoreUpdateController` + vista `admin/core-update/index`, rutas
  `/admin/core-update{,/check,/prepare,/apply,/rollback,/discard,/seed-base}`, ítem de menú
  "Sistema → Actualizar core", permiso nuevo `core.update` (no asignado a `admin`: solo
  superadmin vía `*`), `config/core_update.php` (`latest_url`, `ui_enabled`, `merge`).
- **Config**: `.env.example` (`CORE_UPDATE_URL`, `CORE_UPDATE_UI`), `.gitignore`
  (`/system/storage/core-base/`).
- **Docs**: `docs/CORE-UPDATE.md` (uso con latest.json, panel, merge y base, publicación, piezas),
  `agentic/knowledge/core-manual.md` (4 entradas nuevas + CLI), skill/agente `core-updater`,
  comando `/actualizar-core`, `/release` y skill `release-manager` (publicar `latest.json`),
  `docs/roadmap.md` (backlog ✅), `docs/CHANGELOG.md` (en "Sin publicar").

## Decisiones de diseño
- **Merge en PHP puro, sin `git merge-file`**: el updater corre en hostings donde git no está.
  Comportamiento alineado con git: cambios en líneas contiguas chocan.
- **Base guardada aparte** (`system/storage/core-base/`): el lock solo tiene hashes. Un archivo
  entra a la base solo si su sha256 coincide con el del lock (nunca se guarda como "original"
  una versión editada). Captura oportunista en cada uso + rotación completa tras cada update.
  Para archivos editados antes de tener base: semilla desde el zip de la versión instalada
  (`--seed-base=remote` usa `latest.json`, que por eso indexa todas las releases).
- **Con choques no se toca el local**: se entrega `.merge` como propuesta además del `.new`,
  en vez de escribir marcadores en el archivo real (que rompería PHP en producción).
- **Panel**: paquete solo desde `latest.json` con sha256 verificado (no se aceptan URLs ni zips
  arbitrarios desde la web); confirmación escribiendo la versión; las clases que se usan después
  del reemplazo se cargan antes del apply; PRG para que el request siguiente ya use el código
  nuevo. No se usó la cola de jobs para el apply: agrega dependencia del cron y el flujo completo
  tarda segundos; la pantalla avisa si faltan permisos y recomienda la consola en hostings limitados.
- **No se subió `VERSION`**: hay trabajo de otra sesión en paralelo sin publicar (manuales
  remotos, capa de planning). La release queda para `/release`; lo mío está en "[Sin publicar]".

## Archivos tocados
| Archivo | Cambio |
|---------|--------|
| `system/app/Services/ThreeWayMerge.php` | nuevo |
| `system/app/Services/CoreRelease.php` | nuevo |
| `system/app/Services/CoreUpdate.php` | nuevo |
| `system/app/Services/CoreUpdater.php` | merge en apply/rollback, base, preview |
| `system/app/Controllers/Admin/CoreUpdateController.php` | nuevo |
| `system/app/Views/admin/core-update/index.php` | nuevo |
| `system/config/core_update.php` | nuevo |
| `system/config/routes.php` · `menu.php` · `permissions.php` | rutas, menú, permiso `core.update` |
| `system/console/core-update.php` | reescrito sobre CoreUpdate + flags nuevos |
| `landing/build-core-package.php` | escribe `latest.json` |
| `.env.example` · `.gitignore` | `CORE_UPDATE_*` · `core-base/` |
| `tests/unit/ThreeWayMergeTest.php` · `CoreUpdateTest.php` | nuevos (10 + 13) |
| `tests/unit/CorePackageTest.php` | verifica `latest.json` |
| `docs/CORE-UPDATE.md` · `docs/roadmap.md` · `docs/CHANGELOG.md` | docs |
| `agentic/knowledge/core-manual.md` · `skills/core-updater` · `agents/pm/core-updater.md` · `commands/actualizar-core.md` · `commands/release.md` · `skills/release-manager` | capa agéntica |

## Testing / verificación
- **Suite completa**: 414–415 PASS, 1 SKIP. Fallos ajenos a este trabajo:
  - `CronMasterTest › deshabilitado por defecto`: ya fallaba antes de empezar (depende del estado
    de settings en la DB local).
  - `CoreBoundaryTest › lock sin drift`: falla de forma intermitente porque otra sesión está
    creando archivos de core (capa de planning) durante la corrida. Con el árbol quieto pasa.
- **Tests del área** (ThreeWayMerge, CoreUpdate/CoreRelease, CoreUpdater, CorePackage,
  CoreBoundary): 40/40 PASS.
- **E2E por HTTP** (Apache XAMPP, login real `admin`): instalación aislada
  `C:\xampp\htdocs\nsupd-e2e` (copia del árbol, core 1.19.0) + core falso 1.99.0 publicado en
  `C:\xampp\htdocs\nsupd-e2e-landing` (zip + latest.json, también con el zip de 1.19.0).
  En la instalación se editaron "como proyecto" `Version.php` (zona distinta a la del core) y
  `features.php` (misma línea que el core).
  1. La pantalla detecta 2 editados sin original → *Completar originales desde el landing* → "Base completada: 2".
  2. *Buscar versión* → "Hay una versión nueva del core: 1.99.0".
  3. *Preparar* → sha256 verificado; plan: Agregar 1 · Actualizar 2 · Editado por el proyecto 2 ·
     Sin cambios 483; `Version.php` "Se combina solo", `features.php` "Con choques (1)".
  4. *Aplicar* con confirmación errónea → rechazado; con `1.99.0` → "Core actualizado a 1.99.0. Hay 1 conflicto".
     `Version.php` con ambos cambios; `features.php` intacto + `.new` + `.merge`:
     ```
     <<<<<<< proyecto
         'exportar_listados'  => false, // el proyecto lo apaga
     ||||||| base
         'exportar_listados'  => true,  // ejemplo: mostrar botones de exportación
     =======
         'exportar_listados'  => true,  // v1.99: comentario cambiado por el core
     >>>>>>> core
     ```
  5. *Revertir* con `backup=../../etc` → "Backup de update no encontrado"; con el backup real →
     "Rollback hecho: 5 archivo(s)"; VERSION 1.19.0, `.new`/`.merge` y el archivo agregado eliminados,
     historial "Revertido". Sin sesión → 302 al login.
  6. CLI en la misma instalación: `--check` (exit 3 + aviso http), `--latest` (dry-run con la
     vista previa del merge), `--latest --apply --yes` (mismo resultado que el panel),
     `--rollback` (exit 0), `--seed-base` (484/484 originales).
- **Bug encontrado y corregido en la E2E**: el dry-run con `--latest`/`--url` dejaba el zip
  descargado en `storage/cache/`; ahora se borra en todas las salidas.

## Pendientes / follow-ups
- Publicar: `/release` (subir `VERSION`, regenerar `core-lock.json` con el árbol quieto,
  `build-core-package.php --regen` y subir zip + `latest.json` al landing).
- Configurar `CORE_UPDATE_URL` (https) en los proyectos derivados.
- Los proyectos en v1.19 o anterior hacen su primer salto con el updater viejo (sin merge).
- Artefactos de la prueba E2E que quedaron (no se borraron, ver regla de limpieza):
  `C:\xampp\htdocs\nsupd-e2e`, `C:\xampp\htdocs\nsupd-e2e-src`, `C:\xampp\htdocs\nsupd-e2e-landing`.
  La instalación de prueba usa la misma DB `ns_skeleton` (no se corrió ninguna migración nueva).

## Referencias
- `docs/CORE-UPDATE.md` §3, §5, §6, §7, §8
- `agentic/knowledge/core-manual.md` §6 (`CoreUpdate`, `CoreUpdater`, `CoreRelease`, `ThreeWayMerge`)
