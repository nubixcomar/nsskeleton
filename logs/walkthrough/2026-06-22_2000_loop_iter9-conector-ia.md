# Walkthrough — Iteración 9: conector de IA

**Fecha y hora:** 2026-06-22 20:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S1 | **Versión:** 0.1.0

---

## Resumen ejecutivo
Se implementó el conector de IA con soporte para OpenAI y Deepseek (API de chat
completions compatible): credenciales configurables, cliente HTTP propio, método
`chat()`, log de llamadas y panel de configuración + prueba. El conector y el cliente
HTTP fueron verificados (manejo de error sin key y host inalcanzable, sin excepción).

## Cambios realizados
- **Migración**: `20260622_0005_create_ai_log.sql` (`ai_log`).
- **Http** (`App\Services\Http`): POST JSON con cURL y fallback a stream context; nunca lanza.
- **AiConnector** (`App\Services\AiConnector`): proveedores (openai/deepseek), `config()`
  resiliente (Settings + fallback `.env`), `chat()` sobre `/chat/completions`, log best-effort.
- **Modelo**: `App\Models\AiLog`.
- **Controlador**: `Admin\AiController` (settings, saveSettings, test).
- **Vista** `admin/ai/settings`: credenciales + prueba de chat + historial.
- **Rutas + menú**: registradas; ítem "Conector IA" activado (último módulo del sidebar).
- **Services/README.md**: índice de los 10 servicios del sistema base.

## Verificación
- `php -l` en todo `system/` → sin errores.
- **AiConnector + Http: 7/7 tests** — providers incluye openai/deepseek; `config()` no
  rompe sin DB y usa defaults; `chat()` sin key → `ok=false` con error que menciona la
  key; `Http::postJson` a host muerto → `ok=false` sin excepción.
- `GET /admin/ai` sin sesión → 302 a login (guard OK).
- ⚠️ La llamada real a un proveedor requiere API key válida + red; pendiente de probar
  con credenciales reales.

## Decisiones de diseño
- OpenAI y Deepseek comparten el esquema de chat completions → un solo `chat()` con
  base URL por proveedor. El conector solo da credenciales + transporte; los prompts y
  la lógica los aporta cada proyecto (requisito del usuario).
- API key guardada en `settings` (solo se actualiza si se ingresa una nueva).
- Log de uso en `ai_log` (chars de prompt/respuesta, estado, error), best-effort.

## Pendientes / follow-ups
- **Iteración 10 (cierre MVP)**: docs de uso, `features-resume`, `bugs-resume` init,
  índice de migraciones, repaso final del roadmap.
- A futuro: cifrar API key en reposo; soportar streaming de respuestas.

## Referencias
- `system/app/Services/{Http,AiConnector}.php`, `system/app/Controllers/Admin/AiController.php`.
