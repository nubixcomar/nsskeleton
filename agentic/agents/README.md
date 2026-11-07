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
├── frontend/     UX/UI
├── product/      Descubrimiento: briefs (brief-discovery, creative-explorer, devils-advocate)
├── planning/     Roadmap y planes (roadmap-manager, plan-architect, plan-executor)
└── pm/           Sprints, releases, actualización de core
```

## Formato de un agente (`agents/{cat}/{nombre}.md`)

```markdown
---
name: bug-detection
aliases: [qa, bugs]
category: qa
skill: bug-detection           # → ../../skills/bug-detection/SKILL.md
model_hint: opus               # sugerencia, no obligatorio (agnóstico)
effort_hint: high              # opcional: low | medium | high | max (esfuerzo de razonamiento sugerido)
---

Breve descripción de cuándo usar este agente. La lógica detallada vive en el SKILL.
El agente SIEMPRE cumple la metodología de `agentic/methodology/`.
```

> Los agentes son **agnósticos a la IA**: `model_hint` y `effort_hint` son solo sugerencias
> que cada IA puede mapear (o ignorar). Criterio general (ver `methodology/roadmap-brief-plan.md` §7):
> **pensar caro** (brief, plan, revisión: modelo avanzado, esfuerzo alto/máximo) y
> **ejecutar barato** (implementación desde un plan: modelo estándar, esfuerzo medio).
