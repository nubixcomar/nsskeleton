---
name: report
usage: /report [tipo]
spawns: [report-generator]
---

## Qué hace
Genera un informe ejecutivo (HTML/PDF) a partir de hallazgos, logs y walkthroughs.

## Proceso
1. Invoca `report-generator`.
2. Toma como fuente `logs/bugs-resume.md`, `logs/features-resume.md` y walkthroughs.
3. Guarda el informe y lo referencia desde un walkthrough.

## Restricciones
- Solo consolida información existente; no audita ni modifica código.
