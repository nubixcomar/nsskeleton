# nsSkeleton

**Framework agéntico + esqueleto de sistema** para iniciar proyectos de software de forma acelerada, incluso sin conocimientos técnicos profundos.

`ns` = nubixstore · `Skeleton` = base/esqueleto sobre el que se construye cualquier sistema.

---

## ¿Qué es nsSkeleton?

nsSkeleton son **dos productos en uno**, deliberadamente separados:

1. **Capa agéntica** (`agentic/`) — *cómo se desarrolla*.
   Prompts, agentes, skills, comandos, templates, metodología (sprints, testing,
   bug-tracking, logging). Está escrita en **Markdown genérico y es 100% agnóstica
   a la IA**: no contiene carpetas propietarias (`.claude/`, etc.).

2. **Capa de sistema** (`system/`) — *qué viene preinstalado*.
   Un esqueleto de aplicación ya programado (login de administrador, gestión de
   perfiles, cron/tareas, emails, backups, gráficos, file manager, conector de IA)
   sobre el **stack por defecto: PHP puro (MVC propio) + MySQL + Tailwind/Alpine.js**.

Entre las dos está el **instalador** (`installer/`): un conjunto de preguntas que la
IA le hace al humano para definir stack, IA elegida y datos del proyecto, y luego
**materializa** la capa agéntica al formato de la IA seleccionada.

---

## Principio rector: agnóstico a la IA

```
AGENTS.md  ← archivo de arranque universal (lo lee la IA por defecto)
   │  deriva a ↓
agentic/   ← fuente de verdad (Markdown neutral, agnóstico)
```

El repositorio base **nunca** trae carpetas atadas a una IA concreta: **no hay
`.claude/` ni equivalentes**. Cualquier IA arranca leyendo
[`AGENTS.md`](AGENTS.md), que la deriva a `agentic/rules/rules.md` y a la metodología.
Si querés wirear una IA puntual a su propio archivo de arranque, es opt-in y es
decisión tuya.

---

## Estructura

```
nsSkeleton/
├── AGENTS.md       Archivo de arranque universal (la IA empieza acá)
├── landing/        Landing page de presentación + descarga (ZIP)
├── agentic/        Capa agéntica agnóstica (fuente de verdad)
│   ├── rules/        Reglas en cascada (master → stack → features)
│   ├── agents/       Agentes por categoría (qa, audit, dev, docs, data, frontend)
│   ├── skills/       Especificación detallada por skill ({nombre}/SKILL.md)
│   ├── commands/     Comandos de orquestación (/bug, /audit, /fix, /sprint...)
│   ├── templates/    Plantillas: brief, roadmap, bug-report, walkthrough...
│   ├── methodology/  Sprints, bug-tracking y logging obligatorio
│   └── adapters/     Bindings por stack (php-mvc por defecto)
├── installer/      Q&A + materializadores por IA (targets/)
├── system/         Esqueleto de sistema (PHP MVC + MySQL)
├── docs/           brief.md · roadmap.md · architecture.md · stack.md
├── logs/           Logs incrementales por agente + walkthroughs
├── .env.example    Plantilla de credenciales (DB, FTP, repos, deploy, IA)
└── README.md
```

---

## Cómo se usa (flujo previsto)

1. Cloná/descargá nsSkeleton como base de tu nuevo proyecto.
2. La IA ejecuta el **instalador**: te pregunta stack, IA a usar y datos del proyecto.
3. El instalador completa `docs/` y `.env`; la capa agéntica queda lista en `agentic/`
   y la IA la consume arrancando por `AGENTS.md` (sin generar carpetas propietarias).
4. Completás los archivos base: `docs/stack.md`, `docs/brief.md`, `docs/roadmap.md`, `.env`.
5. (Opcional) Instalás el **sistema base** ya programado (login admin, cron, mail, etc.).
6. Empezás a desarrollar con los agentes, que **siempre registran su trabajo** en `logs/`.

---

## Reglas no negociables para todo proyecto iniciado desde nsSkeleton

Todo agente, **siempre**, al finalizar una tarea:

- **a)** Crea un walkthrough `YYYY-MM-DD_HHMM_<agente>_<feature>.md` en `logs/walkthrough/`.
- **b)** Agrega una línea de síntesis a su log incremental `logs/<agente>.log` (append-only).
- **c)** Guarda los informes/walkthrough de lo realizado (nunca se sobrescriben).

Ver detalle en [`agentic/methodology/logging.md`](agentic/methodology/logging.md).

---

## Estado del proyecto

✅ **Versión 1.4.0 — roadmap v1.1→v1.4 completo** (137 tests verdes, verificado contra
MySQL). Sobre el MVP (núcleo MVC, login/perfiles, cron, emails, backups, gráficos, file
manager, IA) se sumó: tests + cifrado + hardening de login + reset password + headers +
assets locales (v1.1); generador de módulos CRUD + rollback + búsqueda/paginación +
settings + auditoría + sprint/release (v1.2); API REST + RBAC + cron jobs + cola de
emails + file manager+ + IA prompts (v1.3); instalador + seeders demo + módulo showcase +
deploy (v1.4). Ver [`docs/CHANGELOG.md`](docs/CHANGELOG.md).

Puesta en marcha: ver [`docs/INSTALL.md`](docs/INSTALL.md). Avance: [`docs/roadmap.md`](docs/roadmap.md).
