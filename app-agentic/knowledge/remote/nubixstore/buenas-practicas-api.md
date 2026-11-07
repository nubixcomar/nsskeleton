# Manual de Buenas Prácticas para el uso de la API de nubixstore

**Versión del manual:** `1.6.0` — **Fecha:** 2026-11-04
**Distribución:** 🌐 **pública** — se entrega a clientes e integradores, y se publica en el Centro de ayuda
**Documenta la API:** `2.6.0`
**Mantenido por:** agente `api-documenter`
**Dirigido a:** Usuarios no técnicos que integran la API de **nubixstore** mediante agentes de IA u otras herramientas automatizadas.

---

## Índice

1. [Introducción](#introducción)
2. [Glosario rápido](#glosario-rápido)
3. [Cómo es el ciclo básico de uso de la API](#cómo-es-el-ciclo-básico-de-uso-de-la-api)
4. [Seguridad del token de acceso](#1-seguridad-del-token-de-acceso)
5. [Creá tu propia base de datos local](#2-creá-tu-propia-base-de-datos-local)
6. [No abuses de la frecuencia de consultas](#3-no-abuses-de-la-frecuencia-de-consultas)
7. [Cómo funciona la autenticación en la API de nubixstore](#4-cómo-funciona-la-autenticación-en-la-api-de-nubixstore)
8. [Manejá los errores con inteligencia](#5-manejá-los-errores-con-inteligencia)
9. [Cuidá los datos que recibís](#6-cuidá-los-datos-que-recibís)
10. [Recomendaciones específicas para usuarios de agentes de IA](#7-recomendaciones-específicas-para-usuarios-de-agentes-de-ia)
11. [Usá siempre HTTPS](#8-usá-siempre-https)
12. [Ambiente de prueba vs producción](#9-ambiente-de-prueba-vs-producción)
13. [Paginación y volumen de datos](#10-paginación-y-volumen-de-datos)
14. [Operaciones que modifican datos (idempotencia)](#11-operaciones-que-modifican-datos-idempotencia)
15. [Monitoreo y registros (logs)](#12-monitoreo-y-registros-logs)
16. [Backups de tu base local](#13-backups-de-tu-base-local)
17. [Sincronizar el catálogo con otra plataforma](#14-sincronizar-el-catálogo-con-otra-plataforma)
18. [Resumen de buenas prácticas](#15-resumen-de-buenas-prácticas)
19. [Preguntas frecuentes (FAQ)](#16-preguntas-frecuentes-faq)
20. [Contacto y soporte](#17-contacto-y-soporte)
21. [Historial de versiones](#historial-de-versiones)

---

## Introducción

Este manual está pensado para personas que no tienen formación en programación pero necesitan conectar sus sistemas o agentes de IA con la API de **nubixstore**. Acá encontrás recomendaciones generales para que tu integración sea segura, eficiente y no genere problemas ni para tu negocio ni para la plataforma.

> **Importante:** este manual es deliberadamente **no técnico**. No incluye ejemplos de código en lenguajes específicos. Si tu integración es compleja, te recomendamos contratar a un profesional.

> **Para el detalle de cada endpoint** (qué parámetros acepta, qué campos devuelve, qué tipo tiene cada uno) la referencia es la **documentación técnica OpenAPI / Swagger** de tu tienda, en `https://<tu-tienda>/api_docs` con el token de documentación que te entregaron. Este manual explica **cómo usarla bien**; el Swagger explica **qué hay**.

---

## Glosario rápido

Términos que vas a ver a lo largo del manual, explicados en lenguaje llano:

| Término | Qué significa |
|---------|---------------|
| **API** | Es como un "mostrador" digital al que tu sistema le hace pedidos para obtener o enviar datos a **nubixstore**. |
| **Endpoint** | Cada uno de los "lugares" del mostrador que sirven para algo distinto. Por ejemplo, un endpoint para listar productos, otro para crear pedidos. Cada endpoint es una dirección (URL). |
| **Token** | La clave que tu sistema usa para identificarse ante la API. Tratala como una contraseña: si se filtra, avisá a soporte para que la revoquen. |
| **Frontend** | La parte de un sistema que ven los usuarios en su navegador (la página web visible). |
| **Backend** | La parte de un sistema que corre en el servidor, oculta al usuario. Es donde deben vivir las claves y tokens. |
| **Variable de entorno** | Un lugar seguro del servidor donde se guardan datos sensibles (como un token), separado del código y no accesible desde internet. |
| **Repositorio** | Carpeta versionada donde se guarda el código de un proyecto (ej: GitHub, GitLab). Puede ser público o privado. |
| **HTTP / HTTPS** | El protocolo con el que viajan los datos por internet. **HTTPS** es la versión segura (cifrada); **HTTP** es la versión insegura. |
| **Código de estado HTTP** | Un número que devuelve el servidor indicando cómo le fue al pedido. **200** = todo bien, **400** = pedido mal armado, **500** = problema del servidor. ⚠ **En esta API no alcanza con mirarlo** — ver [sección 5](#5-manejá-los-errores-con-inteligencia). |
| **Campo `status`** | Un número que viene **dentro** de la respuesta de nubixstore y dice si el pedido salió bien. **`0` = salió bien**; cualquier otro valor (casi siempre `2`) = no salió. Es el primero que hay que mirar, junto con el código HTTP. |
| **Campo `code`** | Otro número que viene dentro de la respuesta y da **el detalle** de lo que pasó: por ejemplo `903` = token incorrecto. `900` suele acompañar a las respuestas buenas, pero **no alcanza solo**: hay errores que también traen `900`. |
| **Paginación** | Técnica para devolver listados largos de a "páginas" (ej. de 100 en 100), en vez de todo de una vez. |
| **Sandbox** | Un ambiente de prueba donde podés experimentar sin afectar datos reales. |
| **Log** | Un registro escrito de lo que hace tu sistema (qué pidió, cuándo, qué le respondieron). Sirve para diagnosticar problemas. |
| **Backoff** | Estrategia de esperar cada vez más tiempo entre reintentos cuando algo falla, para no sobrecargar al servidor. |
| **SKU** | Código interno con el que la tienda identifica un producto. Lo define la tienda; puede no estar cargado. |
| **EAN / código de barras** | El código impreso en el envase del producto, el que lee el scanner. En la API viaja como `barcode`. |
| **SKU-F (SKU del fabricante)** | El código que le pone **el fabricante o proveedor** al producto, distinto del SKU interno de la tienda. En la API viaja como `skuf`. Sirve para cruzar el catálogo contra listas de precios de proveedores. |
| **Variante** | Cada combinación vendible de un producto (talle, color, presentación). En **nubixstore** cada variante tiene **sus propios códigos y su propio stock**. |
| **Clave de matching** | El campo que usás para decidir que "este producto de nubixstore es el mismo que aquel de mi plataforma". Elegirla mal es el error más caro de una sincronización. |
| **Sincronización delta** | Traer sólo lo que cambió desde la última vez, en lugar de bajar todo de nuevo. |

---

## Cómo es el ciclo básico de uso de la API

```
   ┌──────────────┐    1. Pedido al endpoint /usuarios/token
   │ Tu sistema   │ ──────────────────────────────────────────►  ┌──────────────┐
   │ (backend)    │                                              │   API de     │
   │              │ ◄──────────────────────────────────────────  │ nubixstore   │
   │              │    2. Te devuelve un TOKEN                   └──────────────┘
   │              │
   │              │    3. Usás el token en cada pedido a /api/...
   │              │ ──────────────────────────────────────────►
   │              │
   │              │ ◄──────────────────────────────────────────
   │              │    4. Te devuelve los datos pedidos
   │              │
   │              │    5. Si la respuesta trae code 903 → volver al paso 1 y pedir el token de nuevo
   └──────────────┘
```

Tres ideas clave del flujo:

- El token **se obtiene primero** y se reutiliza en todos los pedidos siguientes.
- Si el token deja de funcionar (la respuesta trae `"code": 903`, **aunque el código HTTP diga 200**), **se pide de nuevo y se reintenta una vez**.
- Todo el flujo ocurre en el **backend** (servidor), no en el navegador del usuario.

---

## 1. Seguridad del token de acceso

El token es la "llave" que permite a tu sistema acceder a los datos de tu cuenta. Si alguien obtiene tu token, puede operar como si fuera vos.

### Qué hacer

- **Guardá el token en un lugar privado.** Nunca lo pongas dentro de una carpeta accesible desde internet (por ejemplo, la carpeta pública de tu sitio web). Si alguien navega a esa dirección, podría leer el token.
- **No compartas el token por chat, email o redes sociales.** Tratalo como una contraseña.
- **No lo incluyas en el código visible de una página web.** Si tu sistema tiene una parte que se ve en el navegador (frontend), el token no debe estar ahí. Cualquier visitante podría verlo.
- **Si usás archivos de configuración**, asegurate de que esos archivos no sean accesibles desde el navegador. Un profesional puede ayudarte a verificar esto.

### Ejemplo concreto

| ❌ Mal | ✅ Bien |
|---|---|
| Pegar el token en un archivo `script.js` que carga la página principal de tu sitio. | Guardar el token en un archivo `.env` ubicado **fuera** de la carpeta `public_html/` (o equivalente del hosting), y leerlo desde el backend. |
| Subir un archivo `config.txt` con el token a una carpeta accesible vía URL pública. | Guardar el token en una **variable de entorno** del servidor o en un sistema de gestión de secretos. |
| Pegar el token en un repositorio público de GitHub. | Mantener los archivos con tokens en un repositorio privado, e incluso así nunca commitear el archivo `.env` (agregarlo a `.gitignore`). |

### Qué evitar

- No guardes el token en archivos como `config.js`, `token.txt` o similares dentro de carpetas públicas.
- No pegues el token en documentos compartidos (Google Docs, Notion) sin restricción de acceso.
- No subas archivos que contengan el token a repositorios públicos.

---

## 2. Creá tu propia base de datos local

Uno de los errores más comunes es consultar la API cada vez que se necesita un dato, incluso si ese dato no cambió.

### Por qué es importante

- **Cada consulta a la API consume recursos**, tanto de tu sistema como del servidor de **nubixstore**.
- Si tu sistema hace cientos o miles de consultas por hora para obtener los mismos datos (por ejemplo, el catálogo), estás generando carga innecesaria.
- Si la API tiene algún momento de indisponibilidad, tu sistema deja de funcionar por completo si depende de ella en tiempo real.

### Qué hacer

- **Armá una base de datos propia** (puede ser un archivo simple, una planilla estructurada o una base de datos real) donde guardes la información que consultás.
- **Sincronizá periódicamente** (ver [sección 3](#3-no-abuses-de-la-frecuencia-de-consultas) para frecuencias orientativas).
- **Consultá la API solo para lo que realmente cambió.** Si únicamente necesitás saber si hay productos nuevos, no descargues todo el catálogo cada vez.

### Ejemplo práctico

Si tenés una tienda que muestra productos de **nubixstore** en tu propia página web:

1. Consultá la API una vez al día para obtener productos actualizados.
2. Guardalos en tu propia base de datos.
3. Tu página web lee desde tu base local, no desde la API directamente.
4. Tu página carga más rápido y no depende de la disponibilidad de la API en tiempo real.

---

## 3. No abuses de la frecuencia de consultas

### Qué significa "abusar"

- Hacer consultas cada pocos segundos sin necesidad.
- Repetir la misma consulta en un bucle.
- Descargar todo el catálogo varias veces por hora.

### Frecuencias orientativas por tipo de dato

| Tipo de dato | Frecuencia recomendada | Comentario |
|---|---|---|
| Catálogo de productos | Cada 6 a 24 horas | Suele cambiar poco a poco. |
| Stock | Cada 15 a 60 minutos | Depende del volumen de ventas. |
| Pedidos entrantes | Cada 5 a 15 minutos | Si necesitás reaccionar rápido a nuevos pedidos. |
| Datos maestros (clientes, listas de precios) | Cada 24 horas | Cambian con baja frecuencia. |

> Estas frecuencias son una guía. Cada negocio tiene su contexto: ajustalas según el volumen real de cambios.

> Si lo que estás armando es una **sincronización de catálogo hacia otra plataforma**, la [sección 14](#14-sincronizar-el-catálogo-con-otra-plataforma) tiene la estrategia completa: carga inicial, deltas diarios y reconciliación semanal, con el endpoint que corresponde a cada paso.

### Reglas generales

- **Si usás un agente de IA**, asegurate de que no consulte la API en cada interacción con el usuario. El agente debería trabajar con datos ya descargados y solo consultar la API cuando realmente necesite información nueva.
- **Evitá los reintentos infinitos.** Si una consulta falla, no la repitas inmediatamente cientos de veces. Ver [sección 5](#5-manejá-los-errores-con-inteligencia) para el patrón recomendado.

---

## 4. Cómo funciona la autenticación en la API de nubixstore

### Paso 1: Obtener el token

Para usar cualquier endpoint de la API, primero hay que obtener un **token de acceso**. Esto se hace mediante un endpoint especial:

- **Método:** `POST` (un pedido que envía datos al servidor para crear o iniciar algo).
- **URL:** `https://<tu-tienda>/usuarios/token`
- **Particularidad:** este es el **único endpoint que NO lleva el prefijo `/api/`** en la URL. Todos los demás endpoints sí lo llevan (por ejemplo `https://<tu-tienda>/api/articulos/items`).

Lo que enviás son tus credenciales de API (usuario, contraseña, API_KEY). Lo que recibís es una respuesta similar a esta (en formato JSON):

```json
{
  "code": 900,
  "status": 0,
  "msg": "Credenciales de acceso válidas. …",
  "access_token": "abc123xyz...",   // ← este valor es lo que vas a guardar y reutilizar
  "expires_in": 1777209460
}
```

> No hace falta entender cada símbolo del JSON. Lo importante es que la API te devuelve un valor llamado `access_token` y vos tenés que guardarlo de forma segura para usarlo en los pedidos siguientes.

> ⚠ **Si las credenciales son incorrectas, la respuesta llega igual con código HTTP 200**, pero con `"status": 2` y `"code": 907`. No des por logueado a tu sistema sólo porque el pedido "respondió bien": fijate que venga `access_token` y que `status` sea `0`.

### Paso 2: Usar el token

Una vez obtenido, el token debe incluirse en todas las consultas posteriores a la API. Sin token, los endpoints rechazan la solicitud.

### Cuándo renovar el token

El token **puede dejar de valer**, por ejemplo si se revoca por seguridad. La respuesta del login trae `expires_in`, la fecha de vencimiento prevista, pero **no te guíes sólo por ese valor**: lo que manda es lo que contesta la API. La estrategia recomendada es:

1. **Renovar bajo demanda:** si una consulta responde con `"code": 903` (**aunque el código HTTP diga 200**), pedí un token nuevo y reintentá la consulta original **una vez**. Si vuelve a fallar, no insistas: avisá a soporte.
2. **Refresco preventivo (red de seguridad):** adicionalmente, refrescar el token cada 6 a 12 horas como medida preventiva.

> **La API no responde `401` cuando el token está mal**, aunque es lo que esperan casi todas las herramientas: responde **200** con `"code": 903` adentro. Si tu sistema espera un `401` para renovar el token, no lo va a renovar nunca. Ver [sección 5](#5-manejá-los-errores-con-inteligencia).

> **Evitá renovar el token a ciegas en intervalos muy cortos.** Si el token sigue válido, renovarlo cada pocos minutos genera consultas innecesarias y va contra la regla de la [sección 3](#3-no-abuses-de-la-frecuencia-de-consultas).

---

## 5. Manejá los errores con inteligencia

Cuando la API responde con un error, tu sistema debe saber qué hacer.

### 🔴 Lo primero: mirá `status` **y** el código HTTP — uno solo no alcanza

Es el consejo más importante de todo este manual, y el que más caro sale ignorar.

Casi todos los tutoriales de integración te dicen "si la respuesta es 200, salió bien". **En la API de nubixstore eso no alcanza**, y hay casos concretos donde te va a mentir:

| Lo que pasa de verdad | Lo que devuelve la API | Lo que parece |
|---|---|---|
| Tu token está mal, falta, o no va en el formato `Authorization: Bearer <token>` | **HTTP 200**, con `"code": 903` y `"status": 2` en el cuerpo | ✅ "Salió bien" |
| La API está apagada en esa tienda | **HTTP 200**, con `"code": 905` y `"status": 2` | ✅ "Salió bien" |
| El módulo del endpoint está apagado (por ejemplo, el blog) | **HTTP 403**, con `"code": 910` y `"status": 2` | 🚫 Te lo dice: el módulo no está habilitado en esa tienda. En una tienda con una API anterior a la 2.6.0 es **HTTP 302** hacia una página web común: recibís HTML donde esperabas datos |

Y una trampa más, para quien ya aprendió a mirar `code`: **`"code": 900` tampoco alcanza solo.** Hay errores que llegan con `900`:

| Lo que pasa de verdad | Lo que devuelve la API |
|---|---|
| Borrar un cupón que no existe, o modificar un pedido que no existe o ya está finalizado | **HTTP 200**, `"code": 900`, **`"status": 2`** |
| Pedir los puntos de un cliente sin decir de qué cliente, o con un método que no corresponde | **HTTP 400**, `"code": 900`, `"status": 0` |

**Un sistema que mira una sola de estas señales va a dar por buena una consulta que falló, y va a guardar una respuesta vacía como si fuera un período sin ventas.** Le pasó a un equipo real integrando esta API.

**Las dos reglas que te protegen:**

1. **Una respuesta salió bien sólo si `status` es `0` y el código HTTP es `200` o `201`.** Si falla cualquiera de las dos, es un error, diga lo que diga el resto. `code` te dice **qué** pasó: guardalo en tu log y pasáselo a soporte. La única excepción es el `409` de las altas que se protegen solas contra el duplicado (ver la tabla de abajo y la [sección 11](#11-operaciones-que-modifican-datos-idempotencia)).
2. **Configurá tu cliente para que NO siga redirecciones.** En la mayoría de las herramientas es una opción llamada *"follow redirects"* o *"seguir redirecciones"*: apagala. Una redirección en un endpoint de datos siempre es un error, nunca una respuesta.

```
Salió bien:           HTTP 200   { "code": 900, "status": 0, "msg": "...", ...datos... }
Token incorrecto:     HTTP 200   { "code": 903, "status": 2, "msg": "Token incorrecto" }
Error con code 900:   HTTP 200   { "code": 900, "status": 2, "msg": "..." }
                                                  ▲
                         esto es lo que hay que mirar, junto con el código HTTP
```

> Si usás un agente de IA para armar la integración, **pedile explícitamente que valide `status === 0` y que el código HTTP sea 200 o 201**. Por defecto va a escribir sólo el chequeo del código HTTP, que acá no alcanza. Si ya le habías pedido que validara `code === 900` —era lo que recomendaba la versión anterior de este manual—, cambialo.

### Tipos de error y respuesta sugerida

| Código HTTP | Qué significa | Qué hacer |
|---|---|---|
| `200` **+ `status` 0** | Todo bien | Procesar la respuesta normalmente. |
| `201` **+ `status` 0** | Se creó el registro (un pedido, un presupuesto, un producto) | Guardar el identificador que devuelve la respuesta. |
| `200` **+ `code` 903** | ⚠ **Falló la autenticación** | Pedir un token nuevo y reintentar **una vez**. Si sigue, avisar a soporte. **No procesar la respuesta.** |
| `200` **+ `code` 905** | ⚠ **La API está deshabilitada en esa tienda** | No reintentar: un token nuevo no lo arregla. Avisar a la tienda. |
| `200` **+ `status` distinto de 0** | ⚠ El pedido no se hizo, aunque el HTTP diga 200 | Leer `msg` y `code`, registrarlo y **no procesar la respuesta**. |
| `302` | ⚠ En una tienda con una API anterior a la 2.6.0: el módulo de ese endpoint está apagado | No reintentar. Consultarle a soporte si ese módulo debería estar activo. |
| `400` | El pedido está mal armado | Revisar qué se envió. **No reintentar igual** — va a fallar igual. |
| `403` **+ `code` 910** | ⚠ **El módulo de ese endpoint está apagado en la tienda.** El cuerpo lo nombra en `module` | No reintentar: un token nuevo o más tiempo no lo arreglan. Consultarle a la tienda si ese módulo debería estar activo. |
| `403` | En lo que documenta la API pública aparece además en dos lugares: la documentación técnica (`/api_docs`) sin un token de documentación válido, y el alta de productos cuando la tienda no la activó | Revisar el token de documentación que te entregaron, o pedirle a la tienda que active el alta de productos por API. |
| `404` | Endpoint o recurso no existe. **En los listados del catálogo también quiere decir "no hay más páginas"** (ver [sección 10](#10-paginación-y-volumen-de-datos)) | Revisar la URL. No reintentar. |
| `409` | El registro ya existía (reenviaste algo que ya se había creado) **o** hay un problema con los datos (faltó uno obligatorio, o un código de producto ya lo usa otro producto) | Si la respuesta trae `duplicate: true` —o, en un pedido, el `orderID` previo—, tomalo como **hecho** y guardá ese identificador. Si no, es un error de datos: revisá lo enviado y no reintentes igual. El detalle de cada alta está en la [sección 11](#11-operaciones-que-modifican-datos-idempotencia). |
| `429` | **Sólo lo devuelve el alta de productos** (`POST /api/articulos/create`): se agotó el cupo de altas por día de la tienda (`"code": 909`) | No reintentar hasta el día siguiente. Ningún otro endpoint de la API lo devuelve hoy: si lo ves en otro lado, viene del servidor de la tienda (hosting o proxy), no de nubixstore, y lo que corresponde es esperar y bajar la frecuencia. |
| `500` y superiores | Problema del lado del servidor (en el alta de productos, un `503` quiere decir "hay otra alta en curso") | Reintentar con backoff (ver abajo). |

### Patrón de reintentos con backoff

Cuando una consulta falla por motivos temporales (errores 500, problemas de red), no la repitas inmediatamente. Esperá tiempos crecientes:

1. Primer reintento: a los **30 segundos**.
2. Segundo reintento: a los **2 minutos**.
3. Tercer reintento: a los **10 minutos**.
4. Si después de 3 intentos sigue fallando: **detenete** y registralo en tu log (ver [sección 12](#12-monitoreo-y-registros-logs)). Avisá a soporte si persiste.

### Reglas generales

- **Nunca ignores los errores.** Registralos siempre.
- **No reintentes los errores 400 y 404** — son problemas que no se solucionan reintentando.
- **No reintentes indefinidamente.** Tres reintentos suelen ser suficientes.
- **Antes de reintentar algo que crea o modifica datos**, leé la [sección 11](#11-operaciones-que-modifican-datos-idempotencia): hay altas que se pueden reenviar sin duplicar y otras que no.

---

## 6. Cuidá los datos que recibís

La API te puede devolver información sensible: precios, stock, datos de clientes, etc.

- **No muestres datos crudos en páginas públicas** sin antes validar qué información estás exponiendo.
- **Si almacenás datos de clientes**, asegurate de cumplir con la normativa vigente de protección de datos personales.
- **No compartas respuestas completas de la API con terceros** sin revisar el contenido.

---

## 7. Recomendaciones específicas para usuarios de agentes de IA

Si estás usando un agente de IA (como ChatGPT, Claude u otro) para construir tu integración:

- **El agente no conoce los detalles internos de la API de nubixstore** a menos que le proporciones la documentación. Compartile este manual y la documentación técnica.
- **Revisá el código que genera el agente antes de ponerlo en producción.** Un agente puede generar código funcional pero inseguro (por ejemplo, dejando el token expuesto en el frontend).
- **Pedile que decida si una respuesta salió bien mirando `status` (tiene que ser `0`) y el código HTTP (200 o 201)**, no sólo uno de los dos. Es la regla de la [sección 5](#5-manejá-los-errores-con-inteligencia), y la que un agente no va a aplicar por su cuenta.
- **Pedile al agente que te explique qué hace cada parte del código.** Si no entendés algo, preguntá. No pongas en funcionamiento algo que no comprendés.
- **No le des al agente acceso directo a tu token o credenciales reales.** Usá variables de entorno; el agente puede explicarte cómo hacerlo sin necesitar el valor real.
- **Probá en un entorno controlado primero.**
- **No pegues respuestas crudas de la API en el chat con el agente** si contienen datos personales de clientes (nombres, DNIs, direcciones, emails). Esos datos pueden quedar registrados en logs del proveedor de IA.
- **Cuidado con el "prompt injection".** Un texto guardado en tu base de datos —por ejemplo, el nombre de un producto manipulado por un usuario malintencionado, o una descripción cargada desde un proveedor— puede contener instrucciones ocultas que el agente interprete como órdenes. Si tu agente procesa contenido externo sin filtros, puede terminar haciendo cosas que no querías.

---

## 8. Usá siempre HTTPS

Toda comunicación con la API debe hacerse por **HTTPS** (URLs que empiezan con `https://`). Nunca por `http://`.

- **HTTPS** cifra los datos: si alguien intercepta la conexión, no puede leer tu token ni los datos enviados.
- **HTTP** envía todo en texto claro: cualquiera en la red puede leerlo.

Si en algún momento ves que tu sistema está armando una URL con `http://` apuntando a la API, **eso es un error de configuración** y debe corregirse antes de seguir.

---

## 9. Ambiente de prueba vs producción

Antes de conectar tu sistema con datos reales, conviene probarlo en un entorno controlado.

- **No uses tu cuenta real para pruebas.** Si no tenés un ambiente de prueba (sandbox), creá al menos un conjunto reducido de productos/clientes/pedidos de prueba y trabajá sobre eso.
- **Usá tokens distintos** para prueba y producción. Etiquetá claramente cuál es cuál (por ejemplo, en el nombre de la variable de entorno: `TOKEN_NUBIXSTORE_PROD` vs `TOKEN_NUBIXSTORE_TEST`).
- **Nunca uses el token de producción durante el desarrollo.** Un error en una prueba puede modificar datos reales (crear pedidos, mover stock, modificar precios).

---

## 10. Paginación y volumen de datos

Cuando pedís listados grandes (todo el catálogo, todos los pedidos de un período largo), la API no te devuelve todo de una vez: te devuelve **páginas** de resultados.

- **No intentes descargar todo de una sola consulta.** Puede colgar tu sistema o agotar la memoria.
- **Leé en lotes** (por ejemplo, de 100 en 100) y guardá el progreso. Si el proceso falla a mitad de camino, podés retomar desde la última página descargada.
- **Para sincronizaciones grandes**, planificá el horario en momentos de bajo tráfico (madrugada).
- **Pedir más registros por página que los permitidos no da error:** la API te devuelve hasta su tope (500 o 1000 según el endpoint) y sigue. Si pediste 5000 y llegaron 1000, no faltan datos: tenés que seguir pidiendo páginas.

### Cómo saber que llegaste a la última página

No es igual en todos los listados, y equivocarse acá es la forma más común de **bajar la mitad de un catálogo y creer que está completo**:

| Listado | Cómo te avisa que terminaste |
|---|---|
| Catálogo de productos (`/api/articulos/items`) y stock (`/api/articulos/stock`) | **Responde `404`** (con `"code": 702`) cuando pedís una página que ya no tiene productos. Seguí pidiendo páginas hasta recibir ese `404`. **Una página con menos productos que los que pediste NO quiere decir que terminaste**: puede pasar en el medio del catálogo. ⚠ Si filtrás por una marca que no existe, también es `404`, pero con `"code": 704`: no es "terminaste", es "la marca no existe". Mirá el `code` |
| Pedidos (`/api/pedidos/orders`) y presupuestos (`/api/presupuestos/budgets`) | Traen el dato `has_more`: cuando viene en `false`, no hay más páginas |
| El resto de los listados | Cuando una página trae menos registros que los que pediste |

> En el catálogo, ese `404` del final **no es un error**: no lo registres como falla ni lo reintentes.

---

## 11. Operaciones que modifican datos (idempotencia)

Algunas operaciones de la API **leen** datos (consultar productos, pedidos) y otras **modifican** datos (crear un pedido, actualizar stock, dar de alta un cliente).

Las operaciones que modifican datos requieren cuidado extra:

- **No las repitas "por las dudas".** Si tu sistema envía un pedido y no recibe respuesta clara, no asumas que falló y lo repitas. Podés terminar creando dos pedidos duplicados.
- **Antes de reintentar una operación de modificación**, **verificá** primero si ya se aplicó. Por ejemplo: consultar si el pedido ya existe en **nubixstore** antes de volver a crearlo.
- **Llevá un registro local** de qué operaciones de escritura ya enviaste y con qué identificador. Así podés diferenciar entre "tengo que reintentar" y "ya está hecho, no toco más".

### Qué operaciones se protegen solas contra el duplicado

| Operación | ¿Se puede reenviar sin duplicar? |
|---|---|
| Alta de pedido (`POST /api/pedidos/order`) | **Sí, pero sólo si mandás los datos del canal de venta con su número de orden** (`marketplace.code` y `marketplace.order_id`): el reenvío responde `409` con el `orderID` que ya existía. ⚠ Ese `409` viene con `"status": 2`, como un error: lo que te dice que ya estaba hecho es que **trae `orderID`**. Un `409` sin `orderID` es un error de verdad (faltó un dato, por ejemplo). **Sin esos dos datos, cada envío crea un pedido nuevo** |
| Alta de presupuesto (`POST /api/presupuestos/budget`) | Sólo si el presupuesto nace de un pedido (`source = 17` con el número de ese pedido en `source_ref_id`): responde `409` con `"status": 0`, `duplicate: true` y el `budgetID` previo. Con cualquier otro origen, cada envío crea uno nuevo |
| Alta de producto (`POST /api/articulos/create`) | **Sí, si mandás `ext_ref`** (tu identificador del producto): el reenvío responde `409` con `"status": 0`, `duplicate: true` y el `articleID` que ya se creó. Sin `ext_ref` tampoco se crea otro producto —el código ya está usado—, pero la respuesta es un error de código repetido (`409`, `"code": 703`) y no podés distinguir tu propio reintento de un código que ya existía de antes |
| Registrar un pago (`POST /api/pagos/payment`) | **No.** Cada envío registra un pago más |
| Cargar puntos a un cliente (`POST /api/usuarios/points/{id}`) | **No.** Cada envío vuelve a sumar los puntos |
| Descontar stock de un artículo (`POST /api/articulos/itemDiscountStock`) | **No.** Cada envío vuelve a descontar |
| Consultas (`GET`) | Sí, siempre |

En las tres que dicen **No**, la regla de arriba es obligatoria: antes de reenviar, verificá si ya se aplicó.

### Si vas a crear productos por API

El alta de productos (`POST /api/articulos/create`) tiene reglas propias, pensadas para que un error de tu integración no termine publicando productos en la tienda:

- **Viene apagada en cada tienda.** La tienda la activa desde su panel (Configuración de la API) y ahí mismo fija el **cupo de altas por día** (200 por defecto). Mientras esté apagada, responde `403`.
- **El producto nace deshabilitado y sin stock, "a moderar".** No se ve en la tienda hasta que alguien de la tienda lo **aprueba** desde el listado de productos del panel. El stock que mandás se guarda aparte y se aplica recién al aprobarlo. Hasta ese momento, el sistema de gestión de la tienda (su ERP) tampoco le actualiza precio ni stock.
- **Probá primero con `?dry_run=1`.** Valida todo —campos, marca, categorías, códigos repetidos— y te muestra cómo quedaría el producto, **sin crear nada y sin gastar cupo**.
- **Mandá siempre `ext_ref`**, tu identificador del producto. Es lo que permite reintentar sin miedo (ver la tabla de arriba).
- **Si algún código ya lo usa otro producto o variante de la tienda** —también los archivados—, no se crea nada y la respuesta es `409` con el detalle en `conflicts`.
- **Las imágenes van como direcciones públicas** (`http` o `https`). Se bajan **antes** de crear el producto: si una no se puede usar, no se crea nada y la respuesta (`400`) dice cuál falló.
- **Mandá los productos de a uno**, no en paralelo: si llega un alta mientras otra está en curso, puede responder `503` ("reintentar en unos segundos").
- **Si se agota el cupo del día**, la respuesta es `429` hasta el día siguiente. Para una carga grande, repartila en varios días o pedile a la tienda que suba el cupo.

Los nombres de los campos son los mismos que devuelve `GET /api/articulos/item`. El detalle de cada uno está en la documentación técnica de tu tienda.

---

## 12. Monitoreo y registros (logs)

Toda integración debe guardar un registro mínimo de su actividad. Sin esto, cuando algo falla es imposible diagnosticar el problema (ni vos ni el equipo de soporte de **nubixstore** puede ayudarte).

### Qué guardar como mínimo

- **Fecha y hora** de cada llamada.
- **Endpoint llamado** (qué le pediste a la API).
- **Resultado** (código de estado y si fue exitoso o no).
- **Identificador** de la operación, si aplica (por ejemplo, número de pedido).
- **Errores recibidos**, con el mensaje completo de la API.

### Qué NO guardar en los logs

- **Nunca registres el token completo** en los logs. Si necesitás dejar rastro, registrá solo los primeros 4 caracteres más `...`.
- **Evitá registrar contraseñas, números de tarjeta o datos personales sensibles** en texto claro.

### Rotación de logs

- Los archivos de log crecen. Configurá una rotación (por ejemplo, archivo diario que se conserva 30 días).

---

## 13. Backups de tu base local

Si seguiste la recomendación de la [sección 2](#2-creá-tu-propia-base-de-datos-local) y armaste una base local, **respaldala periódicamente**.

- Hacé backups diarios automáticos.
- Guardá los backups en un lugar separado del servidor principal.
- Probá ocasionalmente que los backups se puedan **restaurar** (un backup que nunca se probó puede estar corrupto).

---

## 14. Sincronizar el catálogo con otra plataforma

Este capítulo es para el caso concreto de **espejar el catálogo de nubixstore en otro sistema**: un marketplace, una tienda propia, un ERP, un comparador de precios, un catálogo para vendedores. Es el escenario donde más plata se pierde por decisiones que parecen menores al principio.

### 14.1 Los tres códigos de producto

**nubixstore** maneja **tres** códigos por producto, y desde la versión 2.3.0 de la API los tres viajan en todos los endpoints de lectura del catálogo:

| Campo en la API | Cómo se llama | Quién lo define | Para qué sirve |
|---|---|---|---|
| `sku` | SKU interno | La tienda | Identificar el producto dentro de nubixstore |
| `barcode` | Código de barras (EAN) | El fabricante, estándar global | Cruzar con cualquier sistema que trabaje con códigos de barras |
| `skuf` | SKU del fabricante (SKU-F) | El fabricante o proveedor | Cruzar contra listas de precios y catálogos del proveedor |

> **Los tres son opcionales a nivel de datos.** Hay productos con los tres cargados, productos con uno solo y productos con ninguno. **No asumas que tu clave preferida siempre está.**

### 14.2 Cómo elegir la clave de matching

Esta es **la** decisión del proyecto. Recomendación, en orden:

1. **Usá el `id` como identidad, siempre.** El `id` del artículo (y el `id` de cada variante) es el único valor que está siempre presente y no cambia. Guardalo en tu base junto a cada producto tuyo. Es lo que te permite volver a encontrar el producto aunque le cambien el nombre, el precio o hasta el código.
2. **Usá los códigos sólo para el cruce inicial**, cuando todavía no tenés la relación armada: primero probá por `barcode`, después por `sku`, después por `skuf`. Una vez que encontraste el par, **guardá el `id`** y no vuelvas a cruzar por texto.
3. **Nunca uses el nombre del producto como clave.** Cambia por motivos comerciales todo el tiempo.

> **Por qué el `id` y no el código:** si mañana la tienda le corrige el código de barras a un producto, un matching por código lo va a tomar como un producto nuevo y vas a terminar con duplicados en tu plataforma. Con el `id` guardado, es el mismo producto con un código actualizado.

### 14.3 La trampa del código vacío

Cuando un producto no tiene un código cargado, **la API no siempre lo representa igual**. Verificado sobre la API 2.4.0:

| Dónde estás mirando | `sku` sin cargar | `barcode` sin cargar | `skuf` sin cargar |
|---|---|---|---|
| El producto en `/api/articulos/item` o `/items` | vacío nulo | **texto vacío** | **texto vacío** |
| Una variante dentro de `variants` | vacío nulo | vacío nulo | vacío nulo |
| Una fila de `/api/articulos/stock` | vacío nulo | vacío nulo | vacío nulo |

En criollo: a veces "sin código" llega como **nulo** y a veces como **texto vacío**. Son dos cosas distintas para casi cualquier lenguaje de programación.

**Qué pedirle a tu programador o a tu agente de IA:** *"tratá como 'sin código' tanto el valor nulo como el texto vacío, en los tres campos"*. Si sólo se controla el nulo, el sistema va a dar por válido un código vacío y va a intentar publicar productos con código en blanco — que después chocan entre sí en la plataforma destino.

### 14.4 Buscar por código: qué se puede y qué no

- Los endpoints de catálogo aceptan buscar por código con los parámetros `sku`, `code` o `codes`.
- Esa búsqueda mira **el SKU interno y el código de barras**. **No mira el `skuf`.**
- **Si tu plataforma trabaja con el SKU del fabricante**, no vas a poder pedirle a la API "dame el producto con este SKU-F". Tenés que **bajar el catálogo, guardar el `skuf` en tu base y hacer el cruce de tu lado**. Es una limitación conocida de la API, no un error de tu integración.

### 14.5 Qué endpoint usar para cada cosa

| Necesito… | Endpoint | Por qué ése |
|---|---|---|
| Bajar el catálogo completo la primera vez | `GET /api/articulos/items` | Es el único que devuelve **también los productos sin ningún código cargado**. Trae precios, imágenes, variantes y los tres códigos |
| Refrescar sólo el stock, seguido | `GET /api/articulos/stock` | Devuelve poco por producto, así que es mucho más liviano. Viene **indexado por código**, listo para consultar de a uno |
| Un producto puntual, con toda su ficha | `GET /api/articulos/item` | Devuelve más campos que el listado (descripción, atributos de variante) |
| Enterarme de productos nuevos | `GET /api/articulos/new_codes/{días}` | Devuelve sólo los códigos dados de alta en los últimos N días |
| Enterarme de qué cambió | `GET /api/articulos/change_codes/{días}` | Devuelve los códigos con modificaciones en los últimos N días |

> ⚠ **`/api/articulos/stock` no devuelve todo el catálogo.** Como está indexado por código, **los productos que no tienen ni SKU ni código de barras no aparecen**. Es normal que te devuelva menos productos de los que esperabas: no es un error ni una caída. Para el catálogo completo, siempre `/api/articulos/items`.

> ⚠ **`new_codes` y `change_codes` no devuelven el `skuf`**, sólo `sku` y `barcode`. Usalos para saber **qué** cambió y después pedí el detalle con `/api/articulos/items`.

> **¿Sincronizás sólo algunas marcas?** Desde la API 2.5.0, `new_codes` y `change_codes` aceptan el mismo filtro que el catálogo: `brandId` o `brandCode` ( podés pasar varias, separadas por coma ). Así el delta te trae sólo lo tuyo. Si la marca no existe, la respuesta es `404` con `"code": 704`.

> **Las marcas deshabilitadas no se ven ( API 2.5.0 ).** La API te muestra el catálogo igual que la web: si la tienda deshabilita una marca, sus productos dejan de aparecer en todos los endpoints de catálogo, como pasa con los productos archivados o borrados. Por eso `GET /api/marcas/list` lista sólo las marcas habilitadas. Los productos **deshabilitados** sí siguen viniendo, con `enabled = 0`.

### 14.6 Productos con variantes

Si el producto tiene variantes (talles, colores, presentaciones), la API las devuelve en una lista `variants` dentro del producto, y **cada variante trae sus propios tres códigos y su propio stock**.

Reglas prácticas:

- **Publicá una variante por cada combinación**, no el producto padre con el stock sumado. El stock del padre no es la suma de sus variantes.
- **La clave de cada variante es el `id` de la variante**, no el del producto.
- Si tu plataforma destino **no soporta variantes**, pedile a la API el listado con la opción `compact=false`: los productos con precio por rango vienen ya desarmados, un renglón por combinación.
- El campo que dice si la variante está activa **se llama distinto según el endpoint**: `enabled` en el listado (`/items`) y `enable` en el producto individual (`/item`). **Contemplá los dos nombres.** Es una inconsistencia conocida que no se corrigió justamente para no romper a quienes ya leen uno de los dos.

### 14.7 Estrategia de sincronización recomendada

1. **Carga inicial (una vez):** bajá el catálogo completo con `/api/articulos/items`, paginando. Guardá en tu base, por cada producto y cada variante: el `id`, los tres códigos, el precio, el stock y la fecha en que lo bajaste.
2. **Delta diario:** consultá `change_codes` y `new_codes` con la ventana que corresponda, y pedí el detalle **sólo de lo que aparezca ahí**.
3. **Stock, más seguido:** refrescá con `/api/articulos/stock` a la frecuencia de la [sección 3](#3-no-abuses-de-la-frecuencia-de-consultas), sin volver a bajar la ficha completa.
4. **Reconciliación semanal:** una vez por semana, bajá el catálogo completo otra vez y compará contra tu base. Es la red que agarra lo que los deltas se pierden (por ejemplo, un producto que se deshabilitó sin que se tocara su código).
5. **Nunca borres de tu lado por ausencia.** Que un producto no venga en una respuesta puede significar que se archivó, que la tienda deshabilitó su marca, que no tiene código, o que la consulta falló a mitad de camino. Marcalo como "no visto desde X" y decidí recién después de varias corridas seguidas. Los deltas no avisan de estas bajas: las detecta la reconciliación del punto 4.

### 14.8 Errores frecuentes en sincronizaciones de catálogo

| Error | Qué pasa | Cómo evitarlo |
|---|---|---|
| Usar el código como identidad | Cambia un código y aparecen productos duplicados | Guardar el `id` |
| Tomar `""` como código válido | Se publican productos con código en blanco que chocan entre sí | Tratar nulo y texto vacío como lo mismo |
| Publicar el producto padre en vez de las variantes | El stock publicado no coincide con el real | Una publicación por variante |
| Usar `/stock` para bajar el catálogo | Faltan los productos sin código, en silencio | Usar `/items` para el catálogo |
| Bajar todo el catálogo cada hora | Carga innecesaria y sincronizaciones lentas | Carga inicial + deltas + reconciliación semanal |
| Borrar lo que no vino en la respuesta | Se vacía el catálogo destino ante un error temporal | Marcar "no visto" y esperar varias corridas |
| Leer sólo `enabled` en variantes | Todas las variantes figuran deshabilitadas al usar `/item` | Leer `enabled` y `enable` |

---

## 15. Resumen de buenas prácticas

| Práctica | Por qué |
|----------|---------|
| Guardar el token en un lugar privado | Evita que terceros accedan a tu cuenta |
| No poner el token en código visible del navegador | Cualquiera podría verlo |
| Usar siempre HTTPS | Cifra los datos en tránsito |
| Crear una base de datos local | Reduce consultas innecesarias y mejora la velocidad |
| Sincronizar periódicamente (no en tiempo real) | Equilibra datos frescos con uso responsable de la API |
| Renovar el token cuando la API responde `code` 903 (aunque el HTTP sea 200) y refrescarlo cada 6-12 h | Evita renovaciones innecesarias y caídas por token rechazado |
| Dar por buena una respuesta sólo con `status` 0 **y** código HTTP 200 o 201 | El código HTTP solo, o `code` 900 solo, dejan pasar errores |
| Usar reintentos con backoff (30 s, 2 min, 10 min) | Evita sobrecargar la API y tu propio sistema |
| Separar ambiente de prueba y producción | Evita modificar datos reales por accidente |
| Leer listados grandes con paginación | Evita colgar el sistema con descargas masivas |
| En el catálogo, seguir pidiendo páginas hasta el `404` | Una página con menos productos no es la última |
| Verificar antes de reintentar operaciones de escritura | Previene duplicados (pagos, puntos y stock no se protegen solos) |
| Mandar `ext_ref` al crear productos, y probar antes con `dry_run` | Permite reintentar sin duplicar y validar sin crear |
| Guardar logs (sin tokens ni datos sensibles) | Permite diagnosticar problemas |
| Hacer backups de la base local | Protege contra pérdida de datos |
| Revisar el código generado por IA | Evita vulnerabilidades no intencionales |
| No pegar datos personales en chats con IA | Evita filtrar información sensible |
| No compartir respuestas crudas de la API | Pueden contener datos sensibles |
| Usar el `id` como identidad del producto, no el código | Un código puede corregirse; el `id` no cambia |
| Tratar el código vacío como nulo **y** como texto vacío | La API usa las dos formas según dónde mires |
| Bajar el catálogo con `/items`, no con `/stock` | `/stock` omite los productos sin código |
| Publicar una entrada por variante | El stock del producto padre no es la suma de sus variantes |
| Nunca borrar del destino sólo porque un producto no vino | Una respuesta incompleta no significa que se dio de baja |

---

## 16. Preguntas frecuentes (FAQ)

**¿Puedo usar el mismo token desde varios sistemas a la vez?**
Sí, técnicamente se puede, pero no es recomendable. Si el token se compromete en uno de los sistemas, todos quedan expuestos. Conviene que cada sistema tenga **su propio usuario de API**: pedíselo a soporte.

**¿Qué pasa si pierdo el token o creo que se filtró?**
Avisá a soporte de inmediato para que lo revoquen. **Pedir un token nuevo a `/usuarios/token` no invalida el anterior**: hoy la revocación la hace el equipo de **nubixstore**. Si sospechás de un acceso no autorizado, decilo en el mismo aviso.

**¿La API funciona 24/7?**
La API está disponible permanentemente, pero pueden existir ventanas de mantenimiento o caídas puntuales. Por eso es importante manejar bien los errores ([sección 5](#5-manejá-los-errores-con-inteligencia)) y tener una base local que permita seguir operando aunque la API esté momentáneamente indisponible.

**¿Puedo testear sin afectar mi tienda real?**
Sí, lo recomendado es usar un conjunto de datos de prueba o un ambiente sandbox si está disponible. Nunca pruebes con tu cuenta real.

**¿Cuántas consultas por minuto puedo hacer?**
La API no publica un límite fijo de consultas por minuto. El único límite que aplica hoy es el **cupo diario del alta de productos**, que responde `429` cuando se agota (ver [sección 11](#11-operaciones-que-modifican-datos-idempotencia)). Eso no es una invitación a consultar sin medida: respetá las frecuencias de la [sección 3](#3-no-abuses-de-la-frecuencia-de-consultas), porque el límite real es no degradar tu propia tienda. Y si recibís un `429` de otro endpoint, viene del servidor de la tienda (hosting o proxy): esperá y bajá la frecuencia.

**Mi integración validaba `code === 900`, como decía este manual. ¿Tengo que cambiarla?**
Sí. Hay errores que llegan con `"code": 900` (ver [sección 5](#5-manejá-los-errores-con-inteligencia)). La regla correcta es: **`status` igual a `0` y código HTTP 200 o 201**. Seguí guardando `code` en tu log: es el que dice qué pasó.

**¿Necesito ser programador para usar la API con un agente de IA?**
No necesariamente, pero **sí** es recomendable que entiendas los conceptos básicos del [glosario](#glosario-rápido). Si vas a manejar pagos, datos sensibles o alto volumen, contratá a un profesional.

**¿La API puede borrar datos de mi tienda por accidente?**
Solo si tu sistema le pide explícitamente que lo haga. Los endpoints que modifican o borran datos requieren autenticación y pedidos específicos. Pero un error en tu integración puede causar borrados no deseados, por eso es crítico probar primero en ambiente de prueba.

**¿Puedo usar este manual como única referencia técnica?**
No. Este manual cubre buenas prácticas generales. Para los detalles específicos de cada endpoint (qué datos enviar, qué se recibe), consultá la documentación técnica oficial de la API de **nubixstore**, disponible como Swagger en `https://<tu-tienda>/api_docs`.

**¿Cuál de los tres códigos uso para cruzar mi catálogo con el de nubixstore?**
Para el **cruce inicial**, probá en este orden: código de barras (`barcode`), SKU interno (`sku`), SKU del fabricante (`skuf`). Una vez que encontraste el par, guardá el **`id`** del producto y usá ese de ahí en adelante. Ver [sección 14.2](#142-cómo-elegir-la-clave-de-matching).

**Pedí el stock y me devolvió menos productos de los que tengo. ¿Está roto?**
No. `/api/articulos/stock` está indexado por código y **no devuelve los productos que no tienen ni SKU ni código de barras**. Para el catálogo completo usá `/api/articulos/items`. Ver [sección 14.5](#145-qué-endpoint-usar-para-cada-cosa).

**¿Puedo buscar un producto por el SKU del fabricante?**
No directamente: la búsqueda por código mira el SKU interno y el código de barras, no el `skuf`. Tenés que bajar el catálogo, guardar el `skuf` en tu base y hacer el cruce de tu lado. Ver [sección 14.4](#144-buscar-por-código-qué-se-puede-y-qué-no).

**Todas las variantes me figuran deshabilitadas. ¿Por qué?**
Casi seguro estás leyendo sólo una de las dos claves: el listado (`/items`) usa `enabled` y el producto individual (`/item`) usa `enable`. Contemplá las dos. Ver [sección 14.6](#146-productos-con-variantes).

---

## 17. Contacto y soporte

Si tenés dudas sobre el uso de la API o necesitás asistencia técnica, contactá al equipo de soporte de **nubixstore** a través de los canales oficiales disponibles en [nubixstore.ar](https://www.nubixstore.ar).

---

## Historial de versiones

| Versión | Fecha | Agente | Modelo | Cambios |
|---------|-------|--------|--------|---------|
| **1.6.0** | 2026-11-04 | `claude` (modo general) | `claude-opus-5-5` | **API 2.6.0.** Un endpoint de un módulo apagado responde HTTP 403 con `"code": 910`, y ya no redirige a una página web. Sale con nubixstore 13.11.1. |
| **1.5.0** | 2026-10-19 | `claude` (modo general) | `claude-opus-5-5` | **API 2.5.0.** Las marcas deshabilitadas no se ven por la API, igual que en la web: sus productos dejan de aparecer en los endpoints de catálogo, como los archivados (§14.5). `new_codes` y `change_codes` aceptan `brandId` / `brandCode` para traer sólo algunas marcas (§14.5). Una marca del filtro inexistente responde `404` con `code` 704, distinto del `404` / 702 que marca el fin de la paginación: hay que mirar el `code` (§10). La estrategia de sincronización suma la marca deshabilitada como motivo de una ausencia (§14.7). |
| **1.4.0** | 2026-10-15 | `claude` (modo general) | `claude-opus-5-5` | **Se corrigió la regla de éxito, que la 1.3.0 había dejado incompleta.** "Validá `code === 900`" no alcanza: hay errores que llegan con `code` 900 y `status` 2 (borrar un cupón inexistente, modificar un pedido finalizado) y con `code` 900, `status` 0 y HTTP 400 (puntos de un cliente sin indicar el cliente). La regla pasa a ser **`status` 0 y HTTP 200 o 201**, con `code` como detalle (§5, glosario, §7, resumen y una pregunta nueva en el FAQ para quien ya había implementado la regla vieja). Tabla de errores rehecha contra el código: `903` y `905` separados (un token nuevo no arregla una API apagada), `201` y `409` nuevos, el `403` sólo existe en la documentación técnica y en el alta de productos apagada, y **el `429` sólo lo emite el alta de productos** (cupo diario, `code` 909): la API no tiene un límite de consultas por minuto. El rechazo de autenticación sale **HTTP 200 con `code` 903, nunca `401`** (BUG-513): corregidos el diagrama del ciclo básico y la renovación del token, que decían `401`/`403`. El login fallido también sale 200 (`code` 907). Paginación: cómo saber que terminó cada listado (el catálogo corta con `404`, pedidos y presupuestos con `has_more`). §11: **qué altas se pueden reenviar sin duplicar y cuáles no** (pagos, puntos y descuento de stock no se protegen), y una sección nueva para el **alta de productos por API** (`POST /api/articulos/create`): viene apagada, el producto queda a moderar, `dry_run`, `ext_ref`, cupo diario. FAQ: un token filtrado lo revoca soporte (pedir uno nuevo no invalida el anterior), un usuario de API por sistema. Distribución: el portal sin token se apagó; el manual se publica en el Centro de ayuda. |
| **1.3.0** | 2026-08-19 | `claude` (modo general) | `claude-opus-5[1m]` | **Se corrigió el consejo más importante del manual, que estaba mal.** La tabla de errores de la §5 decía que un fallo de autenticación devuelve `401`/`403`; **devuelve HTTP 200** con el error en el cuerpo (BUG-513), así que un sistema que confía en el código HTTP da por buena una consulta que falló y guarda una respuesta vacía como si fuera un período sin ventas — le pasó a un equipo real integrando esta API. Bloque nuevo al inicio de la §5: **mirá `code`, no el HTTP**, con la tabla de los tres casos en que el status miente y las dos reglas concretas que protegen (validar `code === 900` y **apagar el seguimiento de redirecciones**). Se sumó el caso `302`, que aparece cuando el módulo del endpoint está apagado en la tienda y devuelve una página HTML donde se esperan datos (BUG-522). Nota específica para quien arma la integración con un agente de IA: hay que **pedirle explícitamente** que valide `code`, porque por defecto escribe el chequeo HTTP estándar. Además el manual estrena **versionado semver propio** y declara su distribución (🌐 pública) para el catálogo de descargas de nsCentral. |
| 1.0 | 2026-04-25 | Claude Code | `claude-opus-4-6` | Versión inicial: token, base local, frecuencia, autenticación, errores, datos sensibles, agentes de IA. |
| 1.1 | 2026-04-25 | Claude Code | `claude-opus-4-7` | Glosario, diagrama de flujo, ejemplos bien/mal, ejemplo de respuesta del endpoint `/usuarios/token`. Nuevas secciones: HTTPS, ambiente de prueba vs producción, paginación, idempotencia, logs, backups, cuándo pedir ayuda profesional, FAQ. Refinamiento del consejo de renovación de token (bajo demanda + preventivo). Tabla de frecuencias por tipo de dato. Patrón de backoff explícito. Riesgos de prompt injection y datos personales en chats con IA. Tabla de versiones e índice clickeable. |
| 1.2 | 2026-08-06 | `claude` (modo general) | `claude-opus-5[1m]` | **Nueva sección 14 — Sincronizar el catálogo con otra plataforma**, escrita para el caso de espejar el catálogo de nubixstore en un sistema externo: los tres códigos de producto (`sku`, `barcode`/EAN, `skuf`/SKU-F, disponibles en todos los endpoints de catálogo desde la API 2.3.0), cómo elegir la clave de matching (usar el `id` como identidad, los códigos sólo para el cruce inicial), la **representación no uniforme del código vacío** (nulo vs texto vacío según dónde se lo mire), qué endpoint usar para cada cosa y por qué `/stock` **no** sirve para bajar el catálogo completo, que `skuf` **no es criterio de búsqueda**, el manejo de variantes (incluida la divergencia `enabled`/`enable` entre endpoints), la estrategia carga inicial + delta + reconciliación semanal, y una tabla de los 7 errores frecuentes en sincronizaciones de catálogo. Glosario ampliado con SKU, EAN, SKU-F, variante, clave de matching y sincronización delta. Cinco preguntas nuevas en el FAQ y seis filas nuevas en el resumen. Se corrigió el enlace roto de la introducción y se agregó el puntero al Swagger de la tienda. Renumeración: Resumen 14→15, FAQ 15→16, Contacto 16→17. |

---

*Este manual se actualizará a medida que se incorporen nuevas funcionalidades o cambien las condiciones de uso de la API.*
