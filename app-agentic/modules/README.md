# app-agentic/modules/ — Docs de módulos del proyecto

Un archivo `.md` por **módulo de tu sistema**: qué hace, su contrato (rutas/servicios/tablas),
decisiones de diseño y cómo se usa. Es la doc que un agente lee **antes de tocar** ese módulo
(ver regla de carga en `agentic/rules/rules.md` y la regla no negociable de
`agentic/methodology/logging.md`).

Plantilla sugerida: [`../../agentic/templates/module-manual.template.md`](../../agentic/templates/module-manual.template.md).

```
modules/
├── facturacion.md
├── deposito.md
└── ...
```

> Nota: el generador de módulos del core (`/nuevo-modulo`) puede dejar acá el `.md` del
> módulo que crea; el código del módulo vive en `system/app/Modules/` (app, fuera del core).
