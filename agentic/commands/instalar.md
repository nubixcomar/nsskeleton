---
name: instalar
usage: /instalar
spawns: [installer]
---

## Qué hace
Inicia un proyecto nuevo desde nsSkeleton: hace el Q&A del instalador y deja todo
configurado.

## Proceso
1. Invoca el agente `installer`.
2. Lee `installer/questions.yml` y le pregunta al humano (proyecto, stack, IA, infra).
3. Aplica las respuestas: genera `.env` (`php system/console/install.php`), completa
   `docs/stack.md` y `docs/brief.md`, ajusta el adapter.
4. (Opcional) migra + seed del sistema base.

## Restricciones
- No sobrescribe un `.env` existente sin confirmación.
- La IA arranca por `AGENTS.md`; no se generan carpetas propietarias.
