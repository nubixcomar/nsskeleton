# skills/ — Especificación de skills

Cada skill vive en su carpeta y contiene la **especificación detallada** que el
agente ejecuta.

```
skills/
└── {nombre}/
    └── SKILL.md      Rol · Entrada · Tarea · Reglas · Salida · Documentación
```

## Plantilla de un `SKILL.md`

```markdown
---
name: bug-detection
summary: Detecta y documenta bugs mediante análisis estático, sin modificar código.
generic: true            # true = portable; false = específico del negocio
---

## Rol
Quién es el agente y su objetivo en 1-2 líneas.

## Entrada
Qué recibe (módulo, archivo, scope).

## Tarea
Pasos concretos que ejecuta.

## Reglas
Restricciones (qué NO hacer). Incluye los "patrones prohibidos" si aplica.

## Salida
Qué produce y dónde lo registra (bugs-resume.md, logs, walkthrough).
```

## Skills genéricos planificados (a portar de nubixstore)
`bug-detection`, `check-resolved-bugs`, `regression-tester`, `security-audit`,
`performance-audit`, `report-generator`, `hotfix`, `refactor`, `code-documenter`,
`api-documenter`, `migration-analyst`, `ux-ui-specialist`.

## Skills de integración
- `ecommerce-integration` (`generic: true`) — integra con la API de cualquier tienda de
  ecommerce vía el conector genérico `StoreConnector`. Conocimiento:
  `../knowledge/ecommerce/ecommerce-apis.md`.
- `nubixstore-api` (`generic: false`) — dominio de la API de nubixstore (manual + versión).
  Conocimiento: `../knowledge/nubixstore/manual-api-nubixstore.md`.

> Los skills NO mencionan paths/clases concretos: eso vive en `../adapters/{stack}/`.
> Excepción: los skills de integración referencian el conector porque ES la interfaz
> que el skeleton entrega ya programada; el binding del stack vive igualmente en
> `../adapters/php-mvc/`.
