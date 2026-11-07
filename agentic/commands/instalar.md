---
name: instalar
usage: /instalar
spawns: [installer]
---

## Qué hace
Inicia un proyecto nuevo desde nsSkeleton: hace el Q&A del instalador y deja todo
configurado.

> Se corre **dentro** de la carpeta del proyecto. Para crear esa carpeta (copia limpia + DB +
> git propio) usá antes [`/nuevo-proyecto`](nuevo-proyecto.md) desde el master. Si el proyecto
> vino de `/nuevo-proyecto`, el `.env` y la base ya existen: no los regeneres, completá el
> brief, el stack y las reglas.

## Proceso
1. Invoca el agente `installer`.
2. Lee `installer/questions.yml` y le pregunta al humano (proyecto, stack, IA, infra).
3. Aplica las respuestas: genera `.env` (`php system/console/install.php`), completa
   `docs/stack.md` y `docs/brief.md`, ajusta el adapter.
4. Crea `planning/` (roadmap/briefs/planes) con `/roadmap init`.
5. (Opcional) migra + seed del sistema base.

## Restricciones
- No sobrescribe un `.env` existente sin confirmación.
- La IA arranca por `AGENTS.md`; no se generan carpetas propietarias.
