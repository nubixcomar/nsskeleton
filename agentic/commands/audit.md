---
name: audit
usage: /audit [módulo]
spawns: [security-audit, performance-audit]
---

## Qué hace
Auditoría combinada de **seguridad** (OWASP Top 10, auth, inyecciones, XSS/CSRF) y
**performance** (N+1, queries lentas, índices, memoria) sobre el módulo indicado.

## Proceso
1. Invoca `security-audit` y `performance-audit` (pueden correr en paralelo).
2. Cada uno registra hallazgos como bugs/mejoras en `logs/bugs-resume.md` + su log.
3. Genera walkthrough con el resumen de ambos.

## Restricciones
- Solo detecta; no modifica código. Para arreglar, usar `/fix`.
