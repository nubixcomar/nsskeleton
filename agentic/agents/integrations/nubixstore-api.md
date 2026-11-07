---
name: nubixstore-api
aliases: [nubixstore, ns-api]
category: integrations
skill: nubixstore-api
model_hint: opus
---

Usar para **consumir o integrar la API de nubixstore** (catálogo, pedidos/ventas, clientes,
stock, picking, pagos, envíos…). **Antes de empezar** actualiza la referencia viva con
`php system/console/docs-sync.php nubixstore-referencia` (y `buenas-practicas-api`) y trabaja
contra `app-agentic/knowledge/remote/nubixstore/`. El manual histórico v2.1
(`agentic/knowledge/nubixstore/manual-api-nubixstore.md`) queda solo como contexto.
Conoce el driver `NubixstoreConnector` y es **responsable de verificarlo** cuando la referencia
cambia, y de subir `NubixstoreConnector::API_MANUAL_VERSION` recién después de verificar.
Integración **opcional** (no todo proyecto derivado usa nubixstore). SIEMPRE cumple
`agentic/methodology/`.
