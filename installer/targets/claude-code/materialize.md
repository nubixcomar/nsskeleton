# Target: Claude Code — Puntero de arranque (opt-in)

> **El base de nsSkeleton NO genera `.claude/`.** La capa agéntica se consume
> directamente desde `agentic/` a través del archivo raíz `AGENTS.md`.
>
> Este documento es solo para quien **opta** por wirear Claude Code a su archivo de
> arranque propio. Es opcional y queda del lado del usuario.

## Qué leer por defecto

Claude Code (y la mayoría de las IAs) puede leer `AGENTS.md` en la raíz. Ese archivo
ya deriva a `agentic/rules/rules.md`, la metodología y `docs/`. **En la mayoría de
los casos no hace falta nada más.**

## Si querés un puntero específico de Claude Code

Algunas configuraciones de Claude Code buscan `CLAUDE.md`. Si ese es tu caso, creá un
`CLAUDE.md` mínimo en la raíz que **solo redirige** a `AGENTS.md` (no dupliques
contenido):

```markdown
# Guía para Claude Code

Este proyecto es agnóstico a la IA. Empezá leyendo `AGENTS.md` en la raíz y seguí
el orden de lectura que indica (agentic/rules/rules.md → methodology → docs).
Al cerrar cualquier tarea, cumplí el checklist de `agentic/methodology/logging.md`.
```

## Lo que NO se hace

- ❌ No se crea el árbol `.claude/agents`, `.claude/skills`, etc.
- ❌ No se copia ni duplica `agentic/` a un formato propietario.
- ✅ Toda la verdad vive en `agentic/`; la IA la lee desde ahí.
