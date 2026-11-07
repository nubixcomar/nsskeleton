# Reglas del agente (Master Rules)

> Punto de entrada de las reglas. Todo agente las carga al inicio de una tarea.
> Portado y generalizado de nubixstore.

## 1. Carga de reglas (en orden, core → app)
1. Leer estas Master Rules.
2. Leer las reglas del **core**: [`core-rules.md`](core-rules.md) (stack, convenciones, seguridad, performance).
3. Leer las reglas del **proyecto**: [`../../app-agentic/rules/app-rules.md`](../../app-agentic/rules/app-rules.md)
   (si existe). **En conflicto con el core, gana el proyecto (app > core).**
4. Si el stack tiene adapter, leer `../adapters/{stack}/conventions.md`.
5. Antes de tocar un módulo de negocio, buscar y leer su doc en `../../app-agentic/modules/`
   (o `docs/modules/`).

## 1b. Core vs proyecto (frontera y override)
- `agentic/` es **core** (lo actualiza nsSkeleton); `app-agentic/` es del **proyecto** (jamás
  se pisa). **No edites archivos de `agentic/`**: overridealos desde `app-agentic/`.
- **Override por nombre**: un agente o skill en `app-agentic/` con el mismo `name` que uno de
  `agentic/` lo **reemplaza** para este proyecto. Al resolver un skill/agente, buscá primero
  en `app-agentic/`, luego en `agentic/`.

## 2. Metodología (obligatoria)
- Logging y trazabilidad: [`../methodology/logging.md`](../methodology/logging.md).
- Bugs: [`../methodology/bug-tracking.md`](../methodology/bug-tracking.md).
- Sprints y DoD: [`../methodology/sprints.md`](../methodology/sprints.md).
- Flujo Roadmap → Brief → Plan: [`../methodology/roadmap-brief-plan.md`](../methodology/roadmap-brief-plan.md).

## 2b. Filosofía de trabajo: entender → decidir → codear (RM → BR → PL)
- Todo trabajo de tamaño **M** o mayor se implementa **desde un plan** (`PL-`). Tamaño **L/XL**
  además requiere iniciativa en el roadmap (`RM-`) y brief (`BR-`). XS/S (bugs, ajustes) no.
- Antes de codear algo M+ sin plan, el agente **sugiere** la fase que falta (`/brief new`,
  `/plan new`) con el comando listo. Si el humano decide seguir igual, se registra la excepción
  (`origin`/`exception` del plan) y se avanza. **Sugerir, no bloquear; saltear sí, ocultar no.**
- **Pensar caro, ejecutar barato**: brief y plan con el modelo más capaz y esfuerzo alto/máximo;
  la ejecución de un plan aprobado, con un modelo estándar. Si el ejecutor necesita "pensar
  mucho", el plan está incompleto: se para y se re-planifica.
- Los índices `planning/{roadmap,briefs,plans}/INDEX.md` son la fuente de verdad de qué se
  hace, por qué y cómo. Cada cambio de estado se refleja en el archivo **y** en su índice.

## 3. Sistema de skills
- Consultar [`../agents/INDEX.md`](../agents/INDEX.md) (core) y `../../app-agentic/agents/`
  (proyecto) para saber qué skill activar. **app-agentic tiene prioridad** (override por nombre).
- El skill complementa las reglas del proyecto; no las reemplaza.

## 4. Comportamiento autónomo
- Si falta una definición de negocio, **preguntar antes de improvisar**.
- Si se acuerda una regla nueva con el humano, proponer guardarla en el `.md` que corresponda:
  reglas del **proyecto** → `app-agentic/rules/app-rules.md`; nunca en archivos del core.
- No refactorizar "de paso": ver [`new-features-rules.md`](new-features-rules.md).

## 5. Cierre de tarea
- Cumplir SIEMPRE el checklist de cierre de [`../methodology/logging.md`](../methodology/logging.md).
