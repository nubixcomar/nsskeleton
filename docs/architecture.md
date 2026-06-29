# Arquitectura de nsSkeleton

## Visión general

nsSkeleton separa explícitamente **tres capas** que no deben mezclarse:

```
┌──────────────────────────────────────────────────────────────┐
│  agentic/   CAPA AGÉNTICA (agnóstica a la IA)                 │
│  La "fuente de verdad" de CÓMO se desarrolla.                │
│  Markdown neutral: rules, agents, skills, commands,          │
│  templates, methodology, adapters.                            │
└──────────────────────────────────────────────────────────────┘
                         │
                         │  AGENTS.md  (archivo de arranque universal)
                         ▼
        Cualquier IA lee AGENTS.md → agentic/rules/rules.md → methodology → docs
        (NO se generan carpetas propietarias: sin .claude/ ni equivalentes)

┌──────────────────────────────────────────────────────────────┐
│  system/    CAPA DE SISTEMA (esqueleto ya programado)        │
│  QUÉ viene preinstalado. PHP MVC propio + MySQL.            │
│  login admin, cron, mail, backup, charts, files, IA.        │
└──────────────────────────────────────────────────────────────┘
```

## 1. Capa agéntica (`agentic/`)

Hereda de nubixstore el patrón **Hexagonal (dominio ↔ adapter)**, pero lo eleva un
nivel para lograr independencia de la IA:

- **Dominio portable**: `rules/`, `skills/`, `agents/`, `commands/`, `methodology/`,
  `templates/`. No menciona ninguna IA ni stack concreto.
- **Adapters de stack**: `adapters/{stack}/` mapea conceptos genéricos a paths,
  clases y convenciones de un stack concreto (por defecto `php-mvc`).
- **Arranque de IA**: el archivo raíz `AGENTS.md` es el punto de entrada universal
  que cualquier IA lee por defecto y que la deriva al resto de `agentic/`. No se
  generan carpetas propietarias por IA.

### Arquitectura de agentes (2 capas, portada de nubixstore)
1. **Skill** (`skills/{nombre}/SKILL.md`): especificación detallada — rol, entrada,
   tarea, reglas, salida.
2. **Agente** (`agents/{cat}/{nombre}.md`): wrapper fino que invoca el skill con
   metadatos (categoría, alias, modelo sugerido).
3. **Comando** (`commands/{nombre}.md`): orquesta uno o más agentes. Flujo:
   `comando → agente → skill`.

## 2. El instalador (`installer/`)

Implementa el requisito de "instalador como Q&A":

1. Lee `installer/questions.yml` (stack, IA, datos del proyecto).
2. La IA le hace esas preguntas al humano.
3. Con las respuestas, completa `docs/stack.md`/`docs/brief.md`, prepara `.env` y
   ajusta el adapter de stack. La capa agéntica se consume directo desde `agentic/`
   vía `AGENTS.md` — **no se genera ninguna carpeta propietaria**.
4. (Opcional, opt-in) Si el humano quiere wirear su IA a su archivo de arranque
   propio, `installer/targets/{ia}/` documenta cómo crear ese puntero mínimo a
   `AGENTS.md`. No es parte del base.

## 3. Capa de sistema (`system/`)

Esqueleto MVC propio en PHP. Estructura prevista:

```
system/
├── public/         Front controller (index.php) + assets compilados
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Views/
│   ├── Services/    (mail, backup, cron, ai, files)
│   └── Core/        (Router, Request, Response, DB, Auth, View)
├── config/         Configuración (lee .env)
├── database/
│   ├── migrations/  SQL versionado
│   └── seeds/
└── storage/        uploads/ cache/ backups/ logs/
```

Módulos del sistema base (implementados): login admin + RBAC + 2FA, cron + cola de
jobs, mail (SMTP + cola), backup/restore, charts, file manager + shares, export
CSV/Excel/PDF, conector IA, webhooks, ecommerce, auditoría, feature flags, health.

> 📖 **Manual del core** (API + ejemplos de cada módulo, y cómo construir uno nuevo):
> [`../agentic/knowledge/core-manual.md`](../agentic/knowledge/core-manual.md).

## Trazabilidad obligatoria

Transversal a todo: cada agente registra su trabajo en `logs/` según
[`methodology/logging.md`](../agentic/methodology/logging.md). Esto NO es opcional.
