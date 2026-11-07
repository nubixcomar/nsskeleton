# knowledge/ — Base de conocimiento agnóstica

Manuales y referencias de dominio que los agentes/skills leen como **fuente de verdad**.
A diferencia de `skills/` (cómo actuar) y `adapters/` (bindings del stack), acá vive el
**qué saber**: documentación estable de APIs externas, plataformas y dominios.

```
knowledge/
├── core-manual.md                   Manual del core de nsSkeleton: TODOS los módulos de system/ (API + ejemplos)
├── ecommerce/
│   └── ecommerce-apis.md            Conocimiento genérico de APIs de tiendas (multi-plataforma)
└── nubixstore/
    └── manual-api-nubixstore.md     Manual de contexto de la API de nubixstore v2.1 (histórico; ver "Manuales remotos")
```

## Manuales remotos (copias vivas)

Algunos manuales cambian demasiado seguido para versionarlos con el core. Se declaran en
`system/config/remote_docs.php`, `App\Services\RemoteDocs` los baja tal cual a
**`app-agentic/knowledge/remote/`** (capa del proyecto: el update de core no los toca), y el
lock `remote-docs.lock.json` guarda la procedencia.

| Clave | Manual | Origen | Cambia |
|---|---|---|---|
| `nubixstore-referencia` | `app-agentic/knowledge/remote/nubixstore/referencia-api-nubixstore.md` | `https://central.nstore.ar/manuales/referencia-api-nubixstore` | ~semanal |
| `buenas-practicas-api` | `app-agentic/knowledge/remote/nubixstore/buenas-practicas-api.md` | `https://central.nstore.ar/manuales/buenas-practicas-api` | ~mensual |

**Antes de trabajar con un manual remoto**, corré `php system/console/docs-sync.php <clave>`
(si no hay red, usá la copia local y avisá su versión y fecha). Si cambió, mirá el diff contra
`*.prev.md`. Periódico: tarea de cron `job:docs:sync`. Detalle en
[`app-agentic/knowledge/remote/README.md`](../../app-agentic/knowledge/remote/README.md).

## Convenciones
- Un archivo por dominio/plataforma. Versionar el documento en su encabezado.
- Los skills **referencian** estos documentos; no duplican su contenido.
- El mantenimiento de cada manual tiene un responsable (un agente). Para nubixstore es
  `api-nubixstore`; si cambia la versión del manual, debe actualizarse también la constante
  `NubixstoreConnector::API_MANUAL_VERSION` en el stack por defecto.

## Quién consume qué
| Conocimiento | Skill | Agente |
|--------------|-------|--------|
| `core-manual.md` | `dev-backend`, `module-generator`, `architect` | todos los de `dev/` |
| `ecommerce/ecommerce-apis.md` | `ecommerce-integration` | `ecommerce-integrator` |
| `nubixstore/manual-api-nubixstore.md` (histórico) | `nubixstore-api` | `nubixstore-api` |
| remoto `nubixstore-referencia` (fuente de verdad de endpoints) | `nubixstore-api` | `nubixstore-api` |
| remoto `buenas-practicas-api` | `nubixstore-api`, `ecommerce-integration` | `nubixstore-api`, `ecommerce-integrator` |
