# Metodología: Identificación y tracking de bugs

> Portada y generalizada del sistema de bug-tracking de nubixstore (256 bugs
> históricos gestionados con este flujo). Es agnóstica a stack e IA.

---

## Ciclo de vida de un bug

```
        ┌──────► WIP ──────┐
        │   (intento de    │
OPEN ───┤    fix en curso) │────► DONE   (resuelto y verificado)
        │                  │
        └──────────────────┴────► DROP   (conocido, no se arregla — decisión)
```

| Estado | Significado                                              |
|--------|---------------------------------------------------------|
| `OPEN` | Detectado, sin resolver.                                |
| `WIP`  | Hay un fix en progreso o un intento previo fallido.     |
| `DONE` | Resuelto **y verificado** por el agente de verificación.|
| `DROP` | Se decide no arreglarlo (con justificación).            |

---

## Prioridades

`CRÍTICO` > `GRAVE` > `LEVE` > `MEJORA`

- **CRÍTICO**: rompe producción, pérdida de datos, brecha de seguridad, dinero.
- **GRAVE**: funcionalidad importante rota, sin workaround razonable.
- **LEVE**: molesto pero con workaround; warnings, edge cases menores.
- **MEJORA**: no es un bug; oportunidad de mejora detectada.

---

## Identificación de bugs (ID)

```
BUG-NNN     ← auto-incremental desde 1, nunca decrece, nunca se reutiliza.
```

---

## Archivo maestro: `logs/bugs-resume.md`

Es el registro central. Estructura:

1. **Firma** (3 líneas): último control, última detección, última reparación.
2. **Skip list**: archivos que los agentes NO deben analizar (tests, dev, sandbox).
3. **Estadísticas**: abiertos (OPEN/WIP), cerrados (DONE/DROP), total.
4. **Ranking de archivos comprometidos** (solo OPEN/WIP, descendente).
5. **Ranking histórico** (todos los bugs por archivo; nunca decrece).
6. **Tabla — Bugs activos (OPEN/WIP)**:
   | ID | Prioridad | Estado | Módulo | Archivo:Línea | Descripción | Modelo | Detectado |
7. **Tabla — Historial (DONE/DROP)**: + columna `Resolución`.

Ver plantilla en
[`../templates/bug-report.template.md`](../templates/bug-report.template.md).

---

## Logs incrementales de bugs (append-only)

| Archivo                      | Qué registra                            |
|------------------------------|-----------------------------------------|
| `logs/bug-detection.log`     | Cada bug detectado (con detalle/impacto)|
| `logs/fixed-bugs.log`        | Cada fix aplicado                       |
| `logs/bug-resolved.log`      | Cada verificación de resolución         |

**Formato detección:**
```
[2026-06-22 01:18:00] [BUG-014] [GRAVE] Módulo: Auth | Archivo: app/Controllers/AuthController.php:42
Detalle: tras login fallido no se setea flash ni redirect; el usuario queda en blanco.
Impacto: UX rota en el login; parece que la app se colgó.
```

**Formato fix:**
```
[2026-06-22 14:30:00] [FIX APLICADO] ID: BUG-014 | GRAVE | Archivo: app/Controllers/AuthController.php:42
Fix: agrega flash de error + redirect a /login en el path de credenciales inválidas.
Requiere: nada
---
```

**Formato verificación:**
```
[2026-06-22 14:45:00] [VERIFICADO] ID: BUG-014 | GRAVE | Archivo: app/Controllers/AuthController.php
Causa original: faltaba manejo del path de error en login().
Solución detectada: flash + redirect agregados; probado con credenciales inválidas.
---
```

---

## Patrones prohibidos (detección obligatoria)

El agente de detección SIEMPRE reporta estos patrones (la lista crece por proyecto;
estos son genéricos para PHP):

1. **Debug/dump activo en producción** — `var_dump`, `print_r`, `dd()`, `debug()` sin
   comentar. → **GRAVE** (filtra info y/o rompe la respuesta).
2. **Input pisado por valor hardcodeado** — leer el body real y luego sobrescribirlo
   con un literal. → **CRÍTICO** si pisa el input real.
3. **Respuesta AJAX/JSON incompleta** — `render`/`exit` comentado en endpoints que
   deben responder JSON. → **GRAVE**.
4. **Falta de `exit`/`return` tras render** — la ejecución sigue y accede a variables
   inválidas. → **GRAVE**.
5. **Concatenación de SQL con input** — riesgo de inyección. → **CRÍTICO**.

---

## Flujo de trabajo recomendado

```
/bug [módulo]   → detecta y documenta (NO modifica código) → bugs-resume.md + bug-detection.log
/fix BUG-NNN    → aplica fix quirúrgico → fixed-bugs.log → luego verifica → bug-resolved.log
                  → mueve BUG-NNN de OPEN/WIP a DONE en bugs-resume.md
```

> **Regla de oro:** detectar y arreglar son tareas separadas, hechas por agentes
> distintos. La verificación la hace un tercero (no quien aplicó el fix).
