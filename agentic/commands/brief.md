---
name: brief
aliases: [br]
usage: /brief <list|new|show|refine|ready|discard> [BR-NNN] [args]
spawns: [brief-discovery, creative-explorer, devils-advocate]
---

## Qué hace
Construye **briefs de iniciativa** (`BR-`) conversando con el humano: problemas, dolores,
experiencias del cliente y necesidades concretas, hasta que **no queden dudas bloqueantes**
para planificar. Metodología: [`../methodology/roadmap-brief-plan.md`](../methodology/roadmap-brief-plan.md) (§2.2, §5.1, §6).

> Recomendado: **modelo avanzado, esfuerzo alto.** Entender personas y preguntar bien es difícil.

## Subcomandos
- `/brief` o `/brief list [estado|RM-NNN]` — muestra el índice de briefs.
- `/brief new "<nombre>" [RM-NNN]` — asigna el próximo código, crea `BR-NNN-<slug>.md` desde la
  plantilla y **arranca la indagación**:
  1. `brief-discovery` pide el relato libre del problema y hace la **ronda 1** de preguntas.
  2. Con las respuestas, completa las secciones y abre las preguntas que falten.
  3. Si no se pasó `RM-`, pregunta si corresponde a una iniciativa existente o si conviene
     crear una (sugerencia, no obligación).
- `/brief show BR-NNN` — muestra el brief, sus preguntas abiertas y su avance contra la
  Definition of Ready.
- `/brief refine BR-NNN [--explorar] [--cuestionar]` — nueva ronda de indagación:
  - sin flags: `brief-discovery` hace la siguiente ronda de preguntas;
  - `--explorar`: pasada de `creative-explorer` (alternativas, "¿y si…?");
  - `--cuestionar`: pasada de `devils-advocate` (supuestos, riesgos, casos borde).
- `/brief ready BR-NNN` — chequea la Definition of Ready. Si la cumple → `✅ Listo` y
  **sugiere** `/plan new BR-NNN`. Si no, lista exactamente qué falta.
- `/brief discard BR-NNN "<motivo>"` — `❌ Descartado` con motivo (el código no se reutiliza).

## Proceso
1. `brief-discovery` conduce y es dueño del archivo; invoca a `creative-explorer` y
   `devils-advocate` al menos una vez cada uno antes de declarar el brief `✅ Listo`.
2. Cada ronda queda en el **registro de entrevista**; cada respuesta actualiza las secciones.
3. Estado: `✏️ Borrador` al crear → `❓ En indagación` mientras haya preguntas bloqueantes →
   `✅ Listo`. Archivo + índice siempre sincronizados (y el `RM-` vinculado, si hay).
4. Cumple [`../methodology/logging.md`](../methodology/logging.md) (log en `logs/brief-discovery.log`).

## Helper del stack (si existe)
Para las operaciones **mecánicas** (próximo código, regenerar índices, validar coherencia,
sugerencias) usá el helper de planning del adapter en vez de editar los índices a mano:
ver `agentic/adapters/{stack}/conventions.md` § "Planning". En php-mvc:
`php system/console/planning.php <next|reindex|lint|review|sync|list|init>`.
Correlo **después de cada cambio de estado** (`sync`) para que archivo e índice no se desalineen.

## Restricciones
- **No** propone soluciones técnicas en el cuerpo del brief (van en "Ideas para el plan").
- **No** inventa respuestas: lo que el humano no sabe queda como supuesto explícito.
- Máximo 5–7 preguntas por ronda, priorizadas (bloqueantes primero), con opciones cuando se pueda.

## Ejemplos
`/brief new "Pacientes no pueden reprogramar turnos" RM-004`
`/br refine BR-007 --cuestionar`
`/brief ready BR-007`
