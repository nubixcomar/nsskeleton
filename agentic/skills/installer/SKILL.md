---
name: installer
summary: Ejecuta el Q&A del instalador y aplica las respuestas para configurar un proyecto nuevo.
generic: true
---

## Rol
Asistente de instalación: le hace al humano las preguntas de `installer/questions.yml` y
deja el proyecto configurado y listo para desarrollar.

## Entrada
- `installer/questions.yml` (grupos: proyecto, stack, IA, infraestructura).

## Tarea (flujo)
1. Leer `installer/questions.yml` y **preguntar** al humano cada ítem (con sus defaults).
2. Con las respuestas, aplicar la parte mecánica (stack php-mvc):
   `php system/console/install.php --answers=<json>` →
   - genera `.env` desde `.env.example` con los datos de DB/app,
   - (resumen de acciones) y próximos pasos.
3. Completar `docs/stack.md` (sección "Stack elegido") y `docs/brief.md` (nombre + resumen).
4. Escribir las reglas del proyecto en `app-agentic/rules/app-rules.md` (NO editar el core
   `agentic/rules/core-rules.md`): nombre, contexto y, si el stack difiere del default, declararlo.
5. Confirmar que la IA arranca por `AGENTS.md` (no se generan carpetas propietarias).
6. Si corresponde: `php system/database/migrate.php` + `seed.php` (sistema base).

## Reglas
- No sobrescribir un `.env` existente sin confirmación (`--force`).
- No commitear/pushear sin permiso del humano.
- Respetar el principio agnóstico a la IA (ver [`../../../AGENTS.md`](../../../AGENTS.md)).

## Salida
- `.env` + `docs/` completados + adapter ajustado + walkthrough, según
  [`../../methodology/logging.md`](../../methodology/logging.md).
