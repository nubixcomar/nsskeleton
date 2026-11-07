# Walkthrough — Manuales remotos (RemoteDocs) + referencia viva de la API de nubixstore

Fecha y hora: 2026-11-07 09:16 | Agente: nubixstore-api (modo general) | Modelo: claude-opus-5-5

## Contexto
nubixstore publica dos manuales que cambian seguido y que conviene tener actualizados antes de
trabajar con su API:
- **Referencia de la API** (pública, generada desde el registry de endpoints, ~semanal):
  `https://central.nstore.ar/manuales/referencia-api-nubixstore`
- **Buenas prácticas de uso de APIs** (nubixstore y genéricas, ~mensual):
  `https://central.nstore.ar/manuales/buenas-practicas-api`

Los dos se sirven como `text/markdown`, con `ETag` sha256 y `Cache-Control: max-age=300`, y
responden 304 a `If-None-Match`. La copia que tenía el skeleton
(`agentic/knowledge/nubixstore/manual-api-nubixstore.md`) era la v2.1 de abril, contra la API
2.6.0 actual.

Restricción de diseño: nsSkeleton es genérico (hay proyectos derivados sin nubixstore), así que
todo esto es opcional y desacoplado.

## Qué se hizo
- **`system/config/remote_docs.php`**: registro de manuales (url https, file, version_pattern,
  min_bytes, enabled). Se lee vía `Core\Config::load`, así que un proyecto lo apaga o suma los
  suyos desde `config/overrides/remote_docs.php`.
- **`App\Services\RemoteDocs`**: `sync()`, `status()`, `pathFor()` y `validate()`. Guarda el
  contenido verbatim, compara sha256, escribe de forma atómica (tmp + rename), guarda la versión
  anterior como `*.prev.md` y la procedencia en `remote-docs.lock.json`. Rechaza HTML, cuerpos
  chicos, lo que no empieza con `#` y los errores de red **sin tocar la copia local**. Si el
  archivo local se editó a mano, lo repone. Bloquea rutas con `..`. Nunca lanza.
- **CLI** `system/console/docs-sync.php [sync|status] [<clave>] [--dry-run]`. Exit 1 si hay
  error; en `status`, exit 2 si hay vencidos (más de `max_age_hours`, por defecto 24 h).
- **Job** `docs:sync` en `config/jobs.php`: lanza excepción si algún manual falla, así la tarea de
  cron queda en error en el historial. Es opt-in: no se crea ninguna tarea de cron automáticamente.
- **Destino `app-agentic/knowledge/remote/`**: capa del proyecto, así el actualizador de core no
  lo pisa y un sync no genera drift en `core-lock.json`. Incluye su README.
- **Decisión: comparar por sha256 y no por ETag/304.** `App\Services\Http` no expone los headers
  de respuesta y los manuales pesan ~170 KB + ~57 KB. Un GET diario es despreciable. Si hiciera
  falta, se podría agregar el condicional en el futuro.

## Procesamiento de los manuales (relevamiento)
Primer sync real: `nubixstore-referencia` **v2.6.0+09a6bd63** (2026-11-04, 67 endpoints públicos)
y `buenas-practicas-api` **v1.6.0** (2026-11-04).

Contrastado contra `NubixstoreConnector` (escrito contra v2.1, `API_MANUAL_VERSION = '2.1'`):
1. **Criterio de éxito distinto (relevante).** La 2.6.0 define éxito = `status === 0` y HTTP 2xx.
   Token inválido → HTTP 200 con `code` 903; módulo API apagado → 200 con `code` 905. El driver da
   `ok=true` con 2xx salvo `status: "ERROR"`, así que **toma 903/905 como éxito**.
2. Módulo del endpoint apagado → HTTP 403 con `code` 910.
3. Las redirecciones son siempre error (en APIs anteriores a 2.6.0 se redirige a HTML).
   `Http` con cURL no sigue redirecciones por defecto, pero el fallback por stream context sí.
4. `GET /api/utils/connect` (usado por `ping()`) ya no está en la referencia pública: es interno 🔒.
5. Siguen en la pública: `POST /usuarios/token` (sin `/api`), `articulos/items|item|stock`,
   `pedidos/orders`, `pedidos/order/{id}` y `usuarios/clients`.

**El driver no se modificó.** Las diferencias quedaron documentadas en
`agentic/skills/nubixstore-api/SKILL.md` (§ "Diferencias conocidas"). Ajustarlo cambia el contrato
de `ok` para los proyectos derivados, así que queda como propuesta para que decida el usuario.
`API_MANUAL_VERSION` sigue en `2.1`: se sube recién cuando se verifique el driver.

## Documentación tocada
- `agentic/skills/nubixstore-api/SKILL.md`: paso 0 obligatorio (sync), jerarquía de fuentes
  (referencia viva > manual histórico), diferencias conocidas y regla de versión. Integración opcional.
- `agentic/agents/integrations/nubixstore-api.md`: lo mismo, resumido.
- `agentic/skills/ecommerce-integration/SKILL.md`: referencia a buenas prácticas.
- `agentic/knowledge/README.md`: sección "Manuales remotos" y tabla de consumo.
- `agentic/knowledge/core-manual.md`: ficha de `RemoteDocs` en §7 y en el índice.
- `agentic/knowledge/nubixstore/manual-api-nubixstore.md`: marcado como HISTÓRICO.
- `docs/CHANGELOG.md`: entrada "Sin publicar".
- `core-lock.json`: regenerado (468 archivos core).

## Verificación
- `tests/unit/RemoteDocsTest.php`: 9 tests (new/unchanged/updated + .prev, errores que no pisan
  la copia, dry-run, reposición de una edición a mano, disabled/clave desconocida, vencimiento y `..`).
- Suite: **392 PASS, 1 FAIL, 1 SKIP**. El fallo (`Cron master · deshabilitado por defecto`) ya
  estaba antes de este cambio (verificado con `git stash`): depende del estado de la base local,
  que tiene el cron master habilitado. Es ajeno a este trabajo.
- Sync real: dos corridas (`NUEVO` y después `sin cambios`), `status` en exit 0 y
  `Jobs::run('docs:sync')` ok.

## Checklist de cierre
1. Línea en `logs/nubixstore-api.log`: ✅
2. Walkthrough: ✅ (este archivo)
3. Informes: el relevamiento está en la sección "Procesamiento"; no hay informe aparte.
4. Bugs: no se tocaron. Se documentó la diferencia del driver, sin abrir BUG formal hasta que decida el usuario.
5. Roadmap: no aplica (no era un feature del roadmap).

## Pendiente / propuestas
- Ajustar `NubixstoreConnector` al criterio de éxito de la 2.6.0 (`status === 0`) y cambiar
  `ping()` a un endpoint público. Después, subir `API_MANUAL_VERSION` y su test.
- Programar la tarea de cron `job:docs:sync` (diaria) en los entornos que integren nubixstore.
- Regla en `Http`: no seguir redirecciones también en el fallback por stream.
