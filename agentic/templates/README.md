# templates/ — Plantillas

Plantillas reutilizables que los agentes y el humano copian al iniciar/operar el proyecto.

| Plantilla                       | Copiar a                         | Uso                              |
|---------------------------------|----------------------------------|----------------------------------|
| `brief.template.md`             | `docs/brief.md`                  | Brief **maestro** del producto.  |
| `planning/` (scaffold)          | `planning/` (vía `/roadmap init`)| README + índices RM/BR/PL.       |
| `planning/roadmap/RM.template.md` | `planning/roadmap/RM-NNN-<slug>.md` | Iniciativa del roadmap.     |
| `planning/briefs/BR.template.md`  | `planning/briefs/BR-NNN-<slug>.md`  | Brief de iniciativa (dolores, necesidades, preguntas). |
| `planning/plans/PL.template.md`   | `planning/plans/PL-NNN-<slug>.md`   | Plan de implementación por fases. |
| `roadmap.template.md`           | `docs/roadmap.md`                | Roadmap por versiones (legado / vista de releases). |
| `bug-report.template.md`        | `logs/bugs-resume.md`            | Registro maestro de bugs.        |
| `walkthrough.template.md`       | `logs/walkthrough/<fecha>_…md`   | Informe por tarea (obligatorio). |
| `module-manual.template.md`     | `docs/modules/<modulo>.md`       | Manual técnico de un módulo.     |
