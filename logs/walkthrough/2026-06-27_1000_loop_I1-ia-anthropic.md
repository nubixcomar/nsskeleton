# Walkthrough — Fase I1: conector IA proveedor Anthropic (Claude)

**Fecha y hora:** 2026-06-27 10:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S10 (v1.9) | **Versión:** 1.8 → 1.9 · **Idea de:** nsCentral (impulso)

---

## Resumen ejecutivo
El conector de IA ahora soporta **Anthropic (Claude)** además de OpenAI y Deepseek, con el
request/response correcto de cada proveedor.

## Cambios realizados
- **`AiConnector::PROVIDERS`**: cada proveedor declara `style` (`openai` | `anthropic`).
  Anthropic: base `https://api.anthropic.com/v1`, modelo default `claude-haiku-4-5-20251001`.
- **`config()`** expone `style`.
- **`splitSystem()`**: separa el/los mensajes `system` (Anthropic los pide como campo aparte).
- **`buildRequest()`**: arma url+payload+headers por estilo — OpenAI `/chat/completions`
  (Bearer) vs Anthropic `/messages` (`x-api-key` + `anthropic-version` + `max_tokens` +
  `system` top-level).
- **`extractContent()`**: `choices[0].message.content` vs `content[0].text`.
- **`parseSseAnthropic()`** + **`parseSseToken()`** (dispatcher): streaming SSE por estilo
  (`content_block_delta`/`text_delta`).
- `chat()` y `chatStream()` ahora usan `buildRequest()` + parser según estilo. UI: el
  selector de proveedor ya lista `anthropic` (es dinámico) + placeholder de modelo con Claude.

## Verificación
- `php -l` OK.
- **Suite**: **213/213 PASS** (+6 unit: providers incluye anthropic, splitSystem, buildRequest
  openai/anthropic, extractContent, parseSseAnthropic/parseSseToken). OpenAI/Deepseek intactos.
- **E2E (MySQL 3307)**: configurando `provider=anthropic` → `config.style=anthropic`,
  `buildRequest` apunta a `/v1/messages`; `chat()` con key dummy falla **graceful** (sin
  excepción) y registra `ai_log.provider=anthropic`. Config de prueba limpiada.

## Notas honestas
- Sin API key real no se prueba una completion real de Claude; quedó verificado el armado de
  request por estilo, el parseo y el error-path.

## Pendientes / follow-ups
- **I2** Cronmaster v2 — siguiente.

## Referencias
- `system/app/Services/AiConnector.php`, `tests/unit/AiAnthropicTest.php`.
