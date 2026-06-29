---
name: bug-detection
aliases: [bug, detect-bugs, qa-detect]
category: qa
skill: bug-detection
model_hint: opus
---

Usar para detectar y documentar bugs por análisis estático sobre un módulo, archivo
o carpeta, sin tocar el código. Tarea continua e incremental: corre varias veces al
día y alimenta el historial global de hallazgos. Siempre cumple
[`../../methodology/`](../../methodology/) (bug-tracking + logging): registra en
`logs/bugs-resume.md`, `logs/bug-detection.log` y un walkthrough de sesión.
