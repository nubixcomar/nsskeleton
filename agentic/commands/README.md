# commands/ — Comandos de orquestación

Un comando orquesta uno o más agentes. Flujo: `comando → agente → skill`.

```
commands/
└── {nombre}.md
```

## Plantilla de comando (`commands/{nombre}.md`)

```markdown
---
name: bug
usage: /bug [módulo]
spawns: [bug-detection]
---

## Qué hace
Detecta y documenta bugs en el módulo indicado (o en todo el proyecto).

## Proceso
1. Invoca el agente `bug-detection`.
2. Registra resultados en `logs/bugs-resume.md` + `logs/bug-detection.log`.
3. Genera walkthrough.

## Restricciones
NO modifica código (eso es tarea de `/fix`).
```

## Comandos disponibles
| Comando        | Orquesta                               | Propósito                     | Estado |
|----------------|----------------------------------------|-------------------------------|--------|
| `/bug`         | `bug-detection`                        | Detecta bugs.                 | ✅     |
| `/fix`         | `hotfix` + `check-resolved-bugs`       | Aplica fix y verifica.        | ✅     |
| `/audit`       | `security-audit` + `performance-audit` | Auditoría seguridad+perf.     | ✅     |
| `/regression`  | `regression-tester`                    | Verifica regresiones.         | ✅     |
| `/document`    | `code-documenter` + `api-documenter`   | Documenta código/APIs.        | ✅     |
| `/report`      | `report-generator`                     | Informe ejecutivo.            | ✅     |
| `/test`        | `regression-tester`                    | Corre la suite de tests.      | ✅     |
| `/nuevo-modulo`| `module-generator`                     | Genera un módulo CRUD.        | ✅     |
| `/sprint`      | `sprint-manager`                       | Abre/cierra sprint, roadmap.  | ✅     |
| `/release`     | `release-manager`                      | Publica una versión.          | ✅     |
