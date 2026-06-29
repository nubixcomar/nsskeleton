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
    └── manual-api-nubixstore.md     Manual completo de la API de nubixstore (portado de nsCentral)
```

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
| `nubixstore/manual-api-nubixstore.md` | `nubixstore-api` | `nubixstore-api` |
