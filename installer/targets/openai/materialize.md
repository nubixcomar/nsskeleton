# Target: OpenAI / ChatGPT — Puntero de arranque (opt-in)

> **El base de nsSkeleton NO genera carpetas ni configs propietarias.** La capa
> agéntica se consume desde `agentic/` vía el archivo raíz `AGENTS.md`.
>
> Este documento es para quien **opta** por wirear un flujo con OpenAI/ChatGPT. Es
> opcional.

## Qué leer por defecto

`AGENTS.md` (raíz) es el punto de entrada universal y deriva a `agentic/rules/rules.md`,
la metodología y `docs/`. Cualquier flujo (Assistants API, "GPTs", scripts propios,
Codex) debería arrancar apuntando a `AGENTS.md`.

## Si necesitás un puntero propio

Si tu herramienta usa otro archivo de arranque, hacé que su instrucción de sistema
incluya: *"Leé `AGENTS.md` en la raíz y seguí su orden de lectura"*. No dupliques el
contenido de `agentic/`.

## Conector de IA en runtime (distinto de esto)

Ojo: el **conector de IA del sistema base** (que la app usa en runtime para hablar con
OpenAI/Deepseek) es otra cosa, y vive en `system/app/Services/` con credenciales en
`.env` (`AI_PROVIDER`, `AI_API_KEY`). Esto de acá es solo el arranque agéntico.
