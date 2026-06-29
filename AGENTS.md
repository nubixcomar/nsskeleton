# AGENTS.md — Punto de arranque agnóstico

> **Leé esto primero.** Este es el archivo de entrada universal de nsSkeleton (y de
> cualquier proyecto derivado). Lo leen por defecto la mayoría de las herramientas
> de IA. Si tu IA usa otro archivo de arranque (ej. otro nombre), redirigílo a este.
>
> nsSkeleton es **agnóstico a la IA**: NO usamos carpetas propietarias (`.claude/`,
> etc.). Toda la capa agéntica vive en Markdown neutral dentro de `agentic/`.

## Core vs proyecto (leer primero)

nsSkeleton separa **core** (lo que mantiene/actualiza el framework) de **proyecto** (tu sistema):
- `agentic/` y el set de `system/` listado en `core-lock.json` = **core**. Se actualizan con el
  actualizador de core; **no los edites**.
- `app-agentic/` + lo que programás encima = **proyecto**. El actualizador **nunca lo toca**.
- ¿Necesitás cambiar algo del core? **Overridealo** desde `app-agentic/` (reglas, agentes,
  skills, plantillas). Así el update de core queda limpio. Detalle: `core-manifest.json`.

## Por dónde empezar (orden de lectura obligatorio)

1. **Reglas** → [`agentic/rules/rules.md`](agentic/rules/rules.md)
   - Encadena las del **core** [`core-rules.md`](agentic/rules/core-rules.md) y luego las del
     **proyecto** [`app-agentic/rules/app-rules.md`](app-agentic/rules/app-rules.md)
     (**app > core** en conflicto), más `new-features-rules.md`.
2. **Metodología (obligatoria)** → [`agentic/methodology/`](agentic/methodology/)
   - [`logging.md`](agentic/methodology/logging.md) — trazabilidad obligatoria de cada agente.
   - [`bug-tracking.md`](agentic/methodology/bug-tracking.md) — ciclo de vida de bugs.
   - [`sprints.md`](agentic/methodology/sprints.md) — sprints y Definición de Hecho.
3. **Contexto del proyecto** → [`docs/`](docs/)
   - [`brief.md`](docs/brief.md) (qué se construye), [`stack.md`](docs/stack.md),
     [`roadmap.md`](docs/roadmap.md), [`architecture.md`](docs/architecture.md).
4. **Manual del core (qué viene preinstalado y cómo usarlo)** →
   [`agentic/knowledge/core-manual.md`](agentic/knowledge/core-manual.md) — referencia de
   TODOS los módulos de `system/` (núcleo MVC, auth, cron/jobs, mail, backup, IA,
   ecommerce, archivos…) con API y ejemplos. Leelo antes de programar sobre el esqueleto.
5. **Antes de tocar un módulo**: leé su doc en `docs/modules/` (si existe).

## Catálogo agéntico

Core (`agentic/`) — buscá primero en el proyecto (`app-agentic/`), que tiene prioridad:
- **Agentes** → [`agentic/agents/INDEX.md`](agentic/agents/INDEX.md) · proyecto: `app-agentic/agents/`
- **Skills** → [`agentic/skills/`](agentic/skills/) (`{nombre}/SKILL.md`) · proyecto: `app-agentic/skills/`
- **Comandos** → [`agentic/commands/`](agentic/commands/)
- **Plantillas** → [`agentic/templates/`](agentic/templates/) · proyecto: `app-agentic/templates/`
- **Knowledge** → [`agentic/knowledge/`](agentic/knowledge/) · proyecto: `app-agentic/knowledge/`
- **Adapter del stack actual** → [`agentic/adapters/`](agentic/adapters/)
- **Capa del proyecto** → [`app-agentic/`](app-agentic/) (reglas, agentes, skills, knowledge, módulos)

## Regla no negociable

Al **cerrar cualquier tarea**, cumplí el checklist de
[`agentic/methodology/logging.md`](agentic/methodology/logging.md):
walkthrough en `logs/walkthrough/`, línea en `logs/<agente>.log`, e informes guardados.

## Sistema base

El esqueleto de aplicación (login admin, cron, mail, backup, charts, files, conector
IA) vive en [`system/`](system/). Ver [`system/README.md`](system/README.md).
