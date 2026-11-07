# planning/ — Roadmap → Brief → Plan

> Carpeta del **proyecto** (el actualizador de core nunca la toca). Metodología completa:
> [`agentic/methodology/roadmap-brief-plan.md`](../agentic/methodology/roadmap-brief-plan.md).

**Primero se entiende, después se decide, al final se codea.**

| Carpeta | Prefijo | Responde | Índice |
|---------|---------|----------|--------|
| [`roadmap/`](roadmap/) | `RM-` | ¿Qué y cuándo? | [`roadmap/INDEX.md`](roadmap/INDEX.md) |
| [`briefs/`](briefs/) | `BR-` | ¿Por qué y para quién? ¿Qué duele? | [`briefs/INDEX.md`](briefs/INDEX.md) |
| [`plans/`](plans/) | `PL-` | ¿Cómo exactamente? (fases + pseudocódigo) | [`plans/INDEX.md`](plans/INDEX.md) |

## Comandos
- `/roadmap` (`/rm`): `list` · `new` · `show` · `update` · `review` · `sync`
- `/brief` (`/br`): `list` · `new` · `show` · `refine` · `ready` · `discard`
- `/plan` (`/pl`): `list` · `new` · `show` · `review` · `approve` · `run` · `status` · `close`

## Reglas rápidas
- Códigos correlativos de 3 dígitos, **nunca se reutilizan**. Archivo: `<CÓDIGO>-<slug>.md`.
- Cada cambio de estado se refleja en el archivo **y** en su `INDEX.md`.
- Trabajo de tamaño **M** → plan obligatorio. Tamaño **L/XL** → RM + BR + PL.
- Saltear una fase (urgencias, imprevistos) está permitido; **ocultarlo no**: se anota en
  `origin`/`exception` y se regulariza al cerrar.
- El plan se escribe con el modelo más capaz y esfuerzo máximo; la ejecución, con menos.
