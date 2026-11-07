---
name: roadmap
aliases: [rm]
usage: /roadmap <init|list|new|show|update|review|sync> [RM-NNN] [args]
spawns: [roadmap-manager]
---

## Qué hace
Gestiona las **iniciativas del roadmap** (`RM-`): qué se va a construir y cuándo.
Metodología: [`../methodology/roadmap-brief-plan.md`](../methodology/roadmap-brief-plan.md).

## Subcomandos
- `/roadmap init` — crea `planning/` copiando del scaffold
  [`../templates/planning/`](../templates/planning/) **solo** `README.md` y los tres `INDEX.md`
  (las plantillas de ítem quedan en el core). Si `planning/` ya existe, no pisa nada. Si el
  proyecto tiene un `docs/roadmap.md` con contenido, **ofrece** migrar sus ítems a `RM-`.
- `/roadmap` o `/roadmap list [estado|prioridad]` — muestra el índice (filtrable).
- `/roadmap new "<nombre>" [--prioridad P1-P4] [--inicio YYYY-MM-DD] [--entrega YYYY-MM-DD]` —
  asigna el próximo código libre, crea `RM-NNN-<slug>.md` desde la plantilla y agrega la fila
  al índice. Pregunta lo mínimo que falte (objetivo, alcance macro, fechas tentativas).
- `/roadmap show RM-NNN` — muestra la especificación con sus briefs y planes vinculados.
- `/roadmap update RM-NNN [--estado …] [--inicio …] [--entrega …] [--prioridad …]` — actualiza
  archivo + índice. **Todo cambio de fecha o alcance agrega una fila al historial de
  re-planificación** y pide el motivo.
- `/roadmap review` — revisión de salud del roadmap y **sugerencias de la próxima fase**:
  - `RM-` en `📋`/`🟡` sin brief → sugerir `/brief new … RM-NNN`.
  - `RM-` con brief `✅ Listo` sin plan → sugerir `/plan new BR-NNN`.
  - Fechas de entrega vencidas o inicios pasados sin avance → proponer re-planificar.
  - `BR-`/`PL-` sin `RM-` (huérfanos) → proponer vincular o crear un `RM-` retroactivo.
  - `RM-` cuyos planes están todos `✅` → proponer `✅ Entregado`.
- `/roadmap sync` — reconstruye los tres `INDEX.md` a partir del front-matter de los archivos
  y reporta inconsistencias (códigos duplicados, vínculos rotos, estados que no propagaron).

## Proceso
1. Invoca el agente `roadmap-manager`.
2. Mantiene archivo e índice sincronizados; propaga estados según la metodología (§4).
3. Cumple [`../methodology/logging.md`](../methodology/logging.md) (log en `logs/roadmap-manager.log`).

## Helper del stack (si existe)
Para las operaciones **mecánicas** (próximo código, regenerar índices, validar coherencia,
sugerencias) usá el helper de planning del adapter en vez de editar los índices a mano:
ver `agentic/adapters/{stack}/conventions.md` § "Planning". En php-mvc:
`php system/console/planning.php <next|reindex|lint|review|sync|list|init>`.
Correlo **después de cada cambio de estado** (`sync`) para que archivo e índice no se desalineen.

## Restricciones
- No crea briefs ni planes por su cuenta: los **sugiere**.
- Nunca reutiliza ni renumera códigos. Un ítem descartado queda en el índice como `❌`.

## Ejemplos
`/roadmap new "Turnos online" --prioridad P2 --inicio 2026-12-01 --entrega 2027-01-15`
`/rm review`
