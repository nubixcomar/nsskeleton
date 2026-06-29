# agentic/ — Capa agéntica (fuente de verdad)

Esta carpeta es el **corazón agnóstico** de nsSkeleton: define *cómo se desarrolla*,
sin atarse a ninguna IA ni stack. El **instalador** la materializa al formato de la
IA elegida (ej. `.claude/` para Claude Code).

## Contenido

| Carpeta          | Rol                                                              |
|------------------|------------------------------------------------------------------|
| `rules/`         | Reglas en cascada: master → stack → features.                    |
| `agents/`        | Agentes por categoría (qa, audit, dev, docs, data, frontend).    |
| `skills/`        | Especificación detallada por skill: `{nombre}/SKILL.md`.         |
| `commands/`      | Comandos de orquestación: `comando → agente → skill`.            |
| `templates/`     | Plantillas (brief, roadmap, bug-report, walkthrough, manual...). |
| `methodology/`   | Sprints, bug-tracking y logging obligatorio.                     |
| `adapters/`      | Bindings por stack (paths/clases/convenciones). Default: php-mvc.|

## Regla de oro

- El **dominio** (rules, skills, agents, commands, methodology, templates) **NO**
  menciona paths, clases ni framework concretos.
- Lo específico del stack vive en `adapters/{stack}/`.
- Lo específico de la IA vive en `installer/targets/{ia}/` (no aquí).
