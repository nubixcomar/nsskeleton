# agents/ — Agentes

Cada agente es un **wrapper fino** sobre un skill. Categorías:

```
agents/
├── INDEX.md      Catálogo maestro (ver para la lista completa)
├── qa/           Testing y detección de bugs
├── audit/        Auditorías (seguridad, performance, reportes)
├── dev/          Desarrollo (architect, backend, web, hotfix, refactor)
├── docs/         Documentación de código y APIs
├── data/         Datos y base de datos
└── frontend/     UX/UI
```

## Formato de un agente (`agents/{cat}/{nombre}.md`)

```markdown
---
name: bug-detection
aliases: [qa, bugs]
category: qa
skill: bug-detection           # → ../../skills/bug-detection/SKILL.md
model_hint: opus               # sugerencia, no obligatorio (agnóstico)
---

Breve descripción de cuándo usar este agente. La lógica detallada vive en el SKILL.
El agente SIEMPRE cumple la metodología de `agentic/methodology/`.
```

> Los agentes son **agnósticos a la IA**: `model_hint` es solo una sugerencia que el
> target del instalador puede mapear (o ignorar) según la IA elegida.
