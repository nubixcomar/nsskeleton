---
name: plan
aliases: [pl]
usage: /plan <list|new|show|review|approve|run|status|close> [PL-NNN] [args]
spawns: [plan-architect, devils-advocate, architect, plan-executor]
---

## Qué hace
Crea, revisa y **ejecuta por fases** planes de implementación (`PL-`): casi pseudocódigo,
listos para que la ejecución sea mecánica y automatizable en loop.
Metodología: [`../methodology/roadmap-brief-plan.md`](../methodology/roadmap-brief-plan.md) (§2.3, §5.2, §7, §10).

> **Planificá con el modelo más capaz y esfuerzo máximo. Ejecutá con menos.**
> Antes de `new` y `review`, el agente recuerda esta recomendación al humano.

## Subcomandos
- `/plan` o `/plan list [estado|BR-NNN|RM-NNN]` — muestra el índice de planes.
- `/plan new [BR-NNN] [RM-NNN] ["<nombre>"] [--origen urgencia|imprevisto|mejora-técnica]` —
  `plan-architect` asigna el próximo código, crea `PL-NNN-<slug>.md` y lo completa:
  1. Si hay `BR-` y no está `✅ Listo`: **avisa** y sugiere `/brief refine` antes (el humano decide).
  2. Si no hay `BR-` y el trabajo es M o más grande: **sugiere** crear el brief; si el humano
     sigue igual, registra `exception` y `origin`.
  3. Lee el código actual y el [`core-manual`](../knowledge/core-manual.md) para reusar.
  4. Escribe diseño, decisiones, fases ejecutables, pruebas, riesgos y rollback.
  5. Deja el plan en `✏️ Borrador` con las preguntas que le hayan quedado (si quedan).
- `/plan show PL-NNN` — muestra el plan y el estado de cada fase.
- `/plan review PL-NNN` — revisión crítica no anclada: `devils-advocate` (huecos, supuestos,
  casos borde no cubiertos, trazabilidad con el brief) + `architect` (diseño, reuso, aislamiento).
  Registra hallazgos en "Revisión del plan"; `plan-architect` los resuelve. Estado `🔍 En revisión`.
  Idealmente en otra sesión u otro modelo que el que escribió el plan.
- `/plan approve PL-NNN` — verifica la puerta de calidad (§5.2). Si pasa → `👍 Aprobado`,
  `loop_ready: true` si todas las fases tienen formato ejecutable, y propaga al `BR-` (`🧭 Planificado`).
- `/plan run PL-NNN [F<n>|--next|--all]` — `plan-executor` ejecuta fases (por defecto `--next`):
  una fase por iteración según el contrato de la metodología (§10). `--all` encadena fases hasta
  terminar o hasta el primer bloqueo. Apto para correr dentro de un loop de la IA.
  Requiere plan `👍 Aprobado` (o `⚙️ En ejecución`); si no, sugiere `review`/`approve`.
- `/plan status PL-NNN` — fases hechas/pendientes/bloqueadas, desvíos y próximo paso.
- `/plan close PL-NNN` — verifica el cierre (fases, criterios del brief, tests, DoD), pasa a
  `✅ Completado`, actualiza `BR-`/`RM-` y, si el plan fue una excepción, **sugiere regularizar**
  (crear/vincular `RM-`).

## Proceso
1. Archivo + índice siempre sincronizados; estados propagados según la metodología (§4).
2. `run` registra cada fase en el "Registro de ejecución" del plan y cumple
   [`../methodology/logging.md`](../methodology/logging.md) citando `PL-NNN/F<n>`.
3. Logs: `logs/plan-architect.log` (new/review/approve/close) y `logs/plan-executor.log` (run).

## Helper del stack (si existe)
Para las operaciones **mecánicas** (próximo código, regenerar índices, validar coherencia,
sugerencias) usá el helper de planning del adapter en vez de editar los índices a mano:
ver `agentic/adapters/{stack}/conventions.md` § "Planning". En php-mvc:
`php system/console/planning.php <next|reindex|lint|review|sync|list|init>`.
Correlo **después de cada cambio de estado** (`sync`) para que archivo e índice no se desalineen.

## Restricciones
- `run` **no improvisa**: si falta información o el diseño no cierra, marca la fase `⛔`,
  anota el desvío y corta. La corrección se hace en el plan, no en el código.
- `run` no ejecuta fases de un plan en `✏️ Borrador` salvo orden explícita del humano
  (queda registrada como excepción).

## Ejemplos
`/plan new BR-007 RM-004`
`/plan new "Hotfix pagos duplicados" --origen urgencia`
`/pl review PL-012` → `/pl approve PL-012` → `/pl run PL-012 --all`
