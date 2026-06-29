---
name: fix
usage: /fix <BUG-ID>
spawns: [hotfix, check-resolved-bugs]
---

## Qué hace
Aplica una corrección quirúrgica al bug indicado y luego verifica que quedó resuelto.

## Proceso
1. Invoca `hotfix`: cambio de mínimo impacto, sin refactorizar de paso
   (ver [`../rules/new-features-rules.md`](../rules/new-features-rules.md)).
2. Registra el fix en `logs/fixed-bugs.log`.
3. Invoca `check-resolved-bugs`: verifica la resolución (lo hace un agente distinto).
4. Si quedó resuelto, mueve `BUG-ID` de OPEN/WIP → DONE en `logs/bugs-resume.md` y
   registra en `logs/bug-resolved.log`.
5. Genera walkthrough.

## Restricciones
- No toca código fuera del alcance del bug.
- La verificación NO la hace el mismo agente que aplicó el fix.
