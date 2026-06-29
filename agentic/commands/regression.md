---
name: regression
usage: /regression [módulo|cambio]
spawns: [regression-tester]
---

## Qué hace
Verifica que un cambio reciente no haya roto funcionalidades existentes.

## Proceso
1. Invoca `regression-tester`: analiza impacto y dependencias del cambio.
2. Si detecta una regresión, la registra como bug nuevo en `logs/bugs-resume.md`.
3. Genera walkthrough con el resultado.

## Restricciones
- Solo verifica/detecta; no modifica código.
