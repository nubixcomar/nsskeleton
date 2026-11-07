# Metodología: Roadmap → Brief → Plan (RM → BR → PL)

> **Filosofía de trabajo, no burocracia.** Es la guía espiritual de cómo se avanza en un
> proyecto nsSkeleton, sin importar el producto, el stack o el tamaño del equipo:
> **primero se entiende, después se decide, al final se codea.**
>
> El patrón de diseño (MVC, capas, etc.) dice *cómo se escribe el código*. Esta metodología
> dice *cómo se llega a saber qué código escribir*.

---

## 1. Principios

1. **Pensar caro, ejecutar barato.** El esfuerzo intelectual (y el modelo más capaz) se
   invierte en entender el problema y en planificar. Si el plan es bueno, codear es casi
   mecánico, se hace con menos esfuerzo y se puede automatizar en loop.
2. **Las dudas se despejan antes, no durante.** Una duda que aparece mientras se codea cuesta
   10 veces más que la misma duda en un brief. El brief existe para que el plan no tenga dudas;
   el plan existe para que la ejecución no tenga decisiones.
3. **Todo tiene código y estado.** Cada iniciativa, brief y plan tiene un identificador
   (`RM-`, `BR-`, `PL-`) y un estado visible en un índice. Lo que no está en un índice no existe.
4. **Trazabilidad, no rigidez.** La cadena ideal es `RM → BR → PL → código`, pero la realidad
   trae urgencias, imprevistos y cambios. Se permite saltear fases **dejándolo explícito**
   y regularizando después. Nunca en silencio.
5. **Sugerir, no bloquear.** Los agentes **proponen** la fase que falta ("esto parece tamaño L,
   ¿armamos un brief primero?"). Si el humano decide seguir sin ella, se registra la excepción
   y se sigue.

---

## 2. Los tres artefactos

| Artefacto | Prefijo | Pregunta que responde | Quién lo produce | Vive en |
|-----------|---------|-----------------------|------------------|---------|
| **Roadmap** | `RM-` | **¿Qué** vamos a construir y **cuándo**? | `roadmap-manager` + humano | `planning/roadmap/` |
| **Brief** | `BR-` | **¿Por qué** y **para quién**? ¿Qué duele, qué se necesita? | `brief-discovery` (+ `creative-explorer`, `devils-advocate`) | `planning/briefs/` |
| **Plan** | `PL-` | **¿Cómo** exactamente se implementa? | `plan-architect` (+ `devils-advocate`, `architect`) | `planning/plans/` |

Después del plan viene la **ejecución** (`plan-executor` → `dev-backend`/`dev-web`/…), que ya
existe en el core y sigue gobernada por [`logging.md`](logging.md), [`bug-tracking.md`](bug-tracking.md)
y [`sprints.md`](sprints.md).

```
 RM-004 "Turnos online"            ← iniciativa: qué y cuándo (fechas tentativas)
   └─ BR-007 "Pacientes no pueden reprogramar"   ← problema, dolores, necesidades, sin dudas
        └─ PL-012 "Reprogramación self-service"   ← diseño + fases + pseudocódigo
             ├─ F1 migración + modelo      ─┐
             ├─ F2 servicio + reglas        ├─ ejecución por fases (loop guiado)
             └─ F3 UI + notificaciones     ─┘
```

### 2.1 Roadmap (`RM-`)
Iniciativas de producto a nivel macro. No dice cómo, dice **qué y cuándo**.
- **Índice sintético**: `planning/roadmap/INDEX.md` — código, nombre, estado, prioridad,
  inicio tentativo, entrega tentativa, briefs y planes vinculados.
- **Especificación**: `planning/roadmap/RM-NNN-<slug>.md` — objetivo/valor, alcance macro,
  dependencias, hitos y **historial de re-planificación** (cada vez que se mueve una fecha,
  se anota cuándo, de qué a qué y por qué).

### 2.2 Brief (`BR-`)
Un **borrador vivo** que cuenta el problema desde el lado humano: problemáticas, dolores,
experiencias del cliente, necesidades concretas, contexto, criterios de éxito, alcance,
reglas de negocio, casos borde, riesgos y **preguntas abiertas**. Es agnóstico a la
tecnología (qué y por qué, nunca cómo).
- Opcionalmente vinculado a un `RM-`.
- Lo construyen **agentes curiosos y creativos que preguntan** (ver §6).
- Termina cuando no quedan **dudas bloqueantes** para planificar (Definition of Ready).

> No confundir con `docs/brief.md`: ese es el **brief maestro del producto** (visión general).
> Los `BR-` son briefs **de iniciativa/feature**, muchos y puntuales.

### 2.3 Plan (`PL-`)
La especificación de implementación, **casi pseudocódigo**: contexto técnico actual, qué se
reusa del core, diseño (datos, contratos, flujos), decisiones y alternativas descartadas,
**fases ejecutables** con archivos, pseudocódigo, criterios de aceptación verificables y
comando de verificación, plan de pruebas, riesgos, rollback y recomendación de ejecución.
- Vinculado a un `BR-` y/o un `RM-` (ambos opcionales, ver §4).
- Se escribe con el **modelo más capaz y esfuerzo máximo** (ver §7).
- Un plan aprobado debe poder ejecutarlo otro agente, en otra sesión, sin hacer preguntas.

---

## 3. Codificación, archivos e índices

- **Formato de código**: prefijo + 3 dígitos correlativos: `RM-001`, `BR-014`, `PL-120`.
  Un código **nunca se reutiliza**, aunque el ítem se descarte (queda en el índice como ❌).
- **Archivo**: `<CÓDIGO>-<slug-kebab>.md` (ej. `planning/briefs/BR-007-reprogramar-turnos.md`).
- **Front-matter obligatorio** en cada archivo (lo leen agentes y, a futuro, linters):
  `code`, `title`, `status`, vínculos (`rm`, `brief`, `plans`…), fechas y `updated`.
- **Índices**: cada carpeta tiene su `INDEX.md` con una fila por ítem. El índice es la vista
  rápida; el archivo es la fuente de detalle. **Cada cambio de estado se refleja en ambos.**
- Estructura en el proyecto (capa de proyecto; el actualizador de core nunca la toca):

```
planning/
├── README.md                     Cómo se usa esta carpeta (copiado del scaffold)
├── roadmap/
│   ├── INDEX.md                  Índice sintético RM
│   └── RM-001-<slug>.md          Especificación de cada iniciativa
├── briefs/
│   ├── INDEX.md                  Índice BR
│   └── BR-001-<slug>.md
└── plans/
    ├── INDEX.md                  Índice PL
    └── PL-001-<slug>.md
```

- Scaffold y plantillas: [`../templates/planning/`](../templates/planning/) (override por
  proyecto en `app-agentic/templates/planning/`). Se inicializa con `/roadmap init`.
- **Helper determinístico** (opcional, lo provee el adapter del stack): calcula el próximo código,
  regenera los índices desde el front-matter y valida la coherencia (`lint`). Ver
  `../adapters/{stack}/conventions.md` § "Planning". Los agentes deciden; el helper evita que
  los índices se desalineen en loops largos.

---

## 4. Estados

### Roadmap (`RM-`)
`💡 Idea` → `📋 Planificado` → `🟡 En curso` → `✅ Entregado`
Laterales: `⏸️ Pausado` · `⛔ Bloqueado` · `❌ Descartado`

### Brief (`BR-`)
`✏️ Borrador` → `❓ En indagación` → `✅ Listo` → `🧭 Planificado`
Laterales: `⏸️ Pausado` · `❌ Descartado`
- **Listo** = cumple la Definition of Ready del brief (§5.1).
- **Planificado** = tiene al menos un `PL-` vinculado y aprobado.

### Plan (`PL-`)
`✏️ Borrador` → `🔍 En revisión` → `👍 Aprobado` → `⚙️ En ejecución` → `✅ Completado`
Laterales: `⛔ Bloqueado` · `🔁 Reemplazado` (por otro PL) · `❌ Descartado`

### Fases dentro de un plan
`📋 pendiente` · `⚙️ en curso` · `✅ hecha` · `⛔ bloqueada` · `⏭️ omitida (con motivo)`

### Propagación de estados (la hace el agente que cambia el estado)
- Primer `PL-` pasa a `⚙️ En ejecución` → su `RM-` pasa a `🟡 En curso` (si estaba antes).
- `PL-` `✅ Completado` → el `BR-` queda `🧭 Planificado` (o se cierra si todo su alcance quedó
  cubierto); si **todos** los planes del `RM-` están completos → proponer `RM-` `✅ Entregado`.
- Cambio de fecha de un `RM-` → fila nueva en su historial de re-planificación.

---

## 5. Puertas de calidad (Definition of Ready)

### 5.1 Un brief está **Listo** cuando
- [ ] El problema está formulado en una o dos frases y se entiende sin contexto previo.
- [ ] Están identificados los actores/usuarios afectados y su experiencia actual.
- [ ] Hay criterios de éxito observables (cómo sabremos que se resolvió).
- [ ] Alcance explícito: qué **incluye** y qué **NO incluye**.
- [ ] Reglas de negocio y casos borde relevantes están escritos.
- [ ] Se exploraron alternativas (pasada de `creative-explorer`).
- [ ] Se cuestionaron supuestos y riesgos (pasada de `devils-advocate`).
- [ ] **Cero preguntas abiertas bloqueantes** (las no bloqueantes quedan anotadas como tales).

### 5.2 Un plan está **Aprobado** cuando
- [ ] Traza cada criterio de éxito del brief (si hay brief) a una fase o prueba.
- [ ] Revisó qué ya resuelve el core/proyecto y lo reusa ([`../knowledge/core-manual.md`](../knowledge/core-manual.md)).
- [ ] Cada fase tiene: objetivo, archivos a tocar, pseudocódigo, criterios de aceptación
      verificables y **comando de verificación**.
- [ ] Las fases son pequeñas, ordenadas, con dependencias explícitas, y dejan el sistema
      funcionando al terminar cada una.
- [ ] Tiene plan de pruebas, riesgos con mitigación y rollback (si toca datos).
- [ ] Respeta el aislamiento de features ([`../rules/new-features-rules.md`](../rules/new-features-rules.md)).
- [ ] Pasó una revisión crítica (`/plan review`) y no le quedan preguntas abiertas.
- [ ] El humano lo aprobó (`/plan approve`).

---

## 6. Los agentes del brief: curiosidad antes que solución

El brief no se "redacta": **se descubre conversando**. Lo conduce `brief-discovery`, que
alterna tres miradas:

| Agente | Actitud | Aporta |
|--------|---------|--------|
| `brief-discovery` | **Curioso**, empático, entrevistador | Entiende el dolor real, hace preguntas en rondas cortas, ofrece hipótesis para que responder sea fácil, consolida el brief. |
| `creative-explorer` | **Creativo**, lateral | "¿Y si…?", alternativas, oportunidades no pedidas, otras formas de resolver el mismo dolor (incluido "no construir nada"). |
| `devils-advocate` | **Escéptico** constructivo | Supuestos no validados, riesgos, casos borde, contradicciones, lo que puede salir mal, qué se deja afuera. |

Reglas del descubrimiento:
- Preguntar **por rondas** (5–7 preguntas como máximo), priorizadas: primero las bloqueantes.
- Cada pregunta, cuando se pueda, con **opciones o una hipótesis** ("¿es A, B u otra cosa?").
- No proponer soluciones técnicas en el brief. Si aparecen, se anotan como ideas para el plan.
- Todo lo preguntado y respondido queda en el **registro de entrevista** del brief.
- Si el humano no sabe una respuesta, se registra como supuesto explícito (con riesgo).

---

## 7. Modelo y esfuerzo recomendados por fase

El costo de un error crece fase a fase: un error en el plan se multiplica en cada línea de
código. Por eso **el esfuerzo se concentra arriba** y la ejecución se abarata.

| Fase | Tier de modelo | Esfuerzo / razonamiento | Por qué |
|------|----------------|-------------------------|---------|
| Roadmap | estándar | medio | Ordenar, priorizar, fechar. |
| Brief | **avanzado** | alto | Entender humanos y preguntar bien es difícil. |
| **Plan** | **el más capaz disponible** | **máximo** | Acá se toman todas las decisiones. |
| Revisión del plan | avanzado (idealmente otra sesión u otro modelo) | alto | Una mirada no anclada encuentra lo que el autor no ve. |
| Ejecución de fases | estándar / rápido | bajo–medio | El plan ya resolvió lo difícil; se sigue el pseudocódigo. |
| Cierre y verificación | estándar | medio | Chequear criterios y DoD. |

> Es una recomendación agnóstica: cada IA mapea "avanzado/estándar" a sus modelos. Ejemplo con
> Claude: plan con Opus en esfuerzo alto o máximo; ejecución con Sonnet o Haiku.
> **Señal de alarma**: si el ejecutor necesita "pensar mucho" para avanzar, el plan está
> incompleto → se para, se anota el desvío y se vuelve a `/plan` (no se improvisa).

---

## 8. Cuándo aplica (tamaño del trabajo)

| Tamaño | Ejemplos | RM | BR | PL |
|--------|----------|----|----|----|
| **XS/S** | bug, ajuste de texto, un campo, config | — | — | — (usar `/fix`, `/bug`) |
| **M** | feature acotado a un módulo, varios archivos, 1–3 días | sugerido | sugerido (puede ser liviano) | **obligatorio** |
| **L/XL** | módulo nuevo, integración externa, cambio de modelo de datos, multi-módulo | **obligatorio** | **obligatorio** | **obligatorio** (con fases) |

Ante un pedido M o más grande sin plan, el agente **debe sugerir** la fase faltante antes de
codear. Si el humano decide saltearla, sigue y lo registra (§9).

---

## 9. Excepciones: urgencias, imprevistos y cambios

La realidad no respeta el roadmap. Se permite:

| Situación | Qué se hace |
|-----------|-------------|
| **Urgencia** (producción caída, cliente bloqueado) | `PL-` directo con `origin: urgencia`, sin `BR-` ni `RM-`. Al cerrar, el agente **sugiere** regularizar: crear el `RM-` retroactivo o vincularlo a uno existente. Si es un bug chico: `/fix`, sin plan. |
| **Imprevisto técnico** | `PL-` con `origin: imprevisto`; `brief: —` es válido. |
| **Brief sin roadmap** | Válido (`rm: —`): ideas que todavía no tienen lugar en el roadmap. `/roadmap review` las muestra para decidir. |
| **Cambio de alcance durante la ejecución** | Se anota en "Desvíos" del plan. Si es significativo: plan a `⛔ Bloqueado`, se re-planifica (nuevo `PL-` que **reemplaza** al anterior, o una revisión del mismo antes de seguir). |
| **Cambio de fechas** | Se actualiza el `RM-` y se agrega una fila a su historial de re-planificación con el motivo. |

Todo salto de fase queda escrito en el campo `origin` / `exception` del artefacto. **Saltear
está permitido; ocultarlo no.**

---

## 10. Ejecución guiada y loops agénticos

Un plan aprobado es un **contrato ejecutable**: permite trabajos agénticos más largos, en loop
y con menos supervisión.

**Contrato de cada iteración de `/plan run` (una fase por iteración):**
1. Leer el plan completo y el registro de ejecución.
2. Tomar la **primera fase `📋 pendiente` cuyas dependencias estén `✅`**.
3. Marcarla `⚙️ en curso`, implementar **solo** lo que dice la fase.
4. Correr el comando de verificación de la fase y la suite de tests.
5. Si pasa: marcar `✅ hecha`, fila en el registro de ejecución (fecha, agente, modelo,
   walkthrough), log + walkthrough según [`logging.md`](logging.md).
6. Si falla o falta información: marcar `⛔ bloqueada` con el motivo, anotar el desvío y
   **cortar el loop**. Nunca improvisar fuera del plan.
7. Si no quedan fases pendientes: proponer `/plan close`.

Las fases marcadas como **paralelizables** en el plan pueden ejecutarse en simultáneo por
distintos agentes, siempre que no compartan archivos.

---

## 11. Relación con el resto de la metodología

- **Sprints** ([`sprints.md`](sprints.md)): un sprint toma `RM-` / `PL-` aprobados del índice. El
  plan es la unidad de trabajo del sprint; la DoD del feature incluye "plan `✅ Completado`".
- **Bugs** ([`bug-tracking.md`](bug-tracking.md)): los bugs siguen su propio ciclo (`BUG-NNN`).
  Un bug que en realidad es un rediseño se promueve a `BR-`/`PL-`.
- **Logging** ([`logging.md`](logging.md)): cada walkthrough y cada línea de log de trabajo
  planificado **citan los códigos** (`PL-012/F2`, `BR-007`, `RM-004`).
- **`docs/roadmap.md`**: en proyectos que adoptan este flujo, la fuente de verdad del roadmap es
  `planning/roadmap/INDEX.md`. `docs/roadmap.md` puede quedar como vista por versiones/releases
  o como legado de proyectos anteriores.

---

## 12. Comandos

| Comando | Alias | Para qué |
|---------|-------|----------|
| [`/roadmap`](../commands/roadmap.md) | `/rm` | `init`, `list`, `new`, `show`, `update`, `review`, `sync` |
| [`/brief`](../commands/brief.md) | `/br` | `list`, `new`, `show`, `refine`, `ready`, `discard` |
| [`/plan`](../commands/plan.md) | `/pl` | `list`, `new`, `show`, `review`, `approve`, `run`, `status`, `close` |

> Si la herramienta de IA ya usa alguno de estos nombres como comando nativo (por ejemplo
> `/plan`), usá el alias (`/pl`).
