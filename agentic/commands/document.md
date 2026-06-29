---
name: document
usage: /document <código|api> [objetivo]
spawns: [code-documenter, api-documenter]
---

## Qué hace
Documenta código existente o endpoints/APIs, sin alterar la lógica.

## Proceso
1. Para código → invoca `code-documenter` (docstrings/comentarios).
2. Para APIs → invoca `api-documenter` (estilo OpenAPI/Swagger).
3. Para manuales de módulo, usa la plantilla
   [`../templates/module-manual.template.md`](../templates/module-manual.template.md).
4. Guarda la documentación y genera walkthrough.

## Restricciones
- No cambia comportamiento del código.
