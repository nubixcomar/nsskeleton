# Walkthrough — Fase H1: streaming de IA (SSE)

**Fecha y hora:** 2026-06-23 09:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S6 (v1.5) | **Versión:** 1.4 → 1.5

---

## Resumen ejecutivo
El conector de IA ahora soporta streaming de respuestas (SSE): los tokens llegan a
medida que el modelo los genera. Cierra el ítem diferido de C6.

## Cambios realizados
- **`Http::postStream`**: POST con cURL `WRITEFUNCTION`, invoca un callback por cada
  línea recibida (no bufferiza la respuesta completa).
- **`AiConnector::parseSseLine`**: extrae el token de una línea `data: {...}` (maneja
  `[DONE]`, comentarios y deltas sin contenido).
- **`AiConnector::chatStream`**: arma el request con `stream:true`, inyecta el system
  prompt, acumula el contenido y llama a `$onToken` por fragmento.
- **`AiController::stream`** + ruta `/admin/ai/stream`: endpoint `text/event-stream`
  (con `flush`) que reenvía los tokens como eventos SSE.
- **UI**: panel de IA con "Probar con streaming" (Alpine + `EventSource`).

## Verificación
- `php -l` OK.
- **Suite**: `php tests/run.php` → **142/142 PASS** (+5 unit `parseSseLine`: extracción,
  `[DONE]`, comentarios, delta vacío, acumulación en orden).
- **Smoke (servidor + sesión)**: `GET /admin/ai/stream?prompt=hola` →
  `Content-Type: text/event-stream`; sin API key emite `event: error` + `event: done`
  (plumbing SSE correcto); la UI de streaming aparece en el panel.

## Notas honestas
- El streaming **token a token real** requiere una API key válida de OpenAI/Deepseek
  (no probado end-to-end por falta de credenciales). El transporte SSE, el parser y la
  UI quedaron verificados.

## Pendientes / follow-ups
- **H3** Dark mode / theming — siguiente.

## Referencias
- `system/app/Services/{Http,AiConnector}.php`, `system/app/Controllers/Admin/AiController.php`,
  `tests/unit/AiStreamTest.php`.
