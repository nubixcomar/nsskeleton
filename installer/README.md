# installer/ — Instalador (Q&A + materialización por IA)

El instalador NO es un script: es un **guion de preguntas y respuestas** que la IA
ejecuta al iniciar un proyecto nuevo desde nsSkeleton.

> **No genera carpetas propietarias** (`.claude/` ni equivalentes). La capa agéntica
> se consume directamente desde `agentic/` a través del archivo raíz `AGENTS.md`.

## Flujo

```
1. La IA lee installer/questions.yml
2. Le hace las preguntas al humano (proyecto, stack, IA, infra)
3. Con las respuestas:
   ├── completa docs/stack.md, docs/brief.md
   ├── copia .env.example → .env y precarga valores
   ├── escribe app-agentic/rules/app-rules.md (reglas del proyecto; el core no se toca)
   ├── (opcional) prepara el sistema base en system/
   └── registra logs/walkthrough/<fecha>_installer_setup.md
4. La IA arranca leyendo AGENTS.md (no hay nada que "materializar").
```

## Estructura

```
installer/
├── questions.yml          Las preguntas del Q&A
├── targets/               Punteros opt-in por IA (NO generan formato propietario)
│   ├── claude-code/       Cómo (opcional) apuntar Claude Code a AGENTS.md
│   └── openai/            Cómo (opcional) apuntar OpenAI/ChatGPT a AGENTS.md
└── README.md
```

## Principio

`agentic/` es la **fuente de verdad** y `AGENTS.md` el punto de entrada. Los `targets/`
son solo guías **opt-in** para quien quiera wirear su IA a un archivo de arranque
propio; jamás duplican `agentic/`. Soportar otra IA = documentar su puntero a
`AGENTS.md`, sin tocar `agentic/`.
