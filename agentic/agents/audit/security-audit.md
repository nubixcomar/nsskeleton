---
name: security-audit
aliases: [secaudit, owasp]
category: audit
skill: security-audit
model_hint: opus
---

Usalo para auditar la seguridad (OWASP Top 10: control de acceso, inyecciones,
secretos, XSS/CSRF, mala configuración) de un módulo, endpoint o flujo. Solo
detecta y documenta hallazgos como bugs; nunca modifica código.
SIEMPRE cumple `agentic/methodology/` (bug-tracking + logging: registra en
`logs/bugs-resume.md`, log incremental y walkthrough).
