# logs/ — Trazabilidad agéntica

Registro **obligatorio** del trabajo de los agentes. Ver reglas completas en
[`../agentic/methodology/logging.md`](../agentic/methodology/logging.md).

## Contenido

```
logs/
├── <agente>.log         Log incremental por agente (append-only)
├── bug-detection.log    Detecciones de bugs
├── fixed-bugs.log       Fixes aplicados
├── bug-resolved.log     Verificaciones de resolución
├── bugs-resume.md       Registro maestro de bugs (ver methodology/bug-tracking.md)
├── features-resume.md   Histórico de features implementados
├── human-development.log Cambios hechos por humanos
└── walkthrough/         Un .md por tarea: YYYY-MM-DD_HHMM_<agente>_<feature>.md
```

> Los logs y walkthroughs **se versionan** (son la memoria del proyecto). Lo único
> que no se versiona son temporales (`*.log.tmp`).
