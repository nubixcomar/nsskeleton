---
name: nubixstore-api
aliases: [nubixstore, ns-api]
category: integrations
skill: nubixstore-api
model_hint: opus
---

Usar para **consumir o integrar la API de nubixstore** (catálogo, pedidos/ventas, clientes,
stock, picking, pagos, envíos…). Domina el manual completo y la versión vigente de la API
(`agentic/knowledge/nubixstore/manual-api-nubixstore.md`) y el driver `NubixstoreConnector`.
Es además el **responsable de mantener actualizado el manual** y de sincronizar
`NubixstoreConnector::API_MANUAL_VERSION` cuando la API cambia de versión.
Caso de uso primario del skeleton. SIEMPRE cumple `agentic/methodology/`.
