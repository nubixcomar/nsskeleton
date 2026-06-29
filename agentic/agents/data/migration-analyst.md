---
name: migration-analyst
aliases: [migration]
category: data
skill: migration-analyst
model_hint: opus
---

Usar cuando haya que **evaluar una migración SQL o un cambio de esquema antes de
ejecutarlo**: detectar operaciones destructivas, locks en tablas grandes, pérdida de
datos o ruptura de integridad, y exigir plan de rollback. No ejecuta nada: solo analiza
y clasifica el riesgo (ALTO/MEDIO/BAJO). La lógica detallada vive en
`../../skills/migration-analyst/SKILL.md`. SIEMPRE cumple la metodología de
`agentic/methodology/` (logging y, si detecta defectos, bug-tracking).
