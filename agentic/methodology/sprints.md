# Metodología: Sprints de desarrollo

> Marco liviano de gestión de desarrollo para proyectos iniciados desde nsSkeleton.
> Combina sprints cortos con la trazabilidad agéntica (logging + bug-tracking).

---

## Principios

1. **Iterativo e incremental**: se entrega valor en ciclos cortos (sprints).
2. **Aislamiento de features** (heredado de nubixstore): cada feature nuevo vive lo
   más aislado posible; no refactoriza "de paso"; es desactivable sin romper otros.
3. **Definición de Hecho (DoD)** explícita por feature (ver abajo).
4. **Trazabilidad obligatoria**: todo avance queda en `logs/` y en `docs/roadmap.md`.

---

## Estructura de un sprint

- **Duración:** sugerida 1–2 semanas (ajustable por proyecto).
- **Nomenclatura:** `S0`, `S1`, `S2`, … (referenciada en `docs/roadmap.md`).
- **Artefactos:**
  - `docs/roadmap.md` — backlog por versión, con columna `Sprint` y `Estado`.
  - `logs/sprints/<Sn>.md` — plan y cierre del sprint (opcional pero recomendado).

### Ceremonias (adaptadas a flujo humano + agentes)
| Momento        | Qué se hace                                                        |
|----------------|--------------------------------------------------------------------|
| **Planning**   | Se eligen features del roadmap para el sprint y se fija el objetivo.|
| **Desarrollo** | Agentes implementan; cada uno registra en `logs/` (logging.md).    |
| **Testing**    | Agente de QA detecta bugs (bug-tracking.md) antes del cierre.       |
| **Review**     | Se revisa lo hecho contra la DoD; se actualiza estado en roadmap.   |
| **Retro**      | Notas de mejora del proceso en el cierre del sprint.                |

---

## Estados de feature (en `docs/roadmap.md`)

`📋 Pendiente` · `🟡 En progreso` · `🧪 En testing` · `✅ Completo` · `⛔ Bloqueado` · `❌ Descartado`

Un feature solo pasa a `✅ Completo` cuando cumple la **Definición de Hecho**.

---

## Definición de Hecho (DoD) por feature

Un feature está **Hecho** cuando:

- [ ] Cumple la especificación del `docs/brief.md` (o doc de módulo).
- [ ] Está aislado y es desactivable sin romper otros módulos.
- [ ] Pasó la detección de bugs (sin CRÍTICO/GRAVE abiertos).
- [ ] Tiene walkthrough en `logs/walkthrough/` y línea en `logs/<agente>.log`.
- [ ] Estado actualizado en `docs/roadmap.md`.
- [ ] Si aplica: migración SQL versionada y documentada.

---

## Registro de features implementados

Se mantiene un resumen acumulativo en `logs/features-resume.md`:

| # | Feature | Versión | Fecha | Estado | Agente | Modelo |
|---|---------|---------|-------|--------|--------|--------|
| 1 | …       | 0.1.0   | …     | ✅      | …      | …      |

> Esto da una vista histórica de qué construyó cada agente y cuándo — clave para
> auditar el avance del proyecto sin leer todos los walkthroughs.
