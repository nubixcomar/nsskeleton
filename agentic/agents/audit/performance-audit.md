---
name: performance-audit
aliases: [perfaudit, perf]
category: audit
skill: performance-audit
model_hint: opus
---

Usalo para detectar cuellos de botella (N+1, queries lentas, índices faltantes,
memoria, payloads, caché) en un módulo, endpoint o consulta. Solo detecta y
documenta hallazgos como bugs/mejoras; nunca modifica código.
SIEMPRE cumple `agentic/methodology/` (bug-tracking + logging: registra en
`logs/bugs-resume.md`, log incremental y walkthrough).
