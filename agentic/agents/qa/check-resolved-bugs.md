---
name: check-resolved-bugs
aliases: [check-bugs, verify-bugs, qa-verify]
category: qa
skill: check-resolved-bugs
model_hint: opus
---

Usar para auditar los bugs OPEN/WIP contra el código actual y cerrar (DONE) los ya
resueltos, sin modificar código. Verifica que el fix sea real y robusto, no un parche
superficial. Siempre cumple [`../../methodology/`](../../methodology/) (bug-tracking +
logging): actualiza `logs/bugs-resume.md`, registra en `logs/bug-resolved.log` y deja
un walkthrough de la auditoría.
