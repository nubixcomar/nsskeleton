# Walkthrough — Fase C6: IA prompts + system prompt (cierra v1.3)

**Fecha y hora:** 2026-06-23 06:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S4 (v1.3) | **Versión:** 1.2 → 1.3

---

## Resumen ejecutivo
El conector de IA gana una librería de prompts reutilizables (con variables) y un system
prompt configurable que se inyecta en cada conversación. Verificado. Cierra la v1.3.

## Cambios realizados
- **`config/prompts.php`**: prompts reutilizables (`resumen`, `traducir`, `email-formal`,
  `clasificar`) con `{{var}}`.
- **`App\Services\PromptLibrary`**: `all/names/has/get/render` (reemplazo de variables).
- **`AiConnector`**: `withSystem` (antepone system prompt si no hay), `chatPrompt(nombre,
  vars)`, `config.system_prompt`; `chat()` inyecta el system prompt (override por
  `$opts['system']`).
- **`AiController`**: guarda `ai.system_prompt`; el panel muestra el campo + la librería.

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **125/125 PASS** (+4 unit `PromptLibrary`, +3 unit
  `AiConnector::withSystem`).
- **Smoke**: guardar `ai.system_prompt` → `AiConnector::config()` lo refleja;
  `chatPrompt('resumen', …)` renderiza el prompt (ok=false sin API key, esperado);
  `/admin/ai` muestra el campo system prompt y la sección "Librería de prompts".

## Streaming (diferido, documentado)
El streaming de respuestas (SSE) **no** se implementó: el cliente HTTP actual (`Http`)
es no-streaming. Queda como mejora futura (requiere un transporte SSE + endpoint que
reenvíe los chunks). Se reporta con honestidad para no dar por hecho algo no entregado.

## Estado
**v1.3 (Capacidades extendidas) COMPLETA**: C1 API REST, C2 RBAC, C3 cron jobs, C4 emails
cola, C5 file manager+, C6 IA prompts. Siguiente: **Track D (v1.4)** — onboarding y ejemplos.

## Referencias
- `system/config/prompts.php`, `system/app/Services/PromptLibrary.php`,
  `system/app/Services/AiConnector.php`, `tests/unit/PromptLibraryTest.php`.
