# Metodología: Logging y trazabilidad agéntica

> **Regla no negociable.** Todo agente que trabaja en un proyecto iniciado desde
> nsSkeleton DEBE registrar lo que hizo. Sin registro, la tarea no está terminada.

Esta convención está portada y generalizada del framework agéntico de nubixstore.

---

## Los 3 artefactos obligatorios

Al finalizar **cualquier** tarea, el agente produce:

### a) Walkthrough de sesión (uno por tarea, nunca se sobrescribe)
- **Ubicación:** `logs/walkthrough/`
- **Nombre:** `YYYY-MM-DD_HHMM_<agente>_<feature>.md`
  - Ej: `2026-06-22_1430_hotfix_BUG-014.md`
  - Ej: `2026-06-22_0900_dev-backend_login-admin.md`
- **Contenido:** ver plantilla en
  [`../templates/walkthrough.template.md`](../templates/walkthrough.template.md).

### b) Log incremental por agente (append-only)
- **Ubicación:** `logs/<agente>.log`
  - Ej: `logs/dev-backend.log`, `logs/bug-detection.log`, `logs/hotfix.log`
- **Formato de línea:**
  ```
  [YYYY-MM-DD HH:MM:SS] [<TIPO>] <feature/módulo> | <síntesis en 1 línea> | modelo: <modelo>
  ```
  - `<TIPO>`: `FEATURE` | `FIX` | `REFACTOR` | `DOCS` | `AUDIT` | `TEST` | `BUG`
  - Ej:
    ```
    [2026-06-22 14:30:11] [FIX] Login/Auth | corrige redirect tras login fallido (BUG-014) | modelo: claude-opus-4-8
    ```
- **Append-only**: jamás se edita ni se borra una línea previa.

### c) Informe / resultado guardado
- Cualquier informe, reporte HTML/PDF o salida relevante se guarda y se referencia
  desde el walkthrough. Nunca se descarta.

---

## Firma del agente

Todo walkthrough y todo resume llevan en el encabezado una **firma**:

```
Fecha y hora: 2026-06-22 14:30 | Agente: hotfix | Modelo: claude-opus-4-8
```

Esto permite auditar quién (qué agente + qué modelo) hizo cada cosa y cuándo.

---

## Estructura de la carpeta de logs

```
logs/
├── <agente>.log              # incremental, append-only, uno por agente
├── human-development.log     # cambios hechos por humanos (referencia)
├── walkthrough/              # un .md por sesión/tarea (nunca se sobrescribe)
│   └── YYYY-MM-DD_HHMM_<agente>_<feature>.md
└── README.md
```

### Separación por stack (cuando hay migración)
Si el proyecto convive con dos stacks (ej. migración), los logs del stack nuevo van
en una subcarpeta para no pisarse:
```
logs/
├── agent-development.log     # stack principal
└── <stack-nuevo>/
    └── agent-development.log # stack en migración
```

---

## Flujo de cierre de tarea (checklist del agente)

Al terminar, el agente ejecuta SIEMPRE, en orden:

1. [ ] Append de 1 línea en `logs/<agente>.log` (b).
2. [ ] Crear walkthrough `logs/walkthrough/YYYY-MM-DD_HHMM_<agente>_<feature>.md` (a).
3. [ ] Guardar informes/salidas y referenciarlos en el walkthrough (c).
4. [ ] Si tocó bugs: actualizar `logs/bugs-resume.md` (ver
       [`bug-tracking.md`](bug-tracking.md)).
5. [ ] Si completó/avanzó un feature: actualizar estado en `docs/roadmap.md`.

> Si un paso no aplica, se omite explícitamente; nunca se "saltea" sin razón.
