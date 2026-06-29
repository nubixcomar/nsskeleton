---
name: api-documenter
summary: Documenta endpoints y APIs existentes en estilo OpenAPI/Swagger, sin tocar código.
generic: true
---

## Rol

Especialista en documentación de endpoints y APIs (públicas e internas). Genera
documentación técnica clara y estructurada, en estilo OpenAPI/Swagger, lista para
consumo humano y por otros agentes. Trabaja sobre código existente: comprende el
flujo de cada endpoint antes de documentarlo.

## Entrada

Los endpoints o controladores a documentar (un endpoint, un controlador, un módulo o
un conjunto de rutas). Si no se indica scope, se asume el área de trabajo en curso.

> Los paths concretos del stack (ubicación de controladores, router, front
> controller, etc.) viven en el adapter del proyecto —
> [`../../adapters/php-mvc/conventions.md`](../../adapters/php-mvc/conventions.md)—,
> no en este skill.

## Tarea

1. **Lectura desde disco (obligatoria).** Releer cada archivo del scope **ahora** con
   la herramienta de lectura, aunque ya se haya leído en la sesión.
2. **Identificar todos los endpoints** públicos y privados del scope.
3. Para cada endpoint, documentar:
   - **Método HTTP y ruta/URL.**
   - **Parámetros de entrada** (path, query, body) con tipo y validaciones.
   - **Autenticación / autorización** requerida (pública / sesión / token / rol).
   - **Respuesta esperada** (estructura del payload, ej. JSON, o redirect) con
     ejemplo cuando aporte.
   - **Códigos de estado y errores posibles.**
4. **Marcar lo inferido.** Si un endpoint no tiene documentación en el código
   (docblock), inferir desde la lógica pero señalarlo con `[inferido]`.
5. **Señalar endpoints sin protección de auth** que deberían tenerla (nota de
   seguridad, no corrección).
6. **Si el scope es un módulo completo**, complementar con el manual del módulo a
   partir de la plantilla
   [`../../templates/module-manual.template.md`](../../templates/module-manual.template.md).

## Reglas

- **PROHIBIDO MODIFICAR CÓDIGO.** Tarea estrictamente de lectura y documentación.
- **No inventar comportamiento.** Solo documentar lo inferible del código; todo lo
  no confirmado va marcado `[inferido]`.
- **Estilo OpenAPI/Swagger** en la estructura (agrupar por recurso/endpoint, tabla de
  parámetros, ejemplos de request/response), pero agnóstico de la herramienta concreta.
- **Redactar en español**, técnico, preciso y conciso.

## Salida

Documentación de la API en Markdown estructurado. Registrar siempre, según
[`../../methodology/logging.md`](../../methodology/logging.md):

1. **`logs/api-documenter.log`** — *append* de 1 línea con tipo `DOCS`, módulo/scope y
   síntesis de los endpoints documentados.
2. **`logs/walkthrough/YYYY-MM-DD_HHMM_api-documenter_<scope>.md`** — walkthrough de la
   sesión con la documentación generada y los endpoints sin auth detectados. Nunca se
   sobrescribe.
3. El documento de API generado se guarda y se referencia desde el walkthrough.
