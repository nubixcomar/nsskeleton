# Referencia de la API de **nubixstore**

**Versión del manual:** `2.6.0+09a6bd63` — **Fecha:** 2026-11-04
**Distribución:** 🌐 **pública** — se entrega a integradores y clientes, y se descarga sin token
**Documenta la API:** `2.6.0` (`NS_API_VERSION`)
**Endpoints documentados:** 67

> El sufijo `+09a6bd63` de la versión es un hash del contenido: cambia exactamente cuando
> cambia este documento. La versión de la API sola no alcanza como identidad — pueden entrar
> endpoints nuevos sin que `NS_API_VERSION` se mueva, si no cambia ningún contrato.

> 🤖 **ESTE ARCHIVO SE GENERA. NO LO EDITES A MANO.**
> Sale de `ApiEndpointRegistry` (`app/Lib/ApiDocs/`) vía `MarkdownBuilder`, la misma fuente
> de la que sale el Swagger. Para cambiar algo de acá, cambiá la definición del endpoint
> en el registry y volvé a correr `php bin/dev/build-api-manual.php`. Cualquier edición
> manual se pierde en la próxima generación.

> Esta es la referencia **pública**: contiene los endpoints pensados para integraciones
> de terceros. nubixstore expone además endpoints de infraestructura y operación interna
> que no se documentan acá. Si necesitás alguno en particular, escribinos y lo evaluamos.

> ℹ **Este documento describe QUÉ existe.** El contexto —trampas conocidas, bugs abiertos
> que afectan el consumo, por qué `/stock` tiene otro contrato que `/items`— vive en
> `manual-api-nubixstore.md`, que se escribe a mano justamente porque eso no se puede generar.

---


## Autenticación

Todos los endpoints exigen un **Bearer token** en el header. El único que no lo pide es
`POST /usuarios/token`, que es justamente el que lo entrega.

> ⚠ **La ruta de login es `/usuarios/token`, SIN el prefijo `/api`.** Pegarle a
> `/api/usuarios/token` devuelve `{"code":903,"msg":"Token vacío"}`, que parece un problema
> de credenciales y no lo es.

```bash
# 1) Login
TOKEN=$(curl -s -X POST -H 'Content-Type: application/json' \
  -d '{"USER":"…","PASSWORD":"…","API_KEY":"…"}' \
  "https://tienda.com.ar/usuarios/token" | jq -r .access_token)

# 2) Consumir
curl -s -H "Authorization: Bearer $TOKEN" "https://tienda.com.ar/api/articulos/items"
```

**Guardá la terna `USER` / `PASSWORD` / `API_KEY`, no el token.** El token se pide cuando hace
falta. Hoy es determinístico y "funciona" guardarlo para siempre, pero la rotación de
credenciales está decidida como producto: la integración que hardcodea un token deja de
funcionar ese día y hay que recargarla a mano, tienda por tienda.

---


## Formato de respuesta

```json
{ "code": 900, "status": 0, "msg": "Mensaje descriptivo", "…": "payload del endpoint" }
```

### 🔴 Éxito = `status === 0` y HTTP 2xx. No decidas sólo por el status HTTP ni sólo por `code`.

Es la trampa que más caro sale de esta API, y afecta a **todos** los endpoints:

| Caso | HTTP real | `code` |
|---|---|---|
| Token vacío, incorrecto o vencido | **200** ⚠ | `903` |
| Módulo API deshabilitado en la tienda | **200** ⚠ | `905` |
| Módulo del endpoint deshabilitado en la tienda | **403** | `910` |

Un rechazo de autenticación sale con **HTTP 200** y el error en el body: el acceso sí se
deniega, lo único que miente es el status. Si el módulo al que pertenece el endpoint está
apagado en esa tienda, responde **HTTP 403** con `code` 910 y el módulo en `module`, salvo los
`cron_list`, que responden igual. Reintentar no lo arregla: lo arregla prender el módulo.
En una tienda con una API anterior a la 2.6.0, lo mismo es una **redirección a una página
HTML**, no un JSON.

**Dos defensas concretas en el cliente HTTP:**

1. Validar `status === 0` **y** HTTP 2xx antes de leer el payload; `code` da el detalle de qué
   pasó. Un `raise_for_status()` a secas toma un fallo de credenciales por éxito y guarda datos
   vacíos sin ningún error visible. Y `code === 900` solo tampoco alcanza: hay errores que salen
   con `code` 900 y `status` 2, y otros con `code` 900, `status` 0 y HTTP 400 (BUG-1116).
2. **No seguir redirecciones** (`allow_redirects=False` / `CURLOPT_FOLLOWLOCATION => false`).
   Un 302 en un endpoint de API es siempre un error, nunca una respuesta.

---


## Índice

- [Auth](#auth) — 1 endpoint
- [Artículos](#articulos) — 4 endpoints
- [Artículos — Stock](#articulos-stock) — 2 endpoints
- [Artículos — Sync](#articulos-sync) — 2 endpoints
- [Artículos — Alta](#articulos-alta) — 1 endpoint
- [Marcas](#marcas) — 1 endpoint
- [Pedidos](#pedidos) — 8 endpoints
- [Pagos](#pagos) — 6 endpoints
- [Blog](#blog) — 2 endpoints
- [Campañas](#campanas) — 1 endpoint
- [Cupones](#cupones) — 2 endpoints
- [Depósitos](#depositos) — 4 endpoints
- [Envíos](#envios) — 5 endpoints
- [Garantías](#garantias) — 5 endpoints
- [Inbox](#inbox) — 1 endpoint
- [Marketplaces](#marketplaces) — 1 endpoint
- [Newsletters](#newsletters) — 2 endpoints
- [Presupuestos](#presupuestos) — 6 endpoints
- [Returns](#returns) — 1 endpoint
- [Usuarios](#usuarios) — 7 endpoints
- [Utils](#utils) — 5 endpoints

---


## Auth

Autenticación y tokens

### `POST /usuarios/token`

**Obtener token de acceso**

Login de integradores. Enviar `USER`, `PASSWORD` y `API_KEY` en un JSON. Si son válidas devuelve `access_token` (usar como Bearer) y `expires_in`. **Responde siempre HTTP 200, también ante credenciales inválidas**: decidir por `status === 0` (éxito, `code` 900) / `code` 907 (error de login). **`expires_in` es un timestamp UNIX (ahora + 24 h) pero hoy NO se valida (BUG-515)**: el token sigue autenticando después de esa fecha. El token es determinístico: cada login devuelve el mismo valor, y volver a loguear no lo revoca. `API_KEY` la exige la mayoría de las cuentas; algunas cuentas están exceptuadas por configuración. **Qué catálogo ve este token (API 2.5.0):** el mismo que la web pública. Los artículos de una **marca deshabilitada** no aparecen en `/api/articulos/item`, `items`, `stock`, `combo_details`, `new_codes` ni `change_codes`, igual que los archivados y los borrados, y filtrar por esa marca responde 404 / `code` 704. Los artículos deshabilitados sí viajan, con `enabled = 0`.

**Body** *(obligatorio)*

Esquema: [`AuthTokenRequest`](#authtokenrequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Respuesta de autenticación, válida o inválida (siempre HTTP 200; ver `code`) | [`AuthTokenResponse`](#authtokenresponse) |


## Artículos

Endpoints de artículos y categorías

### `GET /api/articulos/item`

**Obtener un artículo por ID, variante o código**

Retorna un artículo individual. Incluye imágenes, variantes, precios, stock y datos de marketplace. **Se busca por cualquiera de estas claves — hay que enviar al menos una:** `id` (artículo), `oid` (opción/variante), o `sku` / `code` (que resuelven contra el **SKU interno y el código de barras**, en ese orden; `code` es alias de `sku`). Si ninguna resuelve contra un artículo, se reintenta contra las **opciones**. **Devuelve los tres códigos del producto** — `barcode` (EAN), `sku` (SKU interno) y `skuf` (SKU del fabricante) — a nivel ítem y dentro de cada elemento de `variants[]`. Si la búsqueda resuelve a una variante, los tres códigos a nivel ítem se pisan con los de esa opción. Ver el schema `ArticuloItem` para cómo se representa un código vacío: **no es uniforme entre el nivel ítem y `variants[]`**. ⚠ Dentro de `variants[]` este endpoint emite el flag de habilitación como **`enable`**, mientras que `GET /api/articulos/items` lo emite como **`enabled`**. ⚠ En este endpoint `variants` es un **OBJETO** con claves `"1"`, `"2"`…, no un array como en `/items`, y la clave no aparece si el artículo no tiene opciones (BUG-439). **Visibilidad (API 2.5.0):** con el token de `POST /usuarios/token`, un artículo de una **marca deshabilitada** responde 404 / `code` 702, igual que uno archivado o borrado. Vale para las cuatro claves de búsqueda.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | query | no | integer | ID del artículo. Hay que enviar al menos una de `oid` / `id` / `sku` / `code`. Si llegan varias, manda `oid`, después `id`, después `sku` (`code` sólo si `sku` viene vacío). Incluye artículos deshabilitados; no devuelve archivados ni borrados ni, con el token de un integrador, los de marcas deshabilitadas. |
| `oid` | query | no | integer | ID de la opción (variante). Devuelve el artículo con los códigos, el precio y el stock de esa variante (el `committed_stock` sigue siendo el del artículo, BUG-1121). Desde la API 2.5.0 aplica la misma visibilidad que `id`: no devuelve opciones de artículos archivados, borrados ni, para un integrador, de marcas deshabilitadas. ⚠ Mandarlo junto con `variants=false`: con el default, la request falla con error 500 al armar la lista de variantes (BUG-1119). |
| `sku` | query | no | string | SKU interno o código de barras (EAN). Busca en `codigo` y `sku` del artículo y, si no hay match, en los de las opciones. Alias: `code`. `skuf` no es criterio de búsqueda. |
| `code` | query | no | string | Alias de `sku`. Se usa sólo si `sku` viene vacío. |
| `variants` | query | no | string (`true` · `false`), default `true` | Incluye `variants` con las opciones del artículo. **Default: activo.** Sólo el valor literal `true` lo mantiene: cualquier otro valor, incluido `1`, lo APAGA (BUG-514). En este endpoint `variants` es un **objeto** con claves `"1"`, `"2"`…, y no aparece si el artículo no tiene opciones. |
| `in_stock` | query | no | integer (`0` · `1`), default `0` | Con cualquier valor no vacío distinto de `0`, devuelve sólo las variantes cuyo estado de stock es disponible. Actúa sólo en la búsqueda por `id` o por el `sku` del artículo padre. |
| `html` | query | no | integer (`0` · `1`), default `0` | `body` viaja siempre, con la descripción en texto plano. Con `html=1` pasa a ser la descripción HTML y además viaja `descripcion` con el mismo contenido. Se castea a entero: `html=true` vale 0. |
| `client_type` | query | no | integer, default `0` | ID de tipo de cliente, para que los precios devueltos sean los de ese segmento. |
| `mercadolibre` | query | no | string (`true` · `false`), default `false` | Con `true` calcula los precios de marketplace (`marketplace_*`). Cualquier otro valor equivale a `false`. |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Ítem encontrado | [`ArticuloItemResponse`](#articuloitemresponse) |
| `400` | No se envió ninguna clave de búsqueda (`id`, `oid`, `sku` o `code`): code 701 | [`ApiError`](#apierror) |
| `401` | Token faltante o inválido. ⚠ Hoy el status HTTP real es 200 con code 903 (token) o 905 (API deshabilitada): validar status === 0 y HTTP 2xx, no sólo el status HTTP (BUG-513). | [`ApiError`](#apierror) |
| `404` | Ítem no encontrado, archivado, borrado o, para un integrador, de una marca deshabilitada: code 702 | [`ApiError`](#apierror) |


### `GET /api/articulos/items`

**Listar múltiples artículos con filtros**

Retorna una lista paginada de artículos. Soporta filtros por ID, SKU, marca, categoría y texto libre. Incluye variantes, imágenes y datos de marketplace opcionalmente. Incluye artículos deshabilitados (enabled = 0, stock = 0); excluye archivados y borrados y, con el token de un integrador, los de marcas deshabilitadas (API 2.5.0, como la web pública). Orden: nombre ascendente. **Es el endpoint de referencia para sincronizar un catálogo completo hacia otra plataforma:** devuelve todos los artículos, incluidos los que no tienen ningún código cargado (`GET /api/articulos/stock`, en cambio, los omite porque indexa por código). **Devuelve los tres códigos del producto** — `barcode` (EAN), `sku` (SKU interno) y `skuf` (SKU del fabricante) — a nivel ítem y dentro de cada elemento de `variants[]`, en **los dos modos de `compact`**. El `skuf` viaja desde la API 2.3.0; antes faltaba con `compact=true` y salía vacío con `compact=false`. **`compact` cambia el juego de claves:** - `compact=true` (default): un elemento por artículo, con las claves del schema `ArticuloItem`. Las variantes van anidadas en `variants[]`. - `compact=false`: los artículos con **precio por rango** se despliegan como **un elemento por variante** (cada uno con los códigos de su opción), y los demás artículos conservan sus variantes anidadas. Pensado para plataformas que no soportan variantes y necesitan un ítem plano por combinación. ⚠ En este modo `weight` es un número (gramos), `main_pic` sale siempre `null`, `free_shipping` siempre 0 y `range_price` 0; no vienen `combo` ni `pack`; las variantes anidadas no traen `price` / `deal_price` / `off` y su `stock` no se anula; con `mercadolibre=true` se agrega una fila por réplica de ML con el mismo `id` (BUG-1123). ⚠ Con `sku`/`codes`: `id` se ignora, `filter` busca sólo por nombre, y la página puede traer hasta 2×`limit` filas (artículos y opciones se paginan por separado) (BUG-1124). El filtro de marca funciona igual en los dos modos desde la API 2.5.0: acepta varios valores y una marca inexistente responde 404 / `code` 704. También acepta **POST** con JSON en el body (no exige Content-Type), para cuando la lista de códigos no entra en la query string. El body admite sólo `id`, `sku`, `categoryId`, `brandId`, `brandCode`, `filter` y `client_type`, **como string separado por comas** (`{"sku": "A1,A2,A3"}`): un array JSON produce error 500. `codes` y los parámetros de forma (`compact`, `variants`, `description`, `mercadolibre`, `page`, `limit`) se leen siempre de la query string.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | query | no | string | IDs de artículos (separados por coma) |
| `sku` | query | no | string | SKUs a buscar (separados por coma). Alias: `codes` |
| `categoryId` | query | no | integer | ID de la categoría PRINCIPAL del artículo. No incluye subcategorías ni categorías secundarias. |
| `brandId` | query | no | string | ID de marca para filtrar (separados por coma). Si ninguna existe: 404 / code 704. Para un integrador, una marca deshabilitada cuenta como inexistente. |
| `brandCode` | query | no | string | Código de marca para filtrar (separados por coma). Tiene prioridad sobre `brandId`. Si ninguno existe: 404 / code 704. |
| `filter` | query | no | string | Texto libre para buscar por nombre, código o SKU |
| `client_type` | query | no | integer, default `0` | ID de tipo de cliente (para precios segmentados) |
| `compact` | query | no | string (`true` · `false`), default `true` | Modo compacto. Default: true. Sólo el literal true activa; cualquier otro valor (incluido 1) equivale a false. |
| `description` | query | no | string (`true` · `false`), default `false` | Sólo con compact=false: agrega body con la descripción en texto plano (no HTML). Con compact=true no tiene efecto. Sólo el literal true activa. Default: false. |
| `variants` | query | no | string (`true` · `false`), default `true` | Incluir variantes (opciones/curvas). Default: true. Sólo el literal true activa; cualquier otro valor (incluido 1) equivale a false. |
| `mercadolibre` | query | no | string (`true` · `false`), default `false` | Con true calcula marketplace_*. Con compact=false agrega además una fila por réplica de ML (mismo id), que cuenta en pagination.results. Con compact=true las réplicas no se emiten. |
| `page` | query | no | integer, default `1` | Número de página. Una página vacía o posterior al final responde 404 / code 702. |
| `limit` | query | no | integer, default `500` | Registros por página. Default 500 (API_RECOMMENDED_LIMIT_RECORDS), tope 1000 (API_HARD_LIMIT_RECORDS): un valor mayor se recorta a 1000. ⚠ Un valor negativo no se recorta y quita el tope (BUG-1115). |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista paginada de artículos | [`ArticulosListResponse`](#articuloslistresponse) |
| `404` | Sin resultados, incluida una página posterior al final: code 702, msg "IDs de ítems no encontrado.". Es la condición de corte confiable al paginar. Marca del filtro inexistente (o deshabilitada, para un integrador): code 704, msg "Codigos de marca inexistentes" / "IDs de marca inexistentes" (desde la API 2.5.0; antes era 702). El cuerpo trae además receive: los códigos recibidos. Para distinguir los casos, mirar code, no msg. | [`ApiError`](#apierror) |
| `401` | Token faltante o inválido. ⚠ Hoy el status HTTP real es 200 con code 903 (token) o 905 (API deshabilitada): validar status === 0 y HTTP 2xx, no sólo el status HTTP (BUG-513). | [`ApiError`](#apierror) |


### `GET /api/articulos/categories`

**Listar categorías de artículos**

Retorna las categorías habilitadas como lista plana ordenada por posición en el árbol (lft). El árbol se reconstruye con parent_id y level. Con id devuelve sólo los hijos directos de esa categoría; con only_leaf=1, sólo las hojas. Sin paginación. Un id inexistente responde 200 con `categories` vacío.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | query | no | integer | ID de categoría padre — retorna solo hijos directos |
| `only_leaf` | query | no | integer (`0` · `1`), default `0` | Con cualquier valor no vacío distinto de 0, retorna solo categorías hoja (sin subcategorías) |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista plana de categorías (no un árbol anidado) | [`CategoriesListResponse`](#categorieslistresponse) |


### `GET /api/articulos/combo_details`

**Obtener detalle de un combo**

Retorna la composición de un combo o pack en `result`: - `details[]`: un componente por fila, con claves en castellano — `id`, `opcion_id`, `nombre`, `title`, `slug`, `habilitado`, `sku`, `codigo` (EAN), `mcode` (SKU-F), `precio`, `units`, `stock`, `stock_dist`, `stock_id`, `stock_id_dist`, `oferta`, `campaign_off`, `brand_off`, `marca_id`, `price_ratio`, `subtotal`, `combo_rel_ids` (interno) y, si el componente es una opción, `variation_attributes[]`. - `resume`: `items` (unidades), `price`, `ratio_price_check`, `deal`, `deal_price`. - `combo.Articulo`: datos del combo (`id`, `nombre`, `precio`, `precio_real`, `stock`, `combo_data`…). - `code`: 0 = combo leído, 2 = el id no existe. ⚠ Un id inexistente responde **200** con `result.code = 2` y `details` vacío, no 404. Un artículo que no es combo ni pack también responde 200, con `details` vacío (BUG-1126). **Visibilidad (API 2.5.0):** con el token de `POST /usuarios/token`, un combo archivado, borrado, inexistente o de una marca deshabilitada responde **404** / `code` 702.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | query | **sí** | integer | ID del artículo combo o pack |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Detalle del combo en result. Verificar result.code: 0 = encontrado, 2 = id inexistente. | [`GenericSuccessResponse`](#genericsuccessresponse) |
| `400` | Parámetro id vacío (code 702, no 701). | [`ApiError`](#apierror) |
| `404` | Sólo con el token de un integrador: combo archivado, borrado, inexistente o de una marca deshabilitada (code 702, msg "Combo no encontrado."). | [`ApiError`](#apierror) |


## Artículos — Stock

Consulta y descuento de stock

### `GET /api/articulos/stock`

**Consultar stock por código (SKU / EAN)**

Retorna el stock actual **indexado por código**. Pensado para sincronización de stock: se consulta por SKU y se obtiene el renglón directo, sin recorrer una lista. **Forma de la respuesta — leer antes de integrar:** - `items` es un **objeto (mapa)**, no un array. La clave es el **SKU** del artículo o de la opción; si no tiene SKU, se usa su **código de barras (EAN)**. - **Cada variante es una fila propia.** Las opciones no vienen anidadas en un sub-array `variants`: se aplanan, porque cada variante tiene su propio código y su propio stock. Un artículo con 3 variantes devuelve 3 entradas en el mapa. - En las filas de variante, `id` es el string `"{articuloID}-{opcionID}"` (ej: `"60-2195"`). En artículos sin variantes es el ID numérico del artículo. - **Los artículos y opciones sin SKU y sin EAN no aparecen**, porque no hay clave con la que indexarlos. Para el catálogo completo, incluidos los que no tienen código, usar `/api/articulos/items`. - Si dos artículos u opciones comparten código, el mapa conserva sólo uno (el último): un código repetido en el catálogo se ve como un único renglón. - Si el código pedido es el de un artículo padre con variantes, la respuesta trae una fila por variante indexada por el código de cada una: la clave pedida no aparece en items. - `limit` cuenta **artículos**, no filas: cada artículo da 0..N filas. `pagination.results` puede ser menor **o mayor** que `limit` en cualquier página, así que `results < limit` **no** indica la última página. La única condición de corte confiable es el **404 / code 702**. ⚠ Con `sku`/`codes`: `id` se ignora, `filter` busca sólo por nombre, y artículos y opciones se paginan por separado (BUG-1124). El filtro de marca funciona igual en los dos modos desde la API 2.5.0. **Visibilidad (API 2.5.0):** la misma que `/api/articulos/items`. Con el token de un integrador no aparecen los artículos de marcas deshabilitadas. Soporta filtros por ID, SKU, marca, categoría, estado y tipo de producto. Respuesta paginada.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | query | no | string | IDs de artículos (separados por coma) |
| `sku` | query | no | string | SKUs a buscar (separados por coma). Alias: `codes` |
| `categoryId` | query | no | integer | ID de la categoría PRINCIPAL del artículo. No incluye subcategorías ni categorías secundarias. |
| `brandId` | query | no | string | ID de marca (separados por coma). Si ninguna existe: 404 / code 704. Para un integrador, una marca deshabilitada cuenta como inexistente. |
| `brandCode` | query | no | string | Código de marca (separados por coma). Tiene prioridad sobre `brandId`. Si ninguno existe: 404 / code 704. |
| `status` | query | no | string (`enabled` · `disabled`) | Filtra por habilitación del artículo y de sus opciones a la vez (enabled / disabled). Otro valor se ignora. |
| `filter` | query | no | string | Texto libre para buscar por nombre, código o SKU |
| `product_type` | query | no | string (`all` · `single` · `combo` · `pack` · `combo_pack`), default `all` | Tipo de producto. Otro valor equivale a `all`. |
| `page` | query | no | integer, default `1` | Página. Una página vacía responde 404 / code 702. |
| `limit` | query | no | integer, default `500` | ARTÍCULOS por página (no filas: cada artículo puede dar 0..N filas). Default 500, tope 1000. ⚠ Un valor negativo no se recorta y quita el tope (BUG-1115). |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Stock de artículos con variantes | [`StockListResponse`](#stocklistresponse) |
| `404` | Sin resultados, incluida una página posterior al final: code 702, msg "IDs de ítems no encontrado.". Es la condición de corte confiable al paginar. Marca del filtro inexistente (o deshabilitada, para un integrador): code 704, msg "Codigos de marca inexistentes" / "IDs de marca inexistentes" (desde la API 2.5.0; antes era 702). El cuerpo trae además receive: los códigos recibidos. Para distinguir los casos, mirar code, no msg. | [`ApiError`](#apierror) |
| `401` | Token faltante o inválido. ⚠ Hoy el status HTTP real es 200 con code 903 (token) o 905 (API deshabilitada): validar status === 0 y HTTP 2xx, no sólo el status HTTP (BUG-513). | [`ApiError`](#apierror) |


### `POST /api/articulos/itemDiscountStock`

**Descontar stock de un artículo**

Resta `item.quantity` del stock disponible del artículo o de la variante **y la suma al stock comprometido** (salvo en modo mayorista independiente, que no compromete). En combos y packs descuenta los componentes y regenera los combos relacionados. Registra el movimiento en sync_log con `context.order_id` y `context.source` (default `API`). Requiere `Content-Type: application/json` (el body se lee del request ya parseado). Obligatorios: `item.item_id` e `item.quantity`. Opcionales: `item.variant_id` (**obligatorio si el artículo tiene variantes**: sin él se descuenta el stock del artículo padre y las opciones quedan como estaban, BUG-1127), `context.is_wholesaler`, `context.order_id` y `context.source`. ⚠ La respuesta de éxito no informa nada (`msg` vacío) y **también sale 900 si el artículo no existe, está deshabilitado o el `variant_id` no corresponde** (BUG-412, BUG-1127). La validación fallida sale con HTTP 200 / code 502 (BUG-552). No es idempotente: reintentar descuenta dos veces.

**Body** *(obligatorio)*

Esquema: [`ItemDiscountStockRequest`](#itemdiscountstockrequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Éxito (code 900, msg vacío) o validación fallida (code 502 "Required field error [campo]"). ⚠ La validación fallida sale con HTTP 200 (BUG-552), y un artículo inexistente o deshabilitado también responde 900 sin haber descontado nada (BUG-412). Validar code. | [`GenericSuccessResponse`](#genericsuccessresponse) |


## Artículos — Sync

Sincronización con sistemas contables

### `GET /api/articulos/new_codes/{lastDays}`

**SKUs creados en los últimos N días**

Retorna los códigos (SKU, o el código de barras si no hay SKU) de artículos y variantes creados en los últimos `lastDays` días: un código por artículo sin curva y uno por opción en artículos con curva. Incluye artículos deshabilitados; excluye archivados y borrados. Sin paginar. Sin resultados, `codes` es `[]`. **Filtro de marca (API 2.5.0):** `brandId` o `brandCode`, con la misma forma que en `/api/articulos/items`. Se combina con la ventana de días (AND). Sin el parámetro, la respuesta no cambia. Con el token de un integrador no aparecen las marcas deshabilitadas. ⚠ Defectos abiertos: la mayoría de las entradas trae `created` y `modified` en null, y puede aparecer una entrada con clave vacía (BUG-1074); no incluye `skuf` (BUG-441).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `lastDays` | path | **sí** | integer, default `1` | Días hacia atrás (entero). Si se omite el segmento (/api/articulos/new_codes) vale 1. Un negativo se toma en valor absoluto; 0 o un valor no numérico dan una ventana vacía. |
| `brandId` | query | no | string | ID de marca (separados por coma). Desde la API 2.5.0. Si ninguna existe: 404 / code 704. Para un integrador, una marca deshabilitada cuenta como inexistente. |
| `brandCode` | query | no | string | Código de marca (separados por coma). Desde la API 2.5.0. Tiene prioridad sobre `brandId`. Si ninguno existe: 404 / code 704. |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista de códigos nuevos. Sin resultados, `codes` es `[]` (no es 404). | [`CodesResponse`](#codesresponse) |
| `404` | La marca del filtro no existe (o, para un integrador, está deshabilitada): code 704, con `codes: []`. | [`ApiError`](#apierror) |


### `GET /api/articulos/change_codes/{lastDays}`

**SKUs modificados en los últimos N días**

Retorna los códigos (SKU, o el código de barras si no hay SKU) de artículos y variantes modificados en los últimos `lastDays` días. «Modificado» es cualquier guardado (precio, stock, textos, sincronización): el código POSIBLEMENTE cambió de precio o stock. Es un delta: dice qué cambió, no el detalle (para eso, `/api/articulos/items`). Incluye deshabilitados; excluye archivados y borrados. Sin paginar. Sin resultados, `codes` es `[]`. **Filtro de marca (API 2.5.0):** `brandId` o `brandCode`, con la misma forma que en `/api/articulos/items`. Se combina con la ventana de días (AND). Sin el parámetro, la respuesta no cambia. Con el token de un integrador no aparecen las marcas deshabilitadas. ⚠ Un artículo con variantes cuyo cambio quedó sólo en el artículo —típicamente el precio, cuando las opciones lo heredan— **no aparece** (BUG-1072): no reemplaza una reconciliación periódica completa. No informa bajas: un artículo que deja de ser visible deja de aparecer. No incluye `skuf` (BUG-441).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `lastDays` | path | **sí** | integer, default `1` | Días hacia atrás (entero). Si se omite el segmento (/api/articulos/change_codes) vale 1. Un negativo se toma en valor absoluto; 0 o un valor no numérico dan una ventana vacía. |
| `brandId` | query | no | string | ID de marca (separados por coma). Desde la API 2.5.0. Si ninguna existe: 404 / code 704. Para un integrador, una marca deshabilitada cuenta como inexistente. |
| `brandCode` | query | no | string | Código de marca (separados por coma). Desde la API 2.5.0. Tiene prioridad sobre `brandId`. Si ninguno existe: 404 / code 704. |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista de códigos modificados. Sin resultados, `codes` es `[]` (no es 404). | [`CodesResponse`](#codesresponse) |
| `404` | La marca del filtro no existe (o, para un integrador, está deshabilitada): code 704, con `codes: []`. | [`ApiError`](#apierror) |


## Artículos — Alta

Alta de productos por API, con moderación en el panel

### `POST /api/articulos/create`

**Crear un producto ( queda a moderar )**

Alta de UN producto por API. **Viene APAGADO en cada tienda**: se prende desde el panel, en Configuración de la API, junto con el cupo de altas por día ( 200 por defecto ). Apagado responde 403. **El producto nace DESHABILITADO, con stock 0 y sin sincronización con el ERP**, y queda **a moderar**. El `stock` que mandás se guarda aparte y se aplica cuando una persona lo **aprueba** desde el listado de productos del panel; recién ahí se habilita y el ERP lo empieza a sincronizar. Hasta la aprobación, la sincronización de precio y stock del ERP no lo toca: su código figura como no sincronizado. **Nombres de campo**: los mismos que devuelve `GET /api/articulos/item`. Obligatorios: `name` ( 10 a 120 caracteres ) y **al menos un código** ( `barcode`, `sku` o `skuf` ). Lo que no se acepta ( `enabled`, `id`, `slug`, `stock_id`, `published` y los campos desconocidos ) se ignora con un aviso en `warnings`. **Duplicados**: cada código se busca en EAN, SKU y SKU-F **cruzados**, de productos y de variantes ( los archivados cuentan, los eliminados no ). Si alguno existe, 409 con `conflicts`. **Idempotencia**: mandá `ext_ref` ( tu ID del producto ). El mismo `ext_ref` devuelve **409 con `status` 0 y el `articleID` que ya se creó**: tratalo como éxito. **Imágenes**: URLs http(s) públicas en `pictures`. Se bajan y se normalizan ( cuadrado de 1200 px y miniatura de 600 ) ANTES de crear el producto: **si una falla, no se crea nada** ( 400 con el motivo ). URLs a direcciones internas, puertos que no sean 80/443 o con usuario se rechazan. **`?dry_run=1`** valida todo ( campos, marca, categorías, duplicados, idempotencia ) y responde 200 con el producto normalizado, sin crear nada ni bajar imágenes. La descripción ( `body` ) se sanea: sólo quedan p, br, strong, b, em, i, u, ul, ol, li, h2-h4, tablas, blockquote y links http(s).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `dry_run` | query | no | integer (`0` · `1`), default `0` | 1 = validar sin crear |

**Body** *(obligatorio)*

Esquema: [`ItemCreateRequest`](#itemcreaterequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `201` | Creado, a moderar. Devuelve `articleID`, `slug`, `moderation: "pending"`, `pictures` y `warnings` | [`ItemCreateResponse`](#itemcreateresponse) |
| `200` | Sólo con `dry_run=1`: válido, no se creó nada. Devuelve `dry_run: true`, `item` ( lo que se guardaría ) y `warnings` | — |
| `400` | Cuerpo vacío ( 904 ), JSON inválido o algún campo mal ( 504, con TODOS los errores en `errors[]` ), marca o categoría inexistente, o una imagen que no se pudo usar. No se creó nada | [`ApiError`](#apierror) |
| `403` | El alta por API está desactivada en esta tienda ( 906 ) | — |
| `405` | Método distinto de POST | — |
| `409` | Con `status` 0 y `duplicate: true`: el `ext_ref` ya creó el producto `articleID` ( tratar como éxito ). Con `code` 703 y `status` 2: algún código ya lo usa otro producto o variante ( ver `conflicts` ) | — |
| `429` | Se alcanzó el cupo de altas por día de la tienda ( 909, con `daily_max` y `used` ) | — |
| `503` | Hay otra alta en curso: reintentar en unos segundos | — |


## Marcas

Endpoints de marcas

### `GET /api/marcas/list`

**Listar todas las marcas**

Lista las marcas habilitadas de primer nivel (sin líneas ni submarcas), ordenadas por nombre, sin paginar. **Sólo las habilitadas, a propósito**: por eso `enabled` vale siempre 1, y una tienda con marcas deshabilitadas lista menos de las que tiene. Para un integrador, los artículos de una marca deshabilitada tampoco aparecen en `/api/articulos/*`, y filtrar por ella responde 404 / code 704 (API 2.5.0). ⚠ permalink hoy se arma con el prefijo de URL de categorías, no con el de marcas: no usarlo como link (BUG-1129). ⚠ Si la tienda tiene apagado el módulo Marcas, responde HTTP 403 con `code 910`.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado de marcas | [`MarcasListResponse`](#marcaslistresponse) |
| `401` | Token faltante o inválido. ⚠ Hoy el status HTTP real es 200 con code 903 (token) o 905 (API deshabilitada): validar status === 0 y HTTP 2xx, no sólo el status HTTP (BUG-513). | [`ApiError`](#apierror) |


## Pedidos

Endpoints de órdenes y pedidos

### `GET /api/pedidos/orders`

**Listar pedidos**

Retorna una lista paginada de pedidos con múltiples filtros opcionales: por estado, estado de pago, datos del comprador (DNI, email, teléfono), número de seguimiento, rangos de fecha (creación/finalización/modificación), rango de IDs, marketplace, canal de adquisición y vendedor. Permite respuesta aligerada (`minify`) e incluir el detalle de pagos por pedido (`payment_details`). Las fechas aceptan formato `YYYY-MM-DD` o ISO 8601 `YYYY-MM-DDTHH:MM:SS`. La respuesta trae, además de `order`/`client`/`items`/`payment`/`shipping`/`address`/`billing`, los bloques `attribution` (canal, origen, referido y UTMs del pedido), `discounts` (cupón, descuento total, crédito) y `loyalty` (puntos canjeados). **Paginación:** `pagination.has_more` indica si existe una página siguiente y no cuesta nada (se pide un registro extra que no se serializa). `pagination.total_results` y `total_pages` requieren `include_total=1` porque agregan un `COUNT()` sobre las mismas condiciones. `pagination.results` cuenta los pedidos **de esta página**, no el total. **Qué pedidos incluye.** También los **eliminados** (`order.status` = `deleted`): filtrarlos del lado del consumidor o pedir `status`. Si la tienda tiene configurado *ID de pedido desde* (`order_store_id_from`), sólo se devuelven pedidos con `id` mayor a ese valor, sin aviso en la respuesta. Un pedido sin ítems ni conceptos extra no trae la clave `items` (no viaja como array vacío). ⚠ **Importes de la lista ≠ detalle (defecto abierto, BUG-1134).** En esta respuesta `order.amount`, `amount_to_pay` y `amount_tax_free` salen **sin descuentos** y `order.discount_total`/`discounts.total_discount` en `0.00`; además `billing.id` es siempre el DNI, el retiro en local no trae sucursal (`pickup_store.code = null`, `branch.code = "STORE"`) y no se aplica el coeficiente financiero. Para importes, usar `GET /api/pedidos/order/{id}` o `/api/pedidos/stats`. ⚠ Si falló la consulta interna a `/api/marketplaces/list`, `order.marketplace.code` y `name` pueden venir `null` hasta que se refresque la caché (BUG-475).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `statusId` | query | no | string | Filtra por estado(s) interno(s) del pedido. Acepta varios IDs separados por coma (genera `estado IN (...)`). Los IDs corresponden al mapa de `/api/pedidos/statusList`. |
| `status` | query | no | string | Filtra por estado(s) usando los códigos API que devuelve la propia respuesta en `order.status` (`pending`, `approved`, `finalized`, `cancelled`, …). Acepta varios separados por coma. Catálogo de los estados habilitados en `/api/pedidos/order_status_list`; el filtro acepta también los códigos de estados no habilitados (`custom1`…`custom25`, `deleted`). Un código desconocido devuelve **400**, no se ignora. Si se envían `statusId` y `status` juntos, gana `statusId`. |
| `order` | query | no | string (`ASC` · `DESC`), default `ASC` | Dirección de orden por fecha de creación del pedido. `DESC` sin distinguir mayúsculas; cualquier otro valor ordena ascendente. |
| `orderId` | query | no | integer | Trae un pedido puntual por su ID exacto. |
| `paid` | query | no | string (`true` · `false`) | Filtra por estado de pago. Sólo el literal `true` filtra pagados (`pago = 1`). **Cualquier otro valor no vacío —incluidos `1` y `false`— filtra NO pagados** (`pago = 0`); `0` u omitido no filtra. Los pedidos en consignación, bonificados, en cuenta corriente o con pago indefinido (pagos avanzados) no entran en ninguno de los dos filtros (BUG-1136). |
| `page` | query | no | integer, default `1` | Número de página (desde 1). No se valida: un valor menor que 1 devuelve la lista vacía con HTTP 200. |
| `identification` | query | no | integer | Busca por **DNI** del comprador (`Pedido.dni`, match exacto). Se convierte a entero: **no busca por CUIT**, y un valor con guiones se trunca en el primer guion. |
| `email` | query | no | string | Busca por email del comprador (match exacto). |
| `phone` | query | no | string | Busca por teléfono del comprador (match exacto). |
| `tn` | query | no | string | Busca por número de seguimiento / guía de envío (`Pedido.guia`), match exacto. |
| `limit` | query | no | integer, default `50` | Registros por página. Si supera el máximo permitido se topea automáticamente. |
| `from_date` | query | no | string | Filtra pedidos creados desde esta fecha (`created >=`). Formato `YYYY-MM-DD` o ISO `YYYY-MM-DDTHH:MM:SS`. |
| `to_date` | query | no | string | Filtra pedidos creados hasta esta fecha (`created <=`), **inclusive**: sin hora se completa a `23:59:59`, de modo que `to_date=2026-01-31` incluye el 31 entero. Si se manda la hora se respeta tal cual, incluido `T00:00:00`. Formato `YYYY-MM-DD` o ISO `YYYY-MM-DDTHH:MM:SS`. |
| `finalized_from_date` | query | no | string | Filtra pedidos finalizados desde esta fecha (`finalized >=`). Formato `YYYY-MM-DD` o ISO `YYYY-MM-DDTHH:MM:SS`. |
| `finalized_to_date` | query | no | string | Filtra pedidos finalizados hasta esta fecha (`finalized <=`), **inclusive**: sin hora se completa a `23:59:59`. Formato `YYYY-MM-DD` o ISO `YYYY-MM-DDTHH:MM:SS`. |
| `modified_from_date` | query | no | string | Filtra pedidos modificados desde esta fecha (`modified >=`). Es el filtro de **descarga incremental**: `modified` se actualiza en cada cambio de la orden, incluido el cambio de estado. Combinado con `order.updated_at` de la respuesta permite bajar sólo lo que cambió desde el último sync. |
| `modified_to_date` | query | no | string | Filtra pedidos modificados hasta esta fecha (`modified <=`), **inclusive**: sin hora se completa a `23:59:59`. |
| `from` | query | no | string | Alias corto del extremo inferior del rango de fechas. Sin hora se resuelve a `00:00:00`. Aplica sobre el campo que indique `date_field`. Mismo vocabulario que `/api/pedidos/stats`. Si se envía junto al par explícito (`from_date`, etc.), gana el explícito. |
| `to` | query | no | string | Alias corto del extremo superior del rango de fechas, **inclusive**: sin hora se completa a `23:59:59`. Ver `from`. |
| `date_field` | query | no | string (`created` · `finalized` · `modified`), default `created` | Sobre qué fecha aplican `from`/`to`: `created` (alta del pedido), `finalized` (finalización) o `modified` (última modificación). Cualquier otro valor se trata como `created`, sin error. |
| `from_id` | query | no | integer | Filtra pedidos con ID mayor o igual (`id >=`). Útil para sincronización incremental. |
| `to_id` | query | no | integer | Filtra pedidos con ID menor o igual (`id <=`). |
| `marketplace` | query | no | string | Filtra por `marketplace_id`. Acepta varios separados por coma. El catálogo `id → code/name` está en `/api/marketplaces/list` y también viaja en `order.marketplace` de cada pedido. Los IDs 1-4 (tienda online, MercadoLibre, mayorista, tienda física) son fijos; los de los marketplaces propios (desde 1001) **no son estables entre tiendas**: resolverlos por `code`. |
| `channel` | query | no | string | Filtra por canal de adquisición. Acepta IDs internos y códigos API (`organic`, `paid`, `referral`, `direct`, `email`, `social`, `social_paid`, `mercadolibre`, `store`, `marketplace`, `nubixstore`, `unknown`), varios separados por coma. ⚠ A diferencia de `status`, un código desconocido **no da 400**: se traduce al canal `unknown` y filtra por él (BUG-1137). |
| `minify` | query | no | integer (`0` · `1`), default `0` | Respuesta aligerada para sync masivo: no carga los ítems y cada orden trae **sólo** `order`, `attribution`, `discounts` y `loyalty`. Se omiten `links`, `client`, `clientType`, `seller`, `shipping`, `address`, `payment`, `billing` e `items`. Se activa con cualquier valor no vacío distinto de `0` (`minify=false` también, BUG-1136). |
| `seller` | query | no | string | Filtra pedidos de vendedor (`is_seller_buy=1`). Si es numérico se usa como `seller_id`; si no, se busca el vendedor por email o login. |
| `payment_details` | query | no | integer (`0` · `1`), default `0` | Completa el bloque `payment` con el registro de pago (cuotas, monto financiado, referencia, autorización, app). Se activa con **cualquier valor no vacío distinto de `0`**: `payment_details=false` también lo activa (BUG-1136). Para no pedirlo, omitirlo o mandar `0`. ⚠ Si el pedido tiene más de un pago viaja uno solo, el último (BUG-466). |
| `include_total` | query | no | string (`true` · `false`), default `false` | Agrega `total_results` y `total_pages` al bloque `pagination`. Cuesta un `COUNT()` extra sobre las mismas condiciones (medido: +0,6 s sobre una ventana de 25.000 pedidos), por eso es opt-in. Para paginar alcanza con `has_more`, que ya viene siempre. |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista paginada de pedidos | [`OrdersListResponse`](#orderslistresponse) |
| `400` | Un código de `status` desconocido (code 901). | [`ApiError`](#apierror) |


### `POST /api/pedidos/order`

**Crear un pedido**

Da de alta una orden completa en la tienda: cliente, envío, pagos, facturación e ítems. Es el endpoint por el que un sistema externo —otra tienda **nubixstore**, un marketplace o un ERP— empuja sus ventas. **`Content-Type: application/json` es obligatorio.** Sin ese header la validación no ve el body y responde **409** `code` 502 *"Required field error [items]"* aunque el JSON sea correcto. **Descuenta stock en el alta** de cada ítem vinculado al catálogo, salvo que la orden nazca cancelada o eliminada o sea de fulfillment. Ignora la opción *Descontar stock* del marketplace (BUG-461). El descuento no es atómico (BUG-401). **Idempotencia.** Si se envía el bloque `marketplace`, la combinación `marketplace.code` + `marketplace.order_id` se usa para deduplicar: reenviar la misma orden devuelve **409** con el `orderID` de la orden ya creada, en vez de duplicarla. Un cliente bien implementado trata ese 409-con-`orderID` **como éxito**, que es lo que hace la replicación entre tiendas. ⚠ La deduplicación sólo funciona si `marketplace.code` está dado de alta y habilitado en **esta** tienda y ya figura en `/api/marketplaces/list`. Un código desconocido **no se rechaza**: la orden se crea sin marketplace y sin deduplicar, y un reenvío la duplica (y descuenta stock de nuevo) (BUG-1140, relacionado con BUG-475). Un pedido previo eliminado no cuenta como duplicado, y si la tienda tiene configurado *ID de pedido desde* (`order_store_id_from`) sólo se comparan los pedidos posteriores a ese ID. **Retiro en local:** la sucursal viaja en `shipping.pickup_store` con su **código**, no con su id — ver la descripción de ese objeto. No confundir con `shipping.branch`, que es la sucursal del correo. **No se envían totales:** se recomponen desde `items[]` + `shipping.cost` − `order.credit`.

**Body** *(obligatorio)*

Esquema: [`OrderCreateRequest`](#ordercreaterequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `201` | Orden creada | [`OrderCreateResponse`](#ordercreateresponse) |
| `409` | No se creó la orden. Se distingue por `orderID`: - **`orderID` numérico**: la orden ya existía (deduplicación por `marketplace.code` + `marketplace.order_id`), `code` 901. **Tratarlo como éxito.** - **sin `orderID`**: faltó un campo obligatorio, `code` 502, `msg` *"Required field error [campo]"* (también sin `Content-Type: application/json`). Es el mismo status que el duplicado (BUG-1153): no tratar todo 409 como éxito. - **`orderID` = `null`**: error al guardar, `code` 901. | [`OrderCreateResponse`](#ordercreateresponse) |


### `GET /api/pedidos/order/{id}`

**Obtener pedido por ID**

Devuelve el pedido completo en `order` (los bloques van **anidados**: la cabecera en `order.order`, y `client`, `shipping`, `payment`, `items`… al mismo nivel dentro de `order`). Siempre carga los pagos, así que el bloque `payment` sale completo. Responde también pedidos eliminados (`status` = `deleted`). ⚠ Si el pedido tiene más de un pago, `payment` refleja uno solo, el último (BUG-466). ⚠ Si falló la consulta interna a `/api/marketplaces/list`, `order.marketplace.code` y `name` pueden venir `null` (BUG-475).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | path | **sí** | integer |  |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Detalle del pedido | [`OrderDetailResponse`](#orderdetailresponse) |
| `400` | Falta el ID en la ruta (`code` 701). | [`ApiError`](#apierror) |
| `404` | Pedido no encontrado (`code` 702) | [`ApiError`](#apierror) |


### `PUT /api/pedidos/order/{id}`

**Actualizar estado del pedido**

Informa cambios de un pedido ya creado: estado, pago, forma de pago, número de seguimiento y notas. Todos los campos son opcionales; se aplica sólo lo que viene. **Es el mismo endpoint en las dos direcciones del vínculo entre tiendas:** - la tienda **origen** se lo llama a la destino cuando cambia un pedido que ya replicó ( tarea `REPLICATE-ORDERS` ); - la tienda **destino** se lo llama al origen para informarle lo que pasó con el pedido que recibió ( tarea programada `UPDATE-ORDERS` del módulo Marketplaces ). Un origen que no es **nubixstore** y quiere recibir esos cambios tiene que exponer este mismo endpoint en su `{url}/api`. **Qué pedido se modifica.** Si el body trae `marketplace.code` + `marketplace.order_id`, se busca por esa pareja y el `{id}` de la ruta **se ignora**. Si no, `{id}` es el ID del pedido en esta tienda. Cuando lo llama `UPDATE-ORDERS`, `{id}` es el `marketplace.order_id` que el origen mandó al crear el pedido. **`status` acepta ID interno o código API.** Numérico → ID interno; texto → código de `/api/pedidos/order_status_list` ( `approved`, `dispatched`… ). `deleted` no se acepta por PUT: se usa `DELETE`. Mandar el estado en que el pedido ya está **no es error**: responde éxito sin cambiar nada. **Respuesta.** El resultado de negocio va en `status` del body, no en el código HTTP: un pedido inexistente, **finalizado** o eliminado responde **HTTP 200 con `status` = 2** ( ERROR ) y `msg` *"Orden no encontrada o ya finalizada."*. **Única excepción:** un pedido finalizado, de cualquier canal, sí se puede pasar a `cancelled` ( BUG-1428 ); cualquier otro cambio sobre él sigue respondiendo ese error. Un eliminado no se cancela. Para dar un cambio por aplicado, exigir HTTP 200, `status` = 0 **y** que `msg` no diga *"no permite"* (BUG-1141).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | path | **sí** | integer | ID del pedido en esta tienda. **Siempre obligatorio en la ruta** (sin él, 400); su valor se ignora si el body trae `marketplace.code` + `marketplace.order_id`. |

**Body** *(obligatorio)*

Esquema: [`OrderUpdateRequest`](#orderupdaterequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Pedido procesado. **Leer `status` y `msg`.** `status` = 2 en dos casos: pedido inexistente, finalizado o eliminado (`code` 900), o `status` = `deleted` (`code` 504). ⚠ `status` = 0 **no garantiza que el estado haya cambiado**: también vuelve 0 cuando la transición no está permitida desde el estado actual (`msg` *"… no permite pasar a …"* o *"… no permite retroceder a …"*), cuando el código de estado no existe, cuando el pedido ya estaba en ese estado y cuando el body vino vacío o no es JSON (BUG-1141, BUG-1116). | [`GenericSuccessResponse`](#genericsuccessresponse) |
| `400` | Falta el ID del pedido en la ruta (`code` 701). | [`ApiError`](#apierror) |


### `GET /api/pedidos/stats`

**Estadísticas agregadas de ventas**

Devuelve las métricas de ventas de un período ya calculadas por el servidor, en **una sola request**: totales (pedidos, unidades, facturación), serie diaria y desagregados por marketplace, canal de adquisición, forma de pago, provincia, cupón, envío gratis y puntos canjeados. Es la alternativa a bajar el listado completo de `/api/pedidos/orders` para calcular agregados del lado del consumidor: un mes de 24.000 pedidos se resuelve en una request de menos de un segundo en vez de ~48 páginas de 500. **Regla de negocio del conjunto:** excluye pedidos eliminados y cancelados; cuenta sólo pedidos **abonados o mayoristas** (en tiendas B2B, todos); y por defecto excluye los orígenes `SRC_CHANGE`, `SRC_BRANCH`, `SRC_WARRANTY` y `SRC_DRAFT` (ver `include_all_sources`). La facturación se toma de `monto_descuento`, es decir **neta de descuentos e incluyendo envío**. Si la tienda tiene configurado *ID de pedido desde* (`order_store_id_from`), sólo cuenta pedidos posteriores a ese ID. El bloque `dimension_names` acompaña la respuesta con el diccionario `id → nombre` de cada dimensión (marketplaces, canales, formas de pago, provincias) para no tener que resolverlo con otra request (en `payments` el valor es el **código API** de la forma de pago, no un nombre). Misma fuente que el widget del dashboard y el monitor, así que los números de la API y los del backend coinciden por construcción.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `from` | query | no | string | Inicio del período (`YYYY-MM-DD` o ISO). Tiene prioridad sobre `period` y `days`. |
| `to` | query | no | string | Fin del período, **inclusive**: sin hora se completa a `23:59:59`, de modo que `to=2026-07-31` incluye el 31 entero. Si se manda la hora se respeta tal cual. Si se omite y hay `from`, se usa el momento actual. Sin `from`, `to` **se ignora** (manda `period` o `days`), aunque `filters_applied.to` lo devuelva. |
| `period` | query | no | integer | Período predefinido, por **número**: 1 hoy · 2 ayer · 3 últimos 7 días · 4 últimos 14 · 5 mes actual · 6 mes anterior · 7 última hora · 8 últimas 2 h · 19 últimas 3 h · 9 últimas 6 h · 10 últimas 12 h · 14 últimos 30 días · 20 últimos 60 · 15 últimos 90 · 16 últimos 180 · 18 últimos 365 · 101-112 enero a diciembre del año en curso. ⚠ **Cualquier otro valor devuelve toda la historia** (sin límite inferior, BUG-1138). Se usa sólo si no vino `from`. |
| `days` | query | no | integer, default `30` | Ventana desde hace N días (00:00) hasta hoy 23:59:59: **N+1 días calendario** (`days=30` cubre 31 días; `days=1`, ayer y hoy; BUG-1049). Se usa sólo si no vinieron `from` ni `period`. |
| `marketplace` | query | no | integer | Acota a un `marketplace_id` (un solo valor). |
| `profile` | query | no | integer | Acota a un perfil de cliente (`tipo_cliente_id`, un solo valor). |
| `brand` | query | no | integer | Acota a los pedidos que contengan items de esa marca. ⚠ Los totales son los de **todo el pedido**, no los de los ítems de esa marca. Para la facturación por marca, usar `include=breakdown` (`top_brands`). |
| `include_all_sources` | query | no | integer (`0` · `1`), default `0` | Incluye también los orígenes normalmente excluidos (cambios, traslados entre sucursales, reclamos y borradores). Se activa con cualquier valor no vacío distinto de `0` (`false` también lo activa, BUG-1136). |
| `fill_gaps` | query | no | integer (`0` · `1`), default `1` | Rellena con 0 los días sin ventas de la serie diaria. Para desactivarlo mandar `0` (`false` no lo desactiva, BUG-1136). |
| `include` | query | no | string | Secciones extra separadas por coma. Hoy soporta `breakdown`, que agrega el TOP de SKUs y de marcas del período. |
| `top_limit` | query | no | integer, default `10` | Cantidad de items y marcas del TOP cuando se pide `include=breakdown`. Mínimo 1, sin máximo. |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Totales, serie diaria y desagregados del período | [`SalesStatsResponse`](#salesstatsresponse) |


### `GET /api/pedidos/statusList`

**Listar estados de pedido (IDs internos)**

Retorna el mapa de estados habilitados para pedidos, indexado por ID interno numérico → nombre del estado. Útil para sistemas legacy que usan IDs numéricos.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de estados id → nombre | [`PedidosStatusListResponse`](#pedidosstatuslistresponse) |


### `GET /api/pedidos/order_status_list`

**Listar estados de pedido (códigos API v2)**

Retorna el mapa de estados habilitados para pedidos, indexado por código API v2 (string) → nombre. Reemplaza a `statusList` con identificadores semánticos (ej: `pending`, `finalized`, `cancelled`). Mapeo vía `_orderStatusId2APICode()`. Lista los estados **habilitados** en la tienda. Los filtros y el PUT aceptan también los códigos de estados no habilitados (`custom1`…`custom25`, `deleted`).

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de estados código_api → nombre | [`PedidosStatusListResponse`](#pedidosstatuslistresponse) |


### `GET /api/pedidos/statusInfo`

**Información extendida de estados de pedido**

Retorna, por estado habilitado (clave = ID interno): `ns_id`, `api_id` (código API), `title` (nombre que le dio la tienda), `ns_title` (nombre de fábrica), `color` y `default_color` (hex sin #), `stage` (etapa: inicial, depósito, despacho, cerrado o libre), `wiki` (texto de ayuda configurado) y `db_color_field`. No incluye conteos de pedidos.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Información extendida de estados | [`PedidosStatusInfoResponse`](#pedidosstatusinforesponse) |


## Pagos

Endpoints de transacciones y pagos

### `GET /api/pagos/payment/{id}`

**Obtener un pago por su ID**

Devuelve el pago `{id}` serializado en `payment`. `{id}` es el ID del pago (`pagos.id`), no el del pedido: para buscar por pedido usar `GET /api/pagos/payment?order_id=`. Si además llega `?order_id`, gana `{id}`. Atención: un ID inexistente responde HTTP 200 con `status` 2 y `code` 702, no 404 (BUG-1114). Ramificar por `status`. ⚠ `method` y `card.network` valen el literal `"emptytext"` cuando el dato está vacío (BUG-1157).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | path | **sí** | integer | ID del pago (`pagos.id`) |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Pago encontrado (`payment`), o inexistente (`status` 2, `code` 702, sin `payment`) | [`PaymentDetailResponse`](#paymentdetailresponse) |


### `GET /api/pagos/payment`

**Obtener el pago de un pedido**

Busca por `order_id` y devuelve UN pago: ordena por estado, así que un aprobado gana sobre rechazados, cancelados, reembolsados y pendientes. Entre dos aprobados (pedido pagado con dos tarjetas) el elegido es arbitrario: para todos los pagos de un pedido usar `GET /api/pagos/payments?order_ids=`. Sin `order_id` responde HTTP 200 con `code` 701; sin resultado, HTTP 200 con `code` 702 (BUG-1114).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `order_id` | query | **sí** | integer | ID del pedido |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Pago encontrado (`payment`), o error con `status` 2 (`code` 701 sin order_id, 702 sin resultado) | [`PaymentDetailResponse`](#paymentdetailresponse) |


### `POST /api/pagos/payment`

**Registrar un pago (sin acreditar el pedido)**

Escribe una fila en `pagos` tal cual llega, dentro de `payment`. Atención: NO acredita el pedido (no cambia su estado de pago ni dispara acciones del gateway), NO valida campos obligatorios y NO deduplica: reenviar el mismo cuerpo crea otro pago. La respuesta no trae el ID del pago creado y responde éxito aunque la escritura falle (BUG-1156). La forma de pago sale de `id` (ID interno) o, si no viene, de `app` (código API; default a convenir). `method` sólo se usa cuando la forma resolvió a convenir y hoy sólo traduce `mercadopago`; no se guarda. `status` acepta approved, pending, cancelled, rejected y refunded; otro valor o ausente se guarda como pendiente. `date` ausente = ahora. Lo usa el Bridge entre tiendas nubixstore. Otros verbos sobre `/api/pagos/payment/{id}`: `DELETE` responde HTTP 400 / `code` 906 (la API no borra pagos); `PUT` y `PATCH` responden `{"code":900,"status":0,"msg":""}` **sin modificar nada** (BUG-206).

**Body** *(obligatorio)*

Esquema: [`PaymentCreateRequest`](#paymentcreaterequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Pago recibido (`msg` "Datos de pago recibidos."). Sin ID del pago | [`GenericSuccessResponse`](#genericsuccessresponse) |


### `GET /api/pagos/mpSearchByExternalReference/{externalReference}/{mpUser}`

**Buscar pago en MercadoPago por referencia externa**

Consulta a MercadoPago (SDK oficial, credenciales de la tienda) por external_reference y devuelve el PRIMER pago del resultado: no contempla varios pagos para la misma referencia (`payment`: `reference`, `paid`, `external_reference`, `shipping_cost`, `free_shipping`, `payment_type`, `notes`). Atención: sin resultados responde éxito con `payment: []`; un error de MercadoPago (token inválido, cuenta `mpUser` sin configurar) sale HTTP 500 sin JSON (BUG-1155). Con el modo prueba de MercadoPago activo consulta la cuenta de prueba.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `externalReference` | path | **sí** | string | Referencia externa del pago en MercadoPago |
| `mpUser` | path | **sí** | integer (`1` · `2`) | Número de cuenta MercadoPago configurada (1 o 2). Si se omite el segmento de la URL se usa la cuenta 1 |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Pago encontrado, o sin resultados (`payment` es `[]`, con `code` 900 y el mismo msg de éxito) | [`MPSearchPaymentResponse`](#mpsearchpaymentresponse) |


### `GET /api/pagos/payment_conditions`

**Listar condiciones de pago**

Condiciones de pago HABILITADAS (las deshabilitadas no se listan, así que `enabled` siempre vale "1"), hasta 20. `list` es un objeto indexado por id; sin condiciones es un array vacío `[]`. Todos los valores son string. Atención: BUG-721, una coma o una barra vertical escrita en un nombre corre las columnas (por ejemplo `notes`, de uso interno, puede salir como `display_name`). BUG-724: una condición habilitada sin `name` no se publica.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de condiciones de pago | [`PaymentConditionsResponse`](#paymentconditionsresponse) |


### `GET /api/pagos/payments`

**Listar pagos recibidos**

Lista paginada de pagos, más nuevos primero (`created` DESC). Atención: los pagos pendientes NUNCA se devuelven; `status=pending`, o cualquier valor que no sea approved, cancelled, rejected o refunded, devuelve una lista vacía con HTTP 200 (BUG-477). `order_ids` filtra por pedidos (CSV). `pagination.results` es la cantidad de esta página, no el total: pedir la siguiente mientras `results == limit`. ⚠ `method` y `card.network` de cada pago valen `"emptytext"` cuando el dato está vacío (BUG-1157).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `status` | query | no | string (`approved` · `cancelled` · `rejected` · `refunded`) | Código API del estado. pending o un valor desconocido devuelven lista vacía (BUG-477) |
| `order_ids` | query | no | string | IDs de pedidos separados por coma |
| `page` | query | no | integer, default `1` | Número de página. No tiene piso: 0 o negativo da un offset negativo. |
| `limit` | query | no | integer, default `500` | Tope 500 (API_RECOMMENDED_LIMIT_RECORDS); un valor mayor se recorta a 500 |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista paginada de pagos | [`PaymentsListResponse`](#paymentslistresponse) |


## Blog

Endpoints del blog y categorías de contenido

### `GET /api/blog/categories`

**Listar categorías del blog**

Retorna el árbol de categorías del blog con id, parent_id, name, title, permalink y level. Solo incluye categorías habilitadas y no eliminadas. Si el módulo Blog está deshabilitado (lo está de fábrica), responde HTTP 403 con `code 910`.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista de categorías del blog | [`BlogCategoriesResponse`](#blogcategoriesresponse) |


### `GET /api/blog/posts`

**Listar posts del blog con filtros**

Retorna posts habilitados del blog, paginados. Filtros: categoría exacta (sin subcategorías) y texto libre en título, meta_title y meta_keys. Sin orden garantizado (BUG-1175). Atención: `pagination.next` vale siempre `false` (BUG-1174); para paginar, pedir la página siguiente mientras `results` sea igual a `limit`. `img` y `thumb` son rutas relativas a la tienda (`img/posts/...`); `permalink` es URL absoluta. `description` es el meta_description o los primeros 350 bytes del cuerpo sin etiquetas, y puede traer entidades HTML. `category` llega como array vacío `[]` si la categoría del post no está habilitada. `author.name` es el login de backend del autor (BUG-1176). Si el módulo Blog está deshabilitado (lo está de fábrica), responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `category_id` | query | no | integer | ID de categoría del blog para filtrar (sólo esa categoría, sin subcategorías) |
| `search` | query | no | string | Texto libre para buscar por título, meta_title o meta_keys. Un `%` actúa como comodín |
| `page` | query | no | integer, default `1` | Número de página. No se castea: un valor no numérico rompe la request (BUG-1115) |
| `limit` | query | no | integer, default `500` | Registros por página. Default 500, máximo 1000. ⚠ Un valor negativo no se recorta y quita el tope (BUG-1115) |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista paginada de posts del blog | [`BlogPostsResponse`](#blogpostsresponse) |


## Campañas

Campañas comerciales, mensajería y carritos abandonados

### `GET /api/campaings/list`

**Listar campañas comerciales**

Retorna todas las campañas comerciales registradas como mapa `id → nombre`, ordenadas por nombre: también las deshabilitadas, las finalizadas y las eliminadas lógicamente (BUG-1173). No trae estado, fechas ni condiciones. Sin campañas, `list` llega como array vacío `[]`. Si el módulo Campañas está deshabilitado (lo está de fábrica), responde HTTP 403 con `code 910`.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista de campañas comerciales | [`CampaingsListResponse`](#campaingslistresponse) |


## Cupones

Gestión de cupones de descuento y notas de crédito

### `GET /api/cupones/status`

**Estado general del módulo de cupones**

Contadores agregados del módulo de cupones para el tablero del panel. No recibe parámetros. `statusInfo.coupons` cuenta cupones de descuento; `nc`, notas de crédito con descuento menor a 100 %, y `ncc`, notas de crédito al 100 % (estas dos, sólo las de `usos = 1`). En cada grupo: `active` = habilitados y vigentes (sin vencimiento o con vencimiento futuro), `inactive` = deshabilitados y vigentes, `defeated` = habilitados y vencidos. ⚠ `coupons.inactive` no cuenta los deshabilitados sin vencimiento con fecha vencida o nula, a diferencia de `nc` y `ncc` (BUG-1170). `alerts` trae los 3 cupones de mayor facturación sin filtrar (puede incluir eliminados y notas de crédito, BUG-1171), con un texto HTML. `msg` llega vacío. Si el módulo Cupones está deshabilitado (lo está de fábrica), responde HTTP 403 con `code 910`.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Status con conteos y alertas | [`CuponesStatusResponse`](#cuponesstatusresponse) |


### `DELETE /api/cupones/del/{code}`

**Eliminar cupón por código**

Borrado lógico (`borrado = 1`, `habilitado = 0`) del cupón o nota de crédito con ese código. Acepta cualquier método HTTP, así que también un GET borra (BUG-1172): el cliente interno (`APICuponComponent::delete()`) usa GET. Las tres salidas responden HTTP 200 y `code` 900; sólo `status` distingue el error (BUG-1116, BUG-1114): eliminado → `status` 0; código inexistente → `status` 2, `msg` "C&oacute;digo de cup&oacute;n no encontrado."; sin código → `status` 2, `msg` "Falta c&oacute;digo de cup&oacute;n.". Un cupón ya eliminado se puede volver a eliminar y responde éxito. Los `msg` traen entidades HTML. Si el módulo Cupones está deshabilitado (lo está de fábrica), responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `code` | path | **sí** | string | Código del cupón a eliminar |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Cupón eliminado (`status` 0) o error (`status` 2). En los dos casos `code` = 900 | [`GenericSuccessResponse`](#genericsuccessresponse) |


## Depósitos

Gestión de depósitos y stock por depósito

### `GET /api/deposits/deposits`

**Listar depósitos**

Retorna los depósitos como mapa `id → {id, name, code, status}`, ordenados por nombre y sin paginación. Por defecto, sólo los habilitados. Sin resultados, `deposits` llega como array vacío `[]` y no como objeto. Atención: la clave `status` de cada depósito vale siempre `enabled`, aunque se filtre por deshabilitados o eliminados (BUG-297). Si el módulo Depósitos está deshabilitado en la tienda, responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `status` | query | no | integer (`1` · `2` · `3`), default `1` | Estado del depósito: 1 = habilitado, 2 = deshabilitado, 3 = eliminado. Default 1. Cualquier otro valor devuelve la lista vacía |
| `wholesaler` | query | no | integer (`0` · `1`), default `0` | Con `1` devuelve sólo los depósitos configurados como stock mayorista. Para no filtrar, omitirlo o mandar `0`. Atención: se evalúa como booleano de PHP, así que el texto `false` también filtra (BUG-1166) |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de depósitos indexados por ID (array vacío si no hay) | [`DepositsListResponse`](#depositslistresponse) |


### `GET /api/deposits/stock_by_deposits`

**Consultar stock desglosado por depósito**

Stock por depósito de los códigos indicados (SKU o EAN), sólo en depósitos habilitados. `items` es un mapa `código → {deposit_code: stock}`. Atención: la clave de cada ítem es su EAN (`barcode`) si lo tiene y, si no, su SKU; no necesariamente el código que pediste. Paginación: `limit` cuenta FILAS (un código con stock en tres depósitos son tres filas) pero `pagination.results` cuenta CÓDIGOS, así que `results < limit` no indica la última página, y un mismo código puede quedar repartido entre dos páginas (BUG-1167). No hay orden garantizado. Para listas largas usar el POST, que no pagina. Sin códigos responde 200 con `items: []`. Si el módulo Depósitos está deshabilitado, responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `codes` | query | no | string | SKUs o EANs separados por coma. También acepta `;`, `\|` o tabulación: el separador se autodetecta |
| `page` | query | no | integer, default `1` | Número de página. No se castea: un valor no numérico rompe la request (BUG-1115) |
| `limit` | query | no | integer, default `500` | Filas (código × depósito) por página. Default 500, máximo 1000. ⚠ Un valor negativo no se recorta y quita el tope (BUG-1115) |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Stock desglosado por código y depósito | [`StockByDepositsResponse`](#stockbydepositsresponse) |


### `POST /api/deposits/stock_by_deposits`

**Consultar stock desglosado por depósito (lista larga de códigos)**

Igual que el GET, con los códigos como array JSON en el body. No pagina: devuelve todas las filas, y `pagination.limit` llega con el valor interno `-3333` (sin límite). Si el body no trae `codes`, se usa el parámetro `codes` de la query string. La clave de cada ítem es su EAN si lo tiene, igual que en el GET.

**Body** *(obligatorio)*

Esquema: [`StockByDepositsRequest`](#stockbydepositsrequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Stock desglosado por código y depósito | [`StockByDepositsResponse`](#stockbydepositsresponse) |


### `GET /api/deposits/sku/{sku}`

> ⚠ **DEPRECADO.** No lo uses para desarrollo nuevo.

**Gestión de ítem de depósito por SKU**

En construcción, sin funcionalidad: acepta cualquier método (GET, POST, PUT, DELETE), no lee ni escribe datos y responde siempre `code` 900, `status` 0, `msg` vacío.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `sku` | path | **sí** | string | SKU del ítem de depósito |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Respuesta del endpoint (en construcción) | [`GenericSuccessResponse`](#genericsuccessresponse) |


## Envíos

Cotización, generación de etiquetas, zonas y métodos de envío. Si el módulo Envíos está apagado en la tienda, todos estos endpoints responden HTTP 403 con `code 910`.

### `POST /api/envios/cotizar`

> ⚠ **DEPRECADO.** No lo uses para desarrollo nuevo.

**Cotizar costo de envío (deprecado: no cotiza)**

Deprecado y sin función: no cotiza nada. Con `order.shipping_type` = 11 (OCASA) la validación de campos se rompe con un error de PHP y la request termina sin JSON (BUG-308); con cualquier otro valor responde `code` 900, `status` 0 y `amount` 0. El cálculo de OCASA está desactivado en el código. Lee el body desde `request->data`, así que requiere POST con `Content-Type: application/json`. Si faltan `order.shipping_type` u `order.shipping_method` responde HTTP 200 con `status` 2 y `code` 4 (código propio del módulo, no es un `API_*`; BUG-1114).

**Body** *(obligatorio)*

Esquema: [`EnviosCotizarRequest`](#envioscotizarrequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Siempre HTTP 200. Éxito: `code` 900, `status` 0, `amount` 0. Datos faltantes: `code` 4, `status` 2, `amount` 0 y `msg` con el campo que falta | [`EnviosCotizarResponse`](#envioscotizarresponse) |


### `GET /api/envios/getFullShippingMethods`

**Listar los métodos de envío habilitados**

Devuelve los métodos de envío habilitados en la tienda (los `always_on` y los que tienen su tarifa activada, dentro de una logística habilitada) como mapa `id del método → "Logística / Método"`. Para las logísticas propias (10 envío propio a domicilio, 13 sucursal propia) y "a convenir" (8) el segundo término es el nombre de la logística. Excluye MercadoEnvíos (33 y 34) si el módulo está apagado. No es un catálogo histórico: un método deshabilitado no aparece aunque haya pedidos que lo usen.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de métodos de envío | [`ShippingMethodsResponse`](#shippingmethodsresponse) |


### `POST /api/envios/generate`

**Generar e imprimir la etiqueta de envío de un pedido**

Genera la guía (si el pedido no la tiene) e imprime la etiqueta en la logística integrada del pedido, vía nsSync. `orderID` va en el body JSON; se lee directo del body, sin depender del Content-Type. En éxito responde `trackID` (la guía, también en la primera generación) y `labelUrl`. **En éxito `code` vale 503, no 900: discriminar por `status` (0 = éxito)** (BUG-1194). Errores, todos con HTTP 200 (BUG-1114): sin `orderID` → `code` 503 y `status` 2; envío inexistente, datos incompletos, generación o impresión rechazada por la logística (con el motivo en `msg`) o logística que no genera guía ni etiqueta por API → `code` 901 y `status` 2. En tiendas con una versión anterior al arreglo de BUG-1195, la primera generación devuelve `trackID` null y una generación fallida o una logística no integrada responden `status` 0 "Etiqueta impresa." sin etiqueta.

**Body** *(obligatorio)*

Esquema: [`EnviosGenerateRequest`](#enviosgeneraterequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Siempre HTTP 200. Éxito: `status` 0 (y `code` 503), con `trackID` y `labelUrl`. Error: `status` 2 con `code` 503 u 901 y el motivo en `msg` | [`EnviosGenerateResponse`](#enviosgenerateresponse) |


### `GET /api/envios/zones`

**Listar zonas de envío**

Retorna todas las zonas de envío configuradas, ordenadas alfabéticamente. Cada zona incluye id, name y zip_codes (códigos postales asociados). Sin zonas configuradas, `zones` llega como array vacío `[]`, no como objeto.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de zonas de envío | [`ZonesResponse`](#zonesresponse) |


### `GET /api/envios/cp_zones_map`

**Mapa de código postal a zona**

Retorna un mapa código postal → id de zona (entero). Si un mismo CP figura en más de una zona, gana la de nombre alfabéticamente menor. Se cachea hasta que se guarda una zona. Sin zonas, `map` llega como `[]`.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa CP → zona | [`CpZonesMapResponse`](#cpzonesmapresponse) |


## Garantías

Gestión de garantías, tipos, estados y resoluciones

### `GET /api/garantias/typeList`

**Listar tipos de garantía**

Tipos de reclamo habilitados en la tienda, como mapa id → nombre. Ids fijos 1 a 6 (1 garantía, 2 service, 3 reparación, 4 devolución, 5 y 6 personalizados); los nombres los puede cambiar cada tienda, así que no cablearlos. Si el módulo Garantías está apagado en la tienda (viene apagado de fábrica) **responde HTTP 403 con `code 910`**.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de tipos de garantía | [`GarantiasKeyValueListResponse`](#garantiaskeyvaluelistresponse) |


### `GET /api/garantias/statusList`

**Listar estados de garantía**

Todos los estados de reclamo, abiertos y cerrados, como mapa id → nombre: 1 abierto, 2 aceptada, 7 en espera, 4 rechazado, 3 cerrado, 5 eliminado, y 10/11 personalizados sólo si la tienda les puso nombre. Los nombres son configurables. Los que cuentan como abiertos (y lista `GET /api/garantias/list`) son 1, 2, 4, 7 y 10. Si el módulo Garantías está apagado en la tienda (viene apagado de fábrica) **responde HTTP 403 con `code 910`**.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de estados de garantía | [`GarantiasKeyValueListResponse`](#garantiaskeyvaluelistresponse) |


### `GET /api/garantias/brandResolutionList`

**Listar resoluciones de marca**

Resoluciones habilitadas del lado de la marca/fabricante, como mapa id → nombre. Defaults: 9 sin definir, 1 cambio, 2 refurbish, 3 reparación, 4 nota de crédito, 5 devolución de dinero; 6 y 8 personalizadas, apagadas por defecto. Nombres configurables por tienda. Si el módulo Garantías está apagado en la tienda (viene apagado de fábrica) **responde HTTP 403 con `code 910`**.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de resoluciones de marca | [`GarantiasKeyValueListResponse`](#garantiaskeyvaluelistresponse) |


### `GET /api/garantias/clientResolutionList`

**Listar resoluciones para el cliente**

Resoluciones habilitadas del lado del cliente, como mapa id → nombre. Mismo catálogo de ids que `brandResolutionList` (9 sin definir, 1 cambio, 2 refurbish, 3 reparación, 4 nota de crédito, 5 devolución de dinero, 6/8 personalizadas) pero con habilitación propia. Si el módulo Garantías está apagado en la tienda (viene apagado de fábrica) **responde HTTP 403 con `code 910`**.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de resoluciones para el cliente | [`GarantiasKeyValueListResponse`](#garantiaskeyvaluelistresponse) |


### `GET /api/garantias/list`

**Listar garantías abiertas**

Lista paginada de reclamos en estado abierto (1 abierto, 2 aceptada, 4 rechazado, 7 en espera, 10 personalizado abierto), del más nuevo al más viejo. Filtro disponible: `seller_id` (reclamos de los clientes del vendedor). **`id` y `client_id` hoy se ignoran** (defecto abierto, BUG-1105): devuelven el listado completo. Incluye `info` con los clientes de la página, el catálogo de estados y el prefijo de numeración; `info.clients` se trunca a 50 clientes (BUG-931). Las filas de `list` traen hoy todas las columnas de la tabla (BUG-1106). Si el módulo Garantías está apagado en la tienda (viene apagado de fábrica) **responde HTTP 403 con `code 910`**.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | query | no | integer | ID de garantía específica. Hoy se ignora (defecto abierto, BUG-1105). |
| `seller_id` | query | no | integer | ID del vendedor — filtra garantías de sus clientes asignados (sin clientes asignados, la lista sale vacía) |
| `client_id` | query | no | integer | ID del cliente. Hoy se ignora (defecto abierto, BUG-1105). |
| `page` | query | no | integer, default `1` | No se castea ni se valida (BUG-1115) |
| `limit` | query | no | integer, default `500` | Registros por página. Default 500, máximo 1000. ⚠ Un valor negativo no se recorta y quita el tope (BUG-1115). |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista paginada de garantías con info complementaria | [`GarantiasListResponse`](#garantiaslistresponse) |


## Inbox

Mensajería interna y departamentos de atención

### `GET /api/inbox/departmentsList`

**Listar departamentos de atención**

Departamentos de atención configurados en el Inbox —también los deshabilitados—, como mapa id → nombre, ordenado alfabéticamente. Sin departamentos devuelve `list: []`. El módulo Inbox viene apagado de fábrica: apagado, responde HTTP 403 con `code 910`.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de departamentos de atención | [`InboxDepartmentsResponse`](#inboxdepartmentsresponse) |


## Marketplaces

Canales de venta y replicación de órdenes

### `GET /api/marketplaces/list`

**Listar canales de venta habilitados**

Canales de venta habilitados, indexados por ID. Fijos: `1` `onlinestore` (tienda online), `2` `mercadolibre` (si MercadoLibre está activo), `3` `wholesaler` (mayorista, si distribución está activa) y `4` `showroom` (tienda física). A ellos se suman los marketplaces propios **habilitados** (IDs desde 1001, código de 8 caracteres). Todos traen `id`, `code`, `status`, `name`, `prefix` y `color`; **`description` sólo la traen los cuatro fijos**. Los IDs 1-4 son iguales en todas las tiendas; los de los marketplaces propios no. ⚠ Un marketplace recién creado o editado puede tardar hasta la próxima limpieza de caché (diaria) en aparecer (BUG-1149).

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Mapa de marketplaces habilitados | [`MarketplacesListResponse`](#marketplaceslistresponse) |


## Newsletters

Suscripciones a newsletter y gestión de suscriptores

### `POST /api/newsletters/subscribe`

**Registrar nueva suscripción**

Registra un suscriptor al newsletter. Body JSON con `mail` (obligatorio, validado como e-mail; se guarda en minúscula y sin espacios) y `nombre` (opcional; también se acepta `name` por compatibilidad). Siempre crea un registro nuevo: no detecta si el mail ya existe ni reactiva una baja anterior (BUG-1177). Atención: los errores responden HTTP 200 con `status: 2` (familia de BUG-552): mail inválido → `code` 504; body vacío → `code` 904. Si el módulo Newsletters está deshabilitado (lo está de fábrica), responde HTTP 403 con `code 910`.

**Body** *(obligatorio)*

Esquema: [`NewsletterSubscribeRequest`](#newslettersubscriberequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Suscripción registrada (`code` 900, `status` 0) o error con el mismo HTTP 200 (`status` 2): `code` 504 si el mail no es válido, `code` 904 si el body vino vacío | [`GenericSuccessResponse`](#genericsuccessresponse) |


### `GET /api/newsletters/subscribers`

**Listar suscriptores del newsletter**

Retorna los registros de suscripción agrupados por e-mail, ordenados por e-mail y sin paginación (toda la tabla en una respuesta, BUG-1180). Modo `list`: objetos con id, mail, created y nombre, tomados de una fila cualquiera del grupo, porque la tabla admite mails repetidos. Modo `only_mails`: array de e-mails. Atención: incluye las bajas y los eliminados (no filtra `subscripto` ni `deleted`, BUG-1178): no usar esta lista para enviar. En hostings con `ONLY_FULL_GROUP_BY` la consulta puede fallar (BUG-1179). En `only_mails` sin suscriptores la clave `list` no viene. Si el módulo Newsletters está deshabilitado (lo está de fábrica), responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `mode` | query | no | string (`list` · `only_mails`), default `list` | Modo de respuesta. Cualquier otro valor se trata como `list` |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista de suscriptores | [`NewsletterSubscribersResponse`](#newslettersubscribersresponse) |


## Presupuestos

Presupuestos: alta, consulta, edición y cambio de estado

### `POST /api/presupuestos/budget`

**Crear un presupuesto**

Alta de un presupuesto completo (cliente, criterio de IVA, vigencia, ítems, envío y condiciones). Es el endpoint por el que un ERP, nsCentral o el propio módulo de Pedidos generan una cotización. **Ítems**: se resuelven por `code` (SKU o código de barras). Si alguno no existe, **se rechaza el presupuesto entero** con 400 y la lista de códigos fallidos en `not_found` — emitir una cotización a la que le faltan líneas, en silencio, es peor que no emitirla. Sólo códigos de artículo (`articulos.sku` o `articulos.codigo`, no borrados): un SKU de variante no se resuelve y va a `not_found`. **Todos** los ítems tienen que traer `code`: si alguno no lo trae, se rechaza el presupuesto entero con 400 y las posiciones de esos ítems (desde 1) en `missing_code` (BUG-1158). El `price` del llamador manda sobre el catálogo: un presupuesto es una cotización pactada. **Idempotencia**: sólo con `source` = 17 (generado desde un pedido) y `source_ref_id`. Reenviar el mismo pedido devuelve **409 con el `budgetID` previo** y `duplicate: true`, que hay que tratar como éxito. Con otro `source` no hay deduplicación: dos POST crean dos presupuestos. Un presupuesto previo ELIMINADO no cuenta. **Envío** (`shipping_cost`): va **SIEMPRE CON IVA INCLUIDO**, igual que los precios de ítem. Con `shipping_included = true` figura en el detalle y suma al total del documento impreso; con `false` figura aparte y no suma. En los dos casos **queda fuera de `amount`**. **Descuento general** (`general_discount`): porcentaje que se aplica **sólo** a los ítems que no traen `offer` propio. **Los descuentos son porcentajes ENTEROS**, igual que en el panel y en el pedido que sale del presupuesto: `offer` y `general_discount` con decimales se redondean **antes** de calcular `deal_price` (BUG-1161). `POST` con un id en la URL responde **405**: para modificar se usa `PUT /api/presupuestos/budget/{id}` (BUG-1163). ⚠ El módulo Presupuestos viene apagado de fábrica; con el módulo apagado este endpoint responde HTTP 403 con `code 910`.

**Body** *(obligatorio)*

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `201` | Presupuesto creado en estado PENDIENTE (1) | — |
| `400` | El presupuesto NO se creó. `code` 904 cuerpo vacío o JSON inválido · 502 sin ítems, o algún ítem sin `code` (posiciones en `missing_code`) · 504 `due_date` inválida · 702 códigos inexistentes (lista en `not_found`) · 901 no se pudo grabar | — |
| `409` | El pedido (`source` 17 + `source_ref_id`) ya generó un presupuesto no eliminado. Es un ÉXITO: `status` 0, `code` 900 | — |


### `GET /api/presupuestos/budget/{id}`

**Detalle de un presupuesto**

Devuelve la cabecera con sus ítems, el bloque `shipping` y el bloque `source` (de dónde nació el presupuesto). **`amount` NO incluye el costo de envío**, ni siquiera con `shipping.included = true`: hay que sumarlo aparte, que es lo mismo que hace la vista de impresión. **`source.ref_id` y `order_id` son cosas distintas**: `source.ref_id` es el pedido del que este presupuesto NACIÓ; `order_id` es el pedido que este presupuesto GENERÓ. No se devuelven costos, margen ni adjuntos internos. Las fechas salen como `YYYY-MM-DD HH:MM:SS` (también `due_date`); `items` no aparece si el presupuesto no tiene ítems. Sobre `/budget/{id}` cada método hace una cosa: GET lee y PUT modifica. POST con id, PATCH, DELETE y cualquier otro responden **405** sin tocar nada (BUG-1163). ⚠ El módulo Presupuestos viene apagado de fábrica; con el módulo apagado este endpoint responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | path | **sí** | integer |  |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Presupuesto con sus ítems | — |
| `404` | Presupuesto inexistente (`code` 702) | — |


### `PUT /api/presupuestos/budget/{id}`

**Modificar datos de un presupuesto**

Modifica campos de cabecera con lista blanca explícita: `title`, `notes`, `conditions`, `due_date`, `tags`, `shipping_cost`, `shipping_included`, `shipping_conditions`. 🔴 **No cambia el estado ni los ítems.** El estado tiene su propio endpoint porque las transiciones llevan guardas de estado de origen que una edición de campos no tiene. Los campos van en la raíz del body o dentro de `budget`. No hay guarda de estado: también se editan presupuestos CONCRETADOS, CANCELADOS o ELIMINADOS. `due_date` tiene que ser una fecha real en formato YYYY-MM-DD; si no, 400 con `code` 504 y no se guarda nada (BUG-1160). ⚠ El módulo Presupuestos viene apagado de fábrica; con el módulo apagado este endpoint responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | path | **sí** | integer |  |

**Body** *(obligatorio)*

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Presupuesto actualizado. Devuelve `budgetID` | — |
| `400` | `code` 904 cuerpo vacío o JSON inválido · 503 ningún campo modificable · 504 `due_date` inválida · 901 no se pudo grabar | — |
| `404` | Presupuesto inexistente (`code` 702). Se verifica antes de leer el cuerpo | — |


### `PUT /api/presupuestos/budget_status/{id}`

**Cambiar el estado de un presupuesto**

Transiciones: aprobar (5), cancelar (3), finalizar (2), eliminar lógico (4), recuperar (1) y los dos estados personalizables de la tienda (6 y 7). **Guardas de negocio**, que devuelven 409 cuando no se cumplen. Son las mismas que el panel (BUG-1162): | Destino | Se llega desde | |---|---| | aprobar (5) · finalizar (2) · cancelar (3) | un estado abierto (1, 5, 6, 7) | | recuperar (1) | sólo ELIMINADO (4) | | eliminar (4) | cualquiera menos ELIMINADO | | personalizado (6 / 7) | sólo APROBADO, y si la tienda lo tiene habilitado | Un CANCELADO ya no vuelve a PENDIENTE ni a APROBADO, y un CONCRETADO no se cancela. Los nombres de los estados son configurables por tienda: pedirlos con `GET /api/presupuestos/status_list` en vez de cablearlos. Sólo acepta **PUT**; otro método responde 405 (BUG-1163). ⚠ El módulo Presupuestos viene apagado de fábrica; con el módulo apagado este endpoint responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | path | **sí** | integer |  |

**Body** *(obligatorio)*

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Estado actualizado. Devuelve `budgetID` y `status_id` | — |
| `400` | `code` 504 estado destino inválido (ver status_list) · 901 no se pudo guardar | — |
| `404` | Presupuesto inexistente (`code` 702) | — |
| `409` | La transición no está permitida desde el estado actual (`code` 906) | — |


### `GET /api/presupuestos/budgets`

**Listar presupuestos**

Listado paginado. El filtro `status` acepta varios IDs separados por coma y entiende el pseudo-estado **100 (ABIERTOS)**, que se expande a los cuatro estados abiertos reales. `order_id` filtra los presupuestos **generados a partir de ese pedido** (`source = 17`). Orden: id descendente. Sin `status` incluye los ELIMINADOS (4): para excluirlos, pedir `status=100,2,3`. `pagination` no trae `offset`, y `has_more` es `results == limit`: si el total es múltiplo de `limit`, la última llamada vuelve vacía. ⚠ El módulo Presupuestos viene apagado de fábrica; con el módulo apagado este endpoint responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `status` | query | no | string | IDs de estado separados por coma. 100 = grupo ABIERTOS. Un texto no numérico vale 0 y no matchea nada |
| `client_id` | query | no | integer |  |
| `seller_id` | query | no | integer |  |
| `order_id` | query | no | integer | Presupuestos generados desde ese pedido |
| `from` | query | no | string | Alta desde (inclusive, 00:00:00). Sólo YYYY-MM-DD: con otro formato, incluido ISO con hora, el filtro se ignora sin error |
| `to` | query | no | string | Alta hasta (inclusive, 23:59:59). Sólo YYYY-MM-DD: con otro formato, incluido ISO con hora, el filtro se ignora sin error |
| `page` | query | no | integer, default `1` |  |
| `limit` | query | no | integer, default `50` |  |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado paginado | — |


### `GET /api/presupuestos/status_list`

**Listar estados de presupuesto**

Estados habilitados **con el nombre que les puso la tienda**. Los dos estados personalizables (6 y 7) los renombra cada tienda —"EN FÁBRICA", "ESPERANDO SEÑA"—, así que un integrador no puede cablear los nombres. `open_status` trae los IDs que el módulo considera abiertos. `list` trae sólo estados reales, con el nombre tal cual ( BUG-1159: antes traía el pseudo-estado 100 "ABIERTOS" y el prefijo "|_ " del selector del panel ). Para filtrar por abiertos en `budgets`, el 100 sigue valiendo como valor de `status`. `open_status` es fijo [5, 1, 6, 7], aunque 6 o 7 estén deshabilitados. ⚠ El módulo Presupuestos viene apagado de fábrica; con el módulo apagado este endpoint responde HTTP 403 con `code 910`.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Estados y estados abiertos | — |


## Returns

Devoluciones y cambios — gestión de tickets de devolución

### `GET /api/returns/list`

**Listar devoluciones**

Lista paginada de tickets de devolución o cambio, **de todos los estados** (para sólo los abiertos, `only_open=true`), ordenada por fecha de alta descendente. También acepta **POST** con los mismos parámetros en un body JSON (el body gana sobre la query). `info` trae los datos de los clientes involucrados y el prefijo de los tickets, o `null` si no hay resultados. ⚠ El módulo de devoluciones viene **apagado** de fábrica; con el módulo apagado, este endpoint responde HTTP 403 con `code 910`.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | query | no | integer | ID de una devolución puntual |
| `seller_id` | query | no | integer | Devoluciones de los clientes asignados a ese vendedor. Si viene también `client_id`, gana `client_id` |
| `client_id` | query | no | integer | ID de usuario del cliente |
| `sale_order_ids` | query | no | string | IDs de pedido de origen, separados por coma |
| `only_open` | query | no | string (`true` · `false`), default `false` | Sólo devoluciones en estados abiertos. Sólo el literal `true` lo activa |
| `item_details` | query | no | string (`true` · `false`), default `false` | Reemplaza `items` (cantidad) por el detalle de cada ítem con SKU, código de barras y variante. Sólo el literal `true` lo activa |
| `page` | query | no | integer, default `1` | Número de página. Mandar un entero ≥ 1 (no se valida, BUG-1115) |
| `limit` | query | no | integer, default `500` | Registros por página. Se topea en 1000. ⚠ Un valor negativo no se recorta y quita el tope (BUG-1115) |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Lista paginada de devoluciones con info complementaria | [`ReturnsListResponse`](#returnslistresponse) |


## Usuarios

Gestión de clientes, perfiles y condiciones comerciales

### `GET /api/usuarios/verifiedConditions`

**Obtener condiciones para cliente verificado**

Retorna las condiciones configuradas para que un cliente pase a "verificado": `min_billing` (facturación mínima, entero), `min_orders` (cantidad mínima de pedidos) y `client_profile_id` (perfil **al que se mueve** el cliente al verificarse; `null` = no cambia de perfil). Si el módulo `usuarios` está apagado responde HTTP 403 con `code 910`.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Condiciones de verificación | [`VerifiedConditionsResponse`](#verifiedconditionsresponse) |


### `GET /api/usuarios/client`

**Obtener datos completos de un cliente**

Busca un cliente por `id`, `code` (código interno, no CUIT) o `mail` — al menos uno. Si se envía más de uno se combinan con AND. Devuelve datos personales, tipo de cliente, empresa, una dirección de envío, condiciones de pago, transportes (`shippingOptions`) y, con el módulo BCRA activo, `credit_risk`. **Los campos `credit_*` vienen hoy siempre en 0 (defecto de código, BUG-1104).** El país de la dirección es el de la tienda (BUG-1109).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `id` | query | no | integer | ID del usuario |
| `code` | query | no | string | Código interno del cliente |
| `mail` | query | no | string | Email del cliente |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Cliente encontrado. **Si no existe también responde HTTP 200**, con `code: 702`, `status: 2` y sin la clave `client` (BUG-1113, BUG-1114). | [`ClientResponse`](#clientresponse) |
| `400` | Sin clave de búsqueda — id, code y mail vacíos (`code` 701) | [`ApiError`](#apierror) |


### `GET /api/usuarios/clients`

**Listar clientes con paginación y filtros**

Listado paginado de clientes, ordenado por login. **Los filtros `search`, `sellerID` e `ids` son excluyentes**: se aplica sólo el primero presente en ese orden y los demás se ignoran. `search` busca por login o mail (contiene) o por código exacto. `credit_level` (con el módulo BCRA activo) se combina con cualquiera. **Sin resultados —incluida una página posterior a la última— responde HTTP 404 con `status: 0` y `clients: []`**: tratarlo como fin de la paginación. Incluye tipo de cliente, vendedor asignado, VIP, créditos (`credit_committed` hoy siempre 0, BUG-1104) y condiciones de pago.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `sellerID` | query | no | integer | Filtrar por ID del vendedor asignado |
| `ids` | query | no | string | IDs de clientes separados por coma |
| `search` | query | no | string | Búsqueda por login, email o código |
| `credit_level` | query | no | string | Niveles de riesgo BCRA separados por coma (0 a 5), p. ej. `4,5`. Sólo se aplica si el módulo BCRA está activo; si no, se ignora. |
| `page` | query | no | integer, default `1` | Página actual. No se castea ni se valida (BUG-1115) |
| `limit` | query | no | integer, default `50` | Registros por página. Default 50, máximo 1000 (valores mayores se topean). ⚠ Un valor negativo no se recorta y quita el tope (BUG-1115). |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado de clientes | [`ClientsListResponse`](#clientslistresponse) |
| `404` | Sin resultados (o página posterior a la última). Body de éxito: `code: 900`, `status: 0`, `clients: []`, `pagination`. | [`ClientsListResponse`](#clientslistresponse) |


### `GET /api/usuarios/client_profiles`

**Listar perfiles (tipos) de clientes**

Retorna todos los perfiles de clientes configurados con nombre, descripción, color, si es perfil por defecto, email de notificación y si tiene acceso a la tienda (shop = enable_cart && access).

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado de perfiles de clientes | [`ClientProfilesResponse`](#clientprofilesresponse) |
| `404` | Sin perfiles configurados. Body de éxito (`status: 0`, `code: 900`) sin la clave `profiles`. | [`GenericSuccessResponse`](#genericsuccessresponse) |


### `POST /api/usuarios/points/{userID}`

**Cargar puntos a un cliente**

Suma (o resta, con `points` negativo) puntos de fidelidad al cliente `userID`. `type` es el ID de origen: 1 compra, 2 review, 3 manual, 4 encuesta, 5 consumo, 6 registro, 7 recuperados. **Enviar `Content-Type: application/json`**: sin ese header responde 409 aunque el body sea correcto. **Si `type` no es uno de esos valores o `points` es 0 no carga nada y responde éxito igual** (BUG-1107). No devuelve el saldo. GET no está implementado (responde vacío).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `userID` | path | **sí** | integer | ID del usuario/cliente |

**Body** *(obligatorio)*

Esquema: [`PointsLoadRequest`](#pointsloadrequest)

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Puntos cargados correctamente | [`GenericSuccessResponse`](#genericsuccessresponse) |
| `400` | Sin `userID`, o método PUT/DELETE. **Ojo: el body trae `status: 0` y `code: 900` aunque el HTTP sea 400** (BUG-1116). | [`ApiError`](#apierror) |
| `409` | Falta `type` o `points` (code 502). También ocurre si el body no se envía con Content-Type: application/json. Es un error de datos, no un duplicado: este endpoint no deduplica (BUG-1153). | [`ApiError`](#apierror) |


### `GET /api/usuarios/suppliers`

**Listar proveedores (branders)**

Retorna todos los proveedores (branders) no eliminados —incluidos los deshabilitados, ver `enabled`— con id, login, nombre, apellido, código, email y los IDs de marcas asignadas. Sin paginación.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado de proveedores. **Sin proveedores también responde HTTP 200** (status 0, sin la clave `suppliers`; BUG-1113, BUG-1114). | [`SuppliersListResponse`](#supplierslistresponse) |


### `GET /api/usuarios/sellers`

**Listar vendedores/ejecutivos por IDs**

Retorna vendedores (ejecutivos comerciales) filtrados por IDs separados por coma. Cada vendedor incluye datos serializados: id, login, nombre, apellido, código, email. Incluye vendedores deshabilitados (ver `enabled`).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `ids` | query | **sí** | string | IDs de vendedores separados por coma |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Vendedores encontrados. **Los errores también salen HTTP 200** (BUG-1113, BUG-1114): sin `ids` → `code: 701`, `status: 2`; sin resultados → `code: 900`, `status: 0` sin la clave `sellers`. | [`SellersListResponse`](#sellerslistresponse) |


## Utils

Utilidades generales — conexión, provincias, IVA y generación de códigos EAN

### `GET /api/utils/dbProvinces/{countryCode}`

**Obtener provincias ISO 3166-2 por país**

Lista fija (no sale de la base) de provincias o regiones ISO 3166-2 de AR (24), UY (19) o CL (16). El código distingue mayúsculas: `ar` devuelve vacío. AR y UY vienen en mayúsculas y sin tildes; CL con mayúscula inicial y tildes. País no soportado: `provinces` = `[]`. Errata conocida: UY-TT dice "TRINTA Y TRESs" (BUG-1037).

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `countryCode` | path | **sí** | string, default `AR` | Código ISO alpha-2 del país, en mayúsculas (AR, UY, CL). Si se omite el segmento se usa AR |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado de provincias ISO 3166-2 | [`DbProvincesResponse`](#dbprovincesresponse) |


### `GET /api/utils/provinces`

**Listar provincias de la base de datos**

Provincias habilitadas de la tabla `provincias` (las que se ofrecen en el checkout), ordenadas por nombre, como mapa `id → nombre` en mayúscula inicial (ej. "Capital Federal"). No filtra por país: devuelve lo que tenga cargado la tienda.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado de provincias | [`ProvincesResponse`](#provincesresponse) |


### `GET /api/utils/provincesInfo`

**Obtener provincias con información extendida**

Provincias habilitadas con ID interno de nubixstore (`ns_id`), código ISO 3166-2 (`code`) y nombre tal como está guardado (`name`). Útil para mapear provincias entre sistemas.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado de provincias con información extendida | [`ProvincesInfoResponse`](#provincesinforesponse) |


### `GET /api/utils/IVAConditions`

**Obtener condiciones de IVA**

Catálogo fijo de condiciones frente al IVA según el país de la tienda; no es configurable ni se filtra. AR: 4 CONSUMIDOR FINAL, 1 RESPONSABLE INSCRIPTO, 5 MONOTRIBUTO, 3 IVA EXENTO. UY y CL: 4 CONSUMIDOR FINAL, 1 RUT. País desconocido: el de AR. Mapa `id → nombre`, en mayúsculas.

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Listado de condiciones de IVA | [`IVAConditionsResponse`](#ivaconditionsresponse) |


### `GET /api/utils/EAN/{number}/{prefix}`

**Generar código EAN-13**

Arma `prefix` + `number` rellenado con ceros a la DERECHA hasta 9 caracteres y le agrega el dígito verificador EAN-13. Sólo da un EAN-13 válido si `prefix` tiene 3 dígitos y `number` hasta 9 dígitos: no hay validación, y con más dígitos o con letras el resultado no es un EAN-13 (y `CHECK` puede igual dar true con letras). Sin `number` la acción falla sin JSON (BUG-1198). Ej.: /api/utils/EAN/1234 → 7791234000005.

**Parámetros**

| Nombre | En | Obligatorio | Tipo | Descripción |
|---|---|---|---|---|
| `number` | path | **sí** | string | Número base, hasta 9 dígitos. Se rellena con ceros a la derecha (1234 → 123400000) |
| `prefix` | path | **sí** | string, default `779` | Prefijo GS1 de 3 dígitos. Si se omite el segmento se usa 779 (Argentina) |

**Respuestas**

| Código | Descripción | Esquema |
|---|---|---|
| `200` | Código EAN-13 generado | [`EANResponse`](#eanresponse) |



---

## Esquemas

89 esquemas alcanzables desde los endpoints de este documento.

### `ApiError`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer | sí |  |
| `status` | integer | sí |  |
| `msg` | string | sí |  |
| `referer` | string |  | Referer del request, eco |
| `IP` | string |  | IP del cliente, eco |
| `httpCode` | integer |  | Presente sólo en las ramas que fijan el status HTTP explícitamente; repite el status de la respuesta |

### `ArticuloItem`

Artículo del catálogo. Es el modelo que devuelven **`GET /api/articulos/item`** (en `item`) y **`GET /api/articulos/items`** (en cada elemento de `items[]`). **Los tres códigos del producto viajan siempre**, a nivel ítem y dentro de cada elemento de `variants[]`: | Campo | Origen | Qué es | |---|---|---| | `barcode` | `articulos.codigo` | Código de barras (EAN) | | `sku` | `articulos.sku` | SKU interno de la tienda | | `skuf` | `articulos.mcode` | SKU del fabricante (SKU-F) | ⚠ **Cómo se representa un código vacío — no es uniforme, verificado contra la API:** - **A nivel ítem**, `barcode` y `skuf` vienen como **cadena vacía `""`**, nunca `null`. `sku` sí viene `null`. - **Dentro de `variants[]`**, los tres vienen **`null`**. - En `GET /api/articulos/stock` los tres vienen **`null`**. Un consumidor que use `?? ` / `is_null` para detectar "sin código" va a tomar `""` como un código válido. Usar una comprobación de valor vacío. Cuando la búsqueda resuelve a una **variante** (`oid`, o un `sku`/`code` que pertenece a una opción), los tres códigos a nivel ítem **se pisan con los de esa opción**. **Alcance de los campos según el endpoint:** `GET /api/articulos/items` con `compact=true` (default) devuelve exactamente las claves listadas acá. Con `compact=false` cambia el juego de claves (se agrega el desglose de productos con rango) y `GET /api/articulos/item` devuelve un **superconjunto** con campos extra de ficha (`body` con `html=1`, `variation_attributes`, etc.). Por eso el schema admite propiedades adicionales.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ID interno del artículo. |
| `name` | string |  | Nombre. Si la fila corresponde a una variante desplegada, incluye el nombre de la variante concatenado. |
| `slug` | string |  |  |
| `permalink` | string |  | URL pública del producto en la tienda. |
| `enabled` | integer (`0` · `1`) |  | 1 = publicado, 0 = deshabilitado. En /items un artículo deshabilitado devuelve stock = 0; en /item el stock se devuelve tal cual. |
| `sku` | string |  | SKU interno (`articulos.sku`). **`null`** si no está cargado. |
| `barcode` | string |  | Código de barras / EAN (`articulos.codigo`). **Cadena vacía `""`** si no está cargado — nunca `null`. |
| `skuf` | string |  | SKU del fabricante / SKU-F (`articulos.mcode`). **Cadena vacía `""`** si no está cargado — nunca `null`. |
| `price` | number |  | Precio de lista vigente para el `client_type` consultado. |
| `deal_price` | number |  | Precio final con la oferta aplicada. |
| `off` | number |  | Porcentaje de descuento aplicado (0 = sin oferta). |
| `range_price` | boolean |  | true = el precio depende de la variante elegida; `price` es orientativo y el precio real está en cada `variants[]`. |
| `currency` | string |  | Código de moneda de todos los importes del ítem. |
| `vat` | string |  | Alícuota de IVA. **Viaja como string**, no como número. |
| `other_taxes` | number |  | Otros impuestos internos distintos de IVA. Hoy siempre 0. |
| `stock` | integer |  | Stock minorista disponible. |
| `stock_id` | integer |  | Estado de stock (según la tabla de estados de la tienda). |
| `committed_stock` | integer |  | Stock comprometido: reservado por pedidos sin despachar. **Se escribe con dos `t`** desde la API 2.3.0; la grafía vieja `commited_stock` ya no se emite. |
| `critical_stock` | integer |  | Umbral de stock crítico configurado para el artículo. |
| `presale` | integer (`0` · `1`) |  | 1 = se vende sin stock (preventa). |
| `units` | integer |  | Unidades que entrega el ítem. Distinto de 1 sólo en combos y packs. |
| `combo` | integer (`0` · `1`) |  |  |
| `pack` | integer (`0` · `1`) |  |  |
| `new` | integer (`0` · `1`) |  |  |
| `free_shipping` | integer (`0` · `1`) |  |  |
| `weight` | object |  | ⚠ **`weight` es un objeto** { value, unit, value_in_kg }, a diferencia de `width` / `height` / `depth` / `volume`, que son números sueltos: unit = "gr" hasta 999 g y "Kg" desde 1000 g (con value en kg). En /items con compact=false viaja como número suelto, en gramos. |
| `width` | number |  | Ancho en cm. |
| `height` | number |  | Alto en cm. |
| `depth` | number |  | Profundidad en cm. |
| `volume` | number |  | Volumen en m³. |
| `brand_id` | integer |  |  |
| `category_id` | integer |  | Categoría principal. La API no devuelve las categorías secundarias en este modelo. |
| `brand` | object |  | Marca. Con `id` = `null` y `name` = `""` cuando el artículo no tiene marca asignada. |
| `wholesaler` | integer (`0` · `1`) |  | 1 = habilitado para venta mayorista. |
| `wholesaler_price` | number |  |  |
| `wholesaler_deal` | number |  | Porcentaje de oferta mayorista. |
| `main_pic` | string |  | URL absoluta (CDN) de la imagen principal. |
| `pictures` | array de object |  | Imágenes del artículo, con URL absoluta de CDN, en el orden de la galería de la tienda: la primera es la portada. |
| `video` | string |  | URL del video del producto. `""` si no tiene. |
| `attributes` | array de object |  | Atributos en formato clave/valor: `brand`, `weight`, `width`, `height`, `depth`, `volume`, `bulk`. ⚠ attributes[weight].unit dice "gr" aunque el valor esté en kg (peso > 999 g); usar weight.unit (BUG-1122). |
| `variant_id` | integer |  | ID de la opción cuando la consulta resolvió a una variante puntual. `null` si el ítem es el artículo padre. |
| `variant_name` | string |  | Nombre de la variante resuelta. `""` si no aplica. |
| `variants` | array de [`ArticuloVariant`](#articulovariant) |  | Variantes (opciones/curvas) del artículo. En `/items` es un array, o `null` cuando el artículo no tiene variantes o cuando se pidió `variants=false`. ⚠ En `/item` es un **objeto** con claves `"1"`, `"2"`… (no un array), y la clave no aparece si el artículo no tiene opciones (BUG-439). |
| `marketplace` | object |  | Estado de publicación en marketplace. Sólo tiene contenido útil con `mercadolibre=true`. |
| `marketplace_stock` | integer |  | Stock que se publica en el marketplace. 0 si el artículo no está publicado. |
| `marketplace_price` | number |  |  |
| `marketplace_off` | number |  |  |
| `marketplace_deal_price` | number |  |  |

### `ArticuloItemResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer | sí | ej.: `900` |
| `status` | integer | sí | ej.: `0` |
| `msg` | string | sí | ej.: `Ítem encontrado.` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `item` | [`ArticuloItem`](#articuloitem) |  |  |

### `ArticuloVariant`

Variante (opción / curva) de un artículo, tal como viaja dentro de `variants[]`. **Los tres códigos son propios de la variante**, no del artículo padre: es lo que permite sincronizar cada combinación como un ítem distinto en la plataforma destino. A diferencia del nivel ítem, acá los tres vienen **`null`** cuando no están cargados. ⚠ **La clave del flag de habilitación difiere según el endpoint:** `GET /api/articulos/items` la emite como **`enabled`** y `GET /api/articulos/item` como **`enable`** (sin la `d`). Un consumidor que lea sólo una de las dos toma la variante como deshabilitada en el otro endpoint. Contemplar ambas hasta que se unifique.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ID de la opción. Es la clave estable de la variante. |
| `enabled` | boolean |  | Habilitación de la variante — clave usada por `GET /api/articulos/items`. |
| `enable` | boolean |  | Mismo dato que `enabled`, con el nombre que usa `GET /api/articulos/item`. |
| `name` | string |  | Nombre de la variante. Con curva doble, los dos valores separados por ` \| `. |
| `sku` | string |  | SKU interno de la variante (`opciones.sku`). |
| `barcode` | string |  | Código de barras / EAN de la variante (`opciones.codigo`). |
| `skuf` | string |  | SKU del fabricante de la variante (`opciones.mcode`). |
| `price` | number |  |  |
| `deal_price` | number |  |  |
| `off` | number |  |  |
| `stock` | integer |  | Stock de la variante. **La regla cambia según el endpoint:** `/item` → 0 si el estado de stock de la opción no está habilitado para el carrito; `/items compact=true` → 0 si el artículo o la variante están deshabilitados; `/items compact=false` → el valor crudo, sin anular. |
| `committed_stock` | integer |  | Stock comprometido de la variante. |
| `new` | — |  | Marcada como nueva. ⚠ /item la emite como boolean y /items como entero 0/1 (misma familia que BUG-439). |
| `pictures` | array de object |  | Imágenes asociadas a la variante (subconjunto de las del artículo), en el orden de la galería. |
| `attributes` | array de object |  | Atributos de la curva que definen la variante (talle, color, unidades…). |
| `marketplace_stock` | integer |  |  |
| `marketplace_price` | number |  |  |
| `marketplace_off` | number |  |  |
| `marketplace_deal_price` | number |  |  |

### `ArticulosListResponse`

Respuesta paginada de listado de artículos.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `items` | array de [`ArticuloItem`](#articuloitem) |  |  |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `pagination` | object |  |  |

### `AuthTokenRequest`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `USER` | string | sí | Usuario de API |
| `PASSWORD` | string | sí | Password de API |
| `API_KEY` | string |  | API Key de la tienda. Obligatoria para la mayoría de las cuentas; si falta o no coincide responde code 907 "API Key incorrecta." |

### `AuthTokenResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer (`900` · `907`) | sí | 900 = credenciales válidas · 907 = error de login (cualquier causa) |
| `status` | integer (`0` · `2`) | sí | 0 = éxito · 2 = error |
| `msg` | string | sí | ej.: `Credenciales de acceso válidas.` |
| `access_token` | string |  | Token a usar como Bearer |
| `expires_in` | integer |  | Timestamp UNIX (ahora + 24 h). Informativo: hoy no se valida y el token sigue funcionando después (BUG-515). |

### `BlogCategoriesResponse`

Respuesta de listado de categorías del blog.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Categorias de blog listados correctamente.` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `categories` | array de [`BlogCategory`](#blogcategory) |  |  |

### `BlogCategory`

Categoría del blog.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `5` |
| `parent_id` | integer |  |  |
| `name` | string |  | ej.: `Tutoriales` |
| `title` | string |  | ej.: `Tutoriales y guías` |
| `permalink` | string |  | ej.: `https://www.tienda.com/blog/tutoriales.html` |
| `level` | integer |  | Nivel de profundidad en el árbol (0 = raíz) |

### `BlogPost`

Post del blog con autor, imagen, fecha y categoría.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `author` | object |  |  |
| `img` | string |  | Ruta relativa a la raíz de la tienda (`img/posts/archivo.jpg`), o la imagen de la categoría si el post no tiene |
| `thumb` | string |  | Ruta relativa del thumbnail, con el mismo criterio que `img` |
| `date` | object |  |  |
| `title` | string |  | ej.: `Guía de compra mayorista` |
| `meta_title` | string |  |  |
| `permalink` | string |  |  |
| `description` | string |  | meta_description o, si falta, los primeros 350 bytes del cuerpo sin etiquetas + "...". Puede traer entidades HTML |
| `comments_count` | integer |  |  |
| `category` | object |  | Categoría del post. Llega como array vacío `[]` (no objeto) si la categoría no está habilitada |

### `BlogPostsResponse`

Respuesta paginada de posts del blog.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Posts listados correctamente.` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `posts` | array de [`BlogPost`](#blogpost) |  |  |
| `search` | string |  | Término de búsqueda utilizado |
| `pagination` | object |  |  |

### `BudgetDetail`

Presupuesto serializado por PresupuestosController::_budget2Json(). No incluye costos, margen ni adjuntos. En el listado (GET /budgets) viene sin `items`.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  |  |
| `display_id` | string |  | Prefijo de la tienda + id |
| `title` | string |  |  |
| `status_id` | integer |  | Ver GET /api/presupuestos/status_list |
| `created` | string |  | Formato SQL, hora argentina |
| `modified` | string |  | ej.: `2026-10-14 10:30:00` |
| `due_date` | string |  | Datetime, no date |
| `vat_criteria` | integer (`0` · `1` · `2`) |  |  |
| `amount` | number |  | Sin descuentos y SIN envío |
| `amount_with_discount` | number |  | Con descuentos y SIN envío |
| `general_discount` | number |  |  |
| `quantity` | integer |  |  |
| `units` | integer |  |  |
| `shipping` | object |  |  |
| `client` | object |  |  |
| `source` | object |  |  |
| `order_id` | integer |  | Pedido que este presupuesto GENERÓ |
| `tags` | array de string |  |  |
| `items` | array de object |  | Ausente (no vacío) cuando el presupuesto no tiene ítems |

### `CampaingsListResponse`

Respuesta de listado de campañas comerciales.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | Texto fijo copiado del cron_list: dice "tareas" aunque devuelve campañas |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `list` | object |  | Mapa id de campaña → nombre. Incluye campañas deshabilitadas y eliminadas |

### `CategoriaArticulo`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `12` |
| `parent_id` | integer |  |  |
| `level` | integer |  | Profundidad en el árbol. |
| `leaf` | integer (`0` · `1`) |  | 1 = categoría hoja (sin subcategorías). |
| `name` | string |  | ej.: `Electrónica` |
| `slug` | string |  | ej.: `electronica` |
| `enabled` | integer (`1`) |  | Siempre 1: el endpoint sólo devuelve categorías habilitadas. |
| `permalink` | string |  |  |

### `CategoriesListResponse`

Respuesta de listado de categorías.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado de categorias listado correctamente.` |
| `categories` | array de [`CategoriaArticulo`](#categoriaarticulo) |  |  |

### `ClientProfileItem`

Perfil (tipo) de cliente.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `1` |
| `name` | string |  | ej.: `Minorista` |
| `description` | string |  | ej.: `Cliente minorista estándar` |
| `color` | string |  | ej.: `3498db` |
| `default` | integer (`0` · `1`) |  | 1 si es el perfil por defecto |
| `notify_to` | string |  | Email de notificación para este perfil |
| `shop` | boolean |  | True si el perfil tiene acceso a comprar en la tienda (enable_cart && access) |

### `ClientProfilesResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Perfiles encontrados.` |
| `profiles` | object |  | Mapa profile_id → datos del perfil de cliente |

### `ClientResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Cliente encontrado.` |
| `client` | object |  | Datos serializados del cliente con información comercial y de envío |

### `ClientsListResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Clientes encontrados.` |
| `clients` | object |  | Mapa user_id → datos serializados del cliente con tipo, vendedor, VIP, créditos y condiciones de pago |
| `pagination` | object |  |  |

### `CodeEntry`

Entrada de código nuevo o modificado.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `created` | string |  | Fecha "YYYY-MM-DD HH:MM:SS" en hora local de la tienda (no RFC 3339). ⚠ Hoy llega null en la mayoría de las entradas de new_codes (BUG-1074). |
| `modified` | string |  | ej.: `2026-10-13 14:05:00` |
| `item_id` | integer |  |  |
| `option_id` | integer |  |  |
| `brand_id` | integer |  |  |
| `sku` | string |  | change_codes lo emite como string ("" si falta); new_codes puede emitir null. |
| `barcode` | string |  |  |

### `CodesResponse`

Respuesta de consulta de códigos nuevos o modificados.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `results` | integer |  | Cantidad de códigos encontrados |
| `codes` | object |  | Mapa clave = SKU (o código de barras si no hay SKU) → datos. ⚠ Sin resultados viaja como array vacío [], no como objeto. |

### `CpZonesMapResponse`

Respuesta con mapa invertido de código postal a zona de envío.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado de zonas` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `map` | object |  | Mapa código_postal → zone_id. Si un CP figura en más de una zona, gana la de nombre alfabéticamente menor. Sin zonas llega como `[]` |

### `CuponesStatusResponse`

Estado general del módulo de cupones con conteos y alertas.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | Llega vacío |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `statusInfo` | object |  |  |

### `DbProvincesResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `provinces` | object |  | Mapa código ISO 3166-2 → nombre. País no soportado: array vacío `[]` (no objeto) |

### `DepositItem`

Depósito del sistema.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `1` |
| `name` | string |  | ej.: `Depósito Central` |
| `code` | string |  | ej.: `DEP-001` |
| `status` | string (`enabled`) |  | Hoy vale siempre `enabled`, sea cual sea el estado real del depósito (BUG-297). Para conocer el estado, filtrar con `?status=` |

### `DepositsListResponse`

Respuesta de listado de depósitos.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado de depositos` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `deposits` | object |  | Mapa de depósitos indexados por ID. Sin resultados llega como array vacío `[]` |

### `EANResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `EAN` | string |  | Código generado: prefijo + número rellenado con ceros a la derecha + dígito verificador. Sólo es un EAN-13 si la entrada era válida (BUG-1198) |
| `CHECK` | boolean |  | true si el dígito verificador es correcto. Puede dar true con una entrada que no produce un EAN-13 (BUG-1198) |

### `EnviosCotizarRequest`

Endpoint deprecado: estos campos sólo se validan por presencia y no se usan para calcular nada.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `order` | object | sí |  |
| `weight` | number |  | Peso. Sólo OCASA |
| `height` | number |  | Alto en cm. Sólo OCASA |
| `width` | number |  | Ancho en cm. Sólo OCASA |
| `depth` | number |  | Profundidad en cm. Sólo OCASA |
| `volume` | number |  | Volumen en m3. Sólo OCASA |
| `zip_code_origin` | string |  | CP de origen, 4 dígitos. Sólo OCASA |
| `zip_code_destination` | string |  | CP de destino, 4 dígitos. Sólo OCASA |

### `EnviosCotizarResponse`

Respuesta de cotización de envío.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `amount` | number |  | Siempre 0: el cálculo está desactivado. Nunca es null |

### `EnviosGenerateRequest`

Request para generar etiqueta de envío.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `orderID` | integer | sí | ID del pedido de nubixstore |

### `EnviosGenerateResponse`

Respuesta de generación de etiqueta de envío.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | En éxito vale 503 (defecto de código: debería ser 900; BUG-1194). Error: 901. Falta `orderID`: 503 con `status` 2. Discriminar por `status` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `trackID` | string |  | Guía del envío. Sólo en éxito. Viene también en la primera generación (corregido en BUG-1195: en tiendas con una versión anterior a ese arreglo llega null la primera vez y hay que leerla después con GET /api/pedidos/order/{id}) |
| `labelUrl` | string |  | URL del PDF (https://{dominio}/labels/AAAA/MM/…) o link de la logística. Sólo en éxito; puede llegar null si la logística no devolvió una etiqueta descargable |

### `GarantiaItem`

Registro de reclamo. Hoy viaja la fila completa de la tabla (incluye datos de comprador y vendedor) por un defecto en la selección de campos (BUG-1106); contar sólo con estas 5 claves.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `150` |
| `articulo_id` | integer |  | ID del artículo en garantía |
| `usuario_id` | integer |  | ID del cliente |
| `created` | string |  |  |
| `status` | integer |  | ID del estado de la garantía |

### `GarantiasKeyValueListResponse`

Respuesta de listado clave-valor de garantías (tipos, estados o resoluciones).

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `list` | object |  | Mapa id → nombre (ej: {"1": "GARANTIA", "4": "DEVOLUCION"}). Los nombres son configurables por tienda. |

### `GarantiasListResponse`

Respuesta paginada de garantías abiertas con información complementaria.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `list` | object |  | Mapa garantia_id → datos de la garantía |
| `info` | object |  | Información complementaria (null si no hay resultados) |
| `pagination` | object |  |  |

### `GenericSuccessResponse`

Respuesta genérica de éxito de la API.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer | sí | ej.: `900` |
| `status` | integer | sí | 0 = éxito |
| `msg` | string | sí | ej.: `Operación exitosa.` |
| `referer` | string |  | Referer del request, eco |
| `IP` | string |  | IP del cliente, eco |
| `httpCode` | integer |  | Presente sólo en las ramas que fijan el status HTTP explícitamente; repite el status de la respuesta |

### `IVAConditionsResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `IVAConditions` | object |  | Mapa condition_id → nombre (catálogo fijo por país) |

### `InboxDepartmentsResponse`

Respuesta de listado de departamentos de atención del inbox.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado de departamentos listados correctamente` |
| `list` | object |  | Mapa id → nombre del departamento |

### `ItemCreateRequest`

Hace falta `name` y al menos uno de `barcode`, `sku` o `skuf`. Lo que no figura acá se ignora con un aviso en `warnings`.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `ext_ref` | string |  | Tu ID del producto. Clave de idempotencia: el mismo valor devuelve el producto ya creado ( 409, status 0 ). Letras, números y . _ : / # - |
| `name` | string | sí | Título. Sin HTML. El máximo real es el ancho de la columna en esa tienda |
| `barcode` | string |  | EAN. Letras, números y . _ - /. Si es un GTIN numérico con dígito verificador inválido, se guarda igual con un aviso |
| `sku` | string |  | ej.: `ZRP-42` |
| `skuf` | string |  | SKU-F |
| `brand` | object |  | Marca por código exacto. Un código inexistente es 400. Si la marca es una línea, se guarda bajo su marca padre |
| `category_ids` | array de integer |  | IDs de categoría existentes. Como mucho, las que admite la tienda por producto ( `category_id` suelto también se acepta ) |
| `price` | number, default `0` |  | Con punto decimal. Se redondea a los decimales de la tienda como el sync ( hacia abajo en el .5 ), con aviso |
| `stock` | integer, default `0` |  | Se RETIENE: se aplica cuando el producto se aprueba en el panel |
| `vat` | number (`0` · `2.5` · `5` · `10.5` · `21`) |  | IVA en %. Por defecto, el configurado en la tienda |
| `meta_title` | string |  | Por defecto, `name` |
| `meta_description` | string |  | La "breve descripción" del panel. El panel recomienda al menos 100 caracteres |
| `meta_keys` | — |  | Palabras clave: texto separado por comas o lista. Se pasan a minúsculas, sin repetidas; las que no entran en 255 caracteres se descartan con aviso |
| `body` | string |  | Descripción en HTML. Se sanea: quedan p, br, strong, b, em, i, u, ul, ol, li, h2, h3, h4, tablas, blockquote y links http(s) |
| `weight` | number |  | Peso en gramos |
| `width` | number |  | Ancho en cm |
| `height` | number |  | Alto en cm |
| `depth` | number |  | Profundidad en cm |
| `video` | string |  | URL de YouTube: watch?v=, shorts/, embed/ o youtu.be/ |
| `pictures` | array de string |  | URLs http(s) públicas, puertos 80/443. Todo o nada: si una falla, no se crea el producto |
| `notes` | string |  | Se suma a la nota interna del producto, como texto plano |

### `ItemCreateResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | 0 = éxito |
| `msg` | string |  | ej.: `Artículo creado, pendiente de moderación: se habilita cuando alguien lo aprueba desde el panel` |
| `articleID` | integer |  | ej.: `4812` |
| `slug` | string |  | ej.: `zapatilla-running-pro-42` |
| `moderation` | string (`pending`) |  | Siempre `pending` al crear: el producto queda deshabilitado hasta que lo aprueben |
| `pictures` | integer |  | Imágenes guardadas |
| `warnings` | array de string |  | Avisos que no impiden el alta ( precio 0, SEO incompleto, campos ignorados, redondeos… ) |
| `httpCode` | integer |  | ej.: `201` |

### `ItemDiscountStockRequest`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `item` | object | sí |  |
| `context` | object |  |  |

### `MPSearchPaymentResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Se encontró la referencia de pago REF-001.` |
| `payment` | object |  | Array vacío [] cuando la búsqueda no trae resultados |

### `MarcaApiItem`

Representa una marca en la respuesta de la API v3.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `1` |
| `name` | string |  | ej.: `Samsung` |
| `slug` | string |  | ej.: `samsung` |
| `enabled` | integer |  | 1 = Activa, 0 = Inactiva |
| `code` | string |  | ej.: `SAM-001` |
| `permalink` | string |  | ⚠ Hoy se arma con base_url_categoria en vez de base_url_marca: puede no ser la URL real de la marca (BUG-1129). |

### `MarcasListResponse`

Respuesta exitosa de listado de marcas.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer | sí | ej.: `900` |
| `status` | integer | sí | ej.: `0` |
| `msg` | string | sí | ej.: `Listado de marcas listado correctamente.` |
| `brands` | array de [`MarcaApiItem`](#marcaapiitem) | sí |  |

### `MarketplaceItem`

Canal de venta / marketplace.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `1` |
| `code` | string |  | ej.: `onlinestore` |
| `status` | integer |  | 1 = habilitado (este listado sólo trae habilitados) |
| `name` | string |  | ej.: `TIENDA ONLINE` |
| `description` | string |  | Sólo en los cuatro marketplaces fijos |
| `color` | string |  | Color hex sin # |
| `prefix` | string |  | Prefijo para numeración de pedidos |

### `MarketplacesListResponse`

Respuesta de listado de marketplaces habilitados.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `list` | object |  | Mapa marketplace_id → datos del marketplace |

### `NewsletterSubscribeRequest`

Request para registrar nueva suscripción al newsletter.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `mail` | string | sí | Email del suscriptor. Se guarda en minúscula y sin espacios |
| `nombre` | string |  | Nombre del suscriptor (opcional) |
| `name` | string |  | Alias aceptado de `nombre`, por compatibilidad. Si vienen los dos, manda `nombre` |

### `NewsletterSubscriber`

Suscriptor del newsletter.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `42` |
| `mail` | string |  | ej.: `martin@ejemplo.com` |
| `created` | string |  |  |
| `nombre` | string |  | ej.: `Martin Carrillo` |

### `NewsletterSubscribersResponse`

Respuesta de listado de suscriptores. En modo `list` retorna objetos completos; en modo `only_mails` retorna array de strings. En modo only_mails sin suscriptores la clave list no viene en la respuesta.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Subscribers list [list].` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `list` | — |  |  |

### `OrderCreateAddress`

Dirección de entrega. También se acepta en la raíz del payload como `address`, **sólo si viene el bloque `shipping`** (sin él, la dirección no se guarda).

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `street` | string |  | Calle. ej.: `Av. Callao` |
| `number` | string |  | Altura. ej.: `1200` |
| `floor` | string |  | Piso |
| `department` | string |  | Departamento |
| `zip_code` | string |  | Código postal. ej.: `1023` |
| `city` | string |  | Localidad. ej.: `CABA` |
| `province_iso` | string |  | Provincia en ISO 3166-2 (ej.: `AR-C`). Alternativas: `province` con el nombre exacto, o `province_id` con el ID interno |
| `country` | string |  | **Ignorado**: la tienda usa su propio país. |

### `OrderCreateBilling`

Datos de facturación.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | string |  | CUIT, o DNI si no hay CUIT |
| `type` | integer |  | Condición de IVA. Catálogo en `/api/utils/IVAConditions` |
| `address` | string |  | Domicilio fiscal |
| `company` | string |  | Razón social |

### `OrderCreateBranch`

Sucursal del **transportista** donde retira el comprador. Se guarda como sucursal de correo del pedido. **No usar para retiro en local** — para eso está `pickup_store`.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | string |  | Código de sucursal del correo. ej.: `OCA-1234` |
| `address` | string |  | Dirección de la sucursal |

### `OrderCreateClient`

Datos del comprador.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `name` | string |  | Nombre del comprador. ej.: `Juan` |
| `last_name` | string |  | Apellido del comprador. ej.: `Pérez` |
| `phone` | string |  | Teléfono de contacto |
| `card_id` | string |  | Documento del comprador (DNI) |
| `mail` | string |  | Email del comprador |

### `OrderCreateItem`

Una línea de la orden.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | string |  | SKU o código de barras. No se exige: si falta vale `S/C`. Si no existe en esta tienda, el ítem se crea igual, sin vincular (`articulo_id` 0). ej.: `SKU-1001` |
| `description` | string |  | Nombre del ítem cuando el código no existe en el catálogo |
| `price` | number |  | Precio unitario. Un precio distinto de 0 se respeta siempre. Si viene 0 u omitido, se toma el del catálogo, salvo en orígenes estrictos (marketplace, cambio, presupuesto, garantía), donde queda en 0 |
| `offer` | integer |  | Descuento de la línea en **%** entero (fuera de 0-100 se toma 0). Si viene 0 y el origen no es estricto, se aplica la oferta vigente del catálogo |
| `quantity` | integer |  | Unidades (se toma el valor absoluto; default 1) |

### `OrderCreateMarketplace`

Identifica el canal de origen y la orden en ese canal. Su presencia activa la **deduplicación**: si ya existe una orden con el mismo `code` + `order_id`, se devuelve **409** con el `orderID` de la orden previa en vez de crear un duplicado.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ID del marketplace en esta tienda. Si viene, gana sobre `code`. Un `code` numérico también se toma como ID |
| `code` | string |  | Código del marketplace/canal, tal como está dado de alta en la tienda receptora. ej.: `0C39EA2B`. ⚠ Un código desconocido no se rechaza: la orden se crea sin marketplace y **sin deduplicar** (BUG-1140) |
| `order_id` | integer |  | ID de la orden en el sistema de origen. Se guarda como `source_ref_id` y es la clave de deduplicación. Si viene el bloque `marketplace` sin `order_id`, `source_ref_id` queda vacío aunque se haya mandado `order.source_ref_id`. |
| `customer_id` | integer |  | ID del cliente en el sistema de origen |
| `username` | string |  | Usuario del comprador en el canal |
| `buyer_username` | string |  | Nombre del comprador en el canal (`marketplace_buyer_name`) |
| `token` | string |  | Token del vínculo entre tiendas. ⚠ **Hoy no se valida** (BUG-459): la única autenticación es el Bearer. |

### `OrderCreateOrder`

Condiciones comerciales de la orden.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `status` | string |  | Estado inicial como código API (`pending`, `approved`…; catálogo en `/api/pedidos/order_status_list`) o ID interno. ⚠ **No se valida**: un código desconocido crea la orden en un estado inexistente (`0`) (BUG-1139). Si el marketplace tiene *estado inicial* configurado, éste se ignora (y en MercadoLibre se pisa con aprobado o finalizado). |
| `currency` | string |  | Moneda del payload. Si difiere de la moneda de la tienda y hay tabla de conversión, los importes se convierten. ej.: `ARS` |
| `quantity` | integer |  | Cantidad total informada por el origen. Sólo se guarda como control cuando viene `marketplace`; la tienda recalcula la suya |
| `units` | integer |  | **Ignorado** |
| `credit` | number |  | Crédito aplicado a la orden |
| `cupon_code` | string |  | Código de cupón. Si existe y está habilitado en la tienda receptora, se aplica el descuento |
| `one_quote_discount` | number |  | Porcentaje de descuento por pago en una cuota |
| `one_quote_price_min` | number |  | Piso de monto para que aplique `one_quote_discount`. Viaja junto al porcentaje: sin él, la tienda receptora aplicaría un descuento que el origen no prometió |
| `without_interest` | integer |  | Cuotas sin interés |
| `without_interest_ratio` | number |  | Coeficiente de las cuotas sin interés |
| `financial_factor` | number |  | Coeficiente financiero aplicado |
| `notes` | string |  | Observaciones del comprador. Se guardan con las etiquetas HTML removidas |
| `admin_notes` | string |  | ⚠ Notas internas. **Reemplaza** las notas automáticas del alta, no se suma a ellas. Se guarda tal cual, **HTML incluido**: mandar texto plano (BUG-1142) |
| `source` | integer |  | Origen de la venta, **ID interno** (`SRC_*`; ver `attribution.source_id`). Si viene el bloque `marketplace`, se pisa. Con origen cambio, presupuesto, garantía o marketplace, los precios y ofertas enviados se respetan aunque vengan en 0 (no se completan desde el catálogo). |
| `source_ref_id` | string |  | Referencia externa. Si viene `marketplace.order_id`, ese gana |

### `OrderCreatePayment`

Un pago ya registrado en el sistema de origen.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `date` | string |  | Fecha del pago en formato API |
| `app` | string |  | Aplicación de pago, como código API. ej.: `mercadopago` |
| `id` | integer |  | Forma de pago, ID interno. Se usa si no viene `app` |
| `method` | string |  | Método (`mercadopago`…), se usa si la forma de pago quedó "a convenir" |
| `paid` | — |  | Booleano o entero: si el pago está acreditado. Si falta, `status = approved` marca la orden como pagada. |
| `status` | string |  | Estado del pago. ej.: `approved`. Sólo se procesa el primer pago que lo trae |
| `amount` | number |  | Monto pagado |
| `factor` | number |  | Coeficiente financiero del pago |
| `monto_pedido` | number |  | Monto sin financiar. Si falta, se deriva de `amount / factor` |
| `installments` | integer |  | Cuotas |
| `external_reference` | string |  | Referencia externa del pago |
| `reference_id` | string |  |  |
| `authorization_code` | string |  |  |
| `card` | object |  |  |
| `currency` | string |  | **Ignorado**: los montos se convierten con `order.currency`. |
| `identification` | string |  | Documento del pagador |
| `name` | string |  | Nombre del pagador |
| `mail` | string |  | Email del pagador |
| `phone` | string |  | Teléfono del pagador |
| `notes` | string |  | Observaciones del pago |

### `OrderCreatePickupStore`

Sucursal **propia de la tienda** donde el comprador retira (retiro en local). **Se manda el CÓDIGO, nunca el id.** El id de sucursal es un auto-incremental local: la misma sucursal física tiene ids distintos en cada tienda. La tienda receptora resuelve el código contra sus propias sucursales y guarda **su** id. El código cumple para la sucursal el mismo rol que el SKU/EAN para el artículo, y debe ser idéntico en todas las tiendas que comparten esa sucursal. **Si el código no existe en la tienda receptora, la orden se crea igual pero sin sucursal asignada**, y queda una nota en el pedido con el `code`, el `name` y el `address` recibidos, para que el operador pueda cargar el código que falta. No es un error y no aborta el alta. Omitir el bloque entero cuando el envío no es retiro en local.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | string |  | **Código portable** de la sucursal, tal como está cargado en la tienda receptora. Se compara en mayúsculas y sin espacios. ej.: `SUC-RECOLETA` |
| `name` | string |  | Nombre de la sucursal en el origen. Sólo se usa para la nota cuando el código no resuelve. ej.: `Sucursal Recoleta` |
| `address` | string |  | Dirección de la sucursal en el origen. Sólo se usa para la nota cuando el código no resuelve. ej.: `Av. Callao 1200` |

### `OrderCreateRequest`

Payload de alta de una orden. **`items` es el único bloque obligatorio**; el resto se completa con los defaults de la tienda si falta. Sin `client` (ni `customer`), la orden se crea con datos de contacto de relleno (apellido *NubixStore*, mail del bot, CP 1640): mandar siempre `client`. **No se envían totales.** La tienda recompone `monto`, `monto_descuento`, `descuento`, `cantidad` y `unidades` a partir de `items[]` + `shipping.cost` − `order.credit`. Mandar un total no tiene efecto.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `marketplace` | [`OrderCreateMarketplace`](#ordercreatemarketplace) |  |  |
| `client` | [`OrderCreateClient`](#ordercreateclient) |  |  |
| `customer` | object |  | Cliente **registrado en esta tienda**: `id` (ID de usuario) o `code` (código de cliente). Si se encuentra, sus datos (nombre, mail, teléfono, DNI, facturación, dirección y perfil) **reemplazan por completo** el bloque `client`. Si el marketplace tiene *cliente por defecto* (`client_code_default`) y no se manda `customer`, la tienda usa ese cliente y descarta los datos del comprador recibidos. |
| `seller` | object |  | Vendedor de la venta: marca el pedido como venta de vendedor (`is_seller_buy`). |
| `order` | [`OrderCreateOrder`](#ordercreateorder) |  |  |
| `shipping` | [`OrderCreateShipping`](#ordercreateshipping) |  |  |
| `payment` | array de [`OrderCreatePayment`](#ordercreatepayment) |  | Pagos ya registrados en el origen. **Es la única forma de dar la orden por pagada en el alta**: sin este bloque la orden se crea impaga y sin registro de pago. Sólo se procesa **el primer elemento que trae `status`**; si hay más, sólo se agrega la nota "Abonó con #N tarjetas" (el resto no se registra). También se acepta un objeto único con `method` (formato anterior, deprecado). |
| `billing` | [`OrderCreateBilling`](#ordercreatebilling) |  |  |
| `items` | array de [`OrderCreateItem`](#ordercreateitem) | sí | **Obligatorio.** Los ítems se enriquecen con los datos del catálogo local buscando por `code`. Si el código no existe en la tienda receptora **el ítem se crea igual**, sin vincular a un artículo. |

### `OrderCreateResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | 0 = éxito |
| `msg` | string |  | ej.: `Orden #A-1045 creada correctamente.` |
| `orderID` | integer |  | ID de la orden creada en esta tienda. En un 409: el ID de la orden previa (duplicado), ausente (validación) o `null` (error al guardar). |
| `fullOrderID` | string |  | ID con el prefijo de órdenes de la tienda |
| `httpCode` | integer |  | Repite el status HTTP de la respuesta |
| `referer` | string |  |  |
| `IP` | string |  |  |

### `OrderCreateShipping`

Datos de envío de la orden. Si viene el bloque, `method`, `is_free` y `cost` se leen sin control de presencia: son obligatorios de hecho. 🔴 **`branch` y `pickup_store` NO son lo mismo.** `branch` es la sucursal del **transportista** (OCA, Correo Argentino, Andreani); `pickup_store` es la sucursal **propia de la tienda** donde el comprador retira. Mandar una dentro de la otra hace que la tienda receptora registre el retiro como sucursal de correo.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `method` | integer | sí | Método de envío, como **ID interno**. Acepta también el código API. La forma de envío (`way`) se recalcula a partir de éste |
| `way` | integer |  | Forma de envío como ID interno. **Informativo**: la tienda lo recalcula desde `method` |
| `is_free` | boolean | sí | Envío sin cargo |
| `cost` | number | sí | Costo del envío. Si `is_free` es `true`, el costo cobrado queda en 0 y éste se conserva como costo real |
| `tracking_number` | string |  | Número de seguimiento, si ya existe |
| `terminal_name` | string |  | Nombre de la terminal/punto de entrega |
| `terminal_company` | string |  | Empresa de la terminal |
| `address` | [`OrderCreateAddress`](#ordercreateaddress) |  |  |
| `branch` | [`OrderCreateBranch`](#ordercreatebranch) |  |  |
| `pickup_store` | [`OrderCreatePickupStore`](#ordercreatepickupstore) |  |  |

### `OrderDetailResponse`

Detalle de una orden. Sólo se documentan acá los bloques con contrato estable; el resto del objeto se devuelve tal cual lo arma la tienda.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `httpCode` | integer |  | ej.: `200` |
| `order` | object |  | Pedido serializado. Bloques: `order` (cabecera: id, fechas, estado como código API, importes como string, `marketplace{id,code,name,detail}`, `updated_at`, `discount_total`), `attribution`, `discounts`, `loyalty`, `links` (pago, estado, seguimiento, factura), `client`, `clientType`, `seller`, `shipping`, `address`, `payment`, `billing`, `items` (ausente si el pedido no tiene ítems). Fuera de producción se agrega `check` (autocontrol de totales). Sólo `shipping` tiene schema propio. |

### `OrderDetailShipping`

Datos de envío de la orden. **`type` discrimina el tipo de envío y determina cuál de los dos objetos de sucursal está completo**: `branch` para sucursal del transportista, `pickup_store` para sucursal propia de la tienda.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `type` | string (`home_delivery` · `branch_delivery` · `pickupstore` · `unknown`) |  | Tipo de envío resuelto |
| `is_home_delivery` | boolean |  | Entrega a domicilio |
| `is_branch_delivery` | boolean |  | Retiro en sucursal del **transportista** → mirar `branch` |
| `is_pickup_store` | boolean |  | Retiro en sucursal **propia de la tienda** → mirar `pickup_store` |
| `tracking_number` | string |  | Número de seguimiento |
| `is_free` | boolean |  | Envío sin cargo |
| `cost` | number |  | Costo del envío |
| `id` | integer |  | Forma de envío (ID interno) |
| `method` | integer |  | Método de envío (ID interno) |
| `terminal_name` | string |  | Nombre de la terminal/punto de entrega |
| `terminal_company` | string |  | Empresa de la terminal |
| `zone_id` | integer |  | Zona de envío |
| `branch` | [`OrderShippingBranch`](#ordershippingbranch) |  |  |
| `pickup_store` | [`OrderShippingPickupStore`](#ordershippingpickupstore) |  |  |

### `OrderShippingBranch`

Sucursal del **transportista** (OCA, Correo Argentino, Andreani…) donde el comprador retira el paquete. Se completa cuando `shipping.is_branch_delivery` es `true`. **No confundir con `pickup_store`**, que es la sucursal propia de la tienda. ⚠ Cuando el envío es **retiro en local** (`is_pickup_store`), este objeto se completa igual pero su `code` trae el **id interno** de la sucursal, que es local a cada tienda y no sirve para correlacionar. Para eso está `pickup_store.code`. Se mantiene así por compatibilidad con los consumidores que ya lo leen.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | string |  | Código de la sucursal del correo |
| `address` | string |  | Dirección de la sucursal |
| `opening_hours` | string |  | Horarios de atención |
| `phone` | string |  | Teléfono de la sucursal |
| `notes` | string |  | Notas para el comprador |

### `OrderShippingPickupStore`

Sucursal **propia de la tienda** donde el comprador retira (retiro en local). Se completa cuando `shipping.is_pickup_store` es `true` y el pedido tiene una sucursal asignada. **`code` es el identificador portable entre tiendas** — cumple para la sucursal el mismo rol que el SKU/EAN para el artículo. Es el único valor de este objeto que sirve para correlacionar: `name` y `address` son informativos y pueden cambiar sin aviso. Sale de la columna `codigo` de la sucursal, que el operador carga a mano y **debe ser idéntica en todas las tiendas que comparten esa sucursal**. Viene en `null` si el pedido no es de retiro en local, si no tiene sucursal asignada, o si la sucursal todavía no tiene código cargado.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | string |  | Código portable de la sucursal. Mayúsculas, sin espacios, único dentro de la tienda |
| `name` | string |  | Nombre de la sucursal. Informativo |
| `address` | string |  | Dirección de la sucursal. Informativo |
| `opening_hours` | string |  | Horarios de atención |
| `phone` | string |  | Teléfono de la sucursal |
| `notes` | string |  | Notas de retiro que ve el comprador |

### `OrderUpdateRequest`

Cambios a aplicar sobre un pedido existente. **Todos los campos son opcionales**; se aplica sólo lo que viene. Los campos que no figuran acá se ignoran. En particular se ignoran `payment`, `label`, `zlabel` y `modified_by`, que algunos emisores nubixstore mandan igual. Lo que manda la tarea `UPDATE-ORDERS` al origen es un subconjunto: `status`, `paid`, `paymentMethod` y `tracking_number`.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `status` | — |  | Estado nuevo. **Numérico = ID interno; texto = código API** de `/api/pedidos/order_status_list` ( `pending`, `approved`, `prepared`, `readytodispatch`, `dispatched`, `delivered`, `cancelled`, `finalized`, `custom1`… ). `deleted` se rechaza: usar `DELETE`. Si el pedido ya está en ese estado, no es error. ⚠ Un código inexistente o una transición no permitida **tampoco** dan error: responden `status` 0 y el estado no cambia (ver `msg`; BUG-1141). Los IDs son **los mismos en todas las tiendas** ( constantes del sistema ): 1 `pending` · 2 `cancelled` · 3 `finalized` · 9 `prepared` · 10 `approved` · 11 `rejected` · 12 `readytopack` · 13 `readytodispatch` · 14 `dispatched` · 36 `delivered` · 68 `preapproved` · `custom1`…`custom25` = 15, 16, 38, 39, 41, 43, 44, 45, 46, 47, 53, 54…67 · `custompreinitial` 49 · `custompredeposit` 50 · `custompredispatch` 51 · `customprefinished` 52. Lo que cambia por tienda es **qué estados están habilitados y cómo se llaman**: eso devuelve `order_status_list`, como mapa código → nombre ( **sin IDs** ). **Lo que manda nubixstore es siempre el código API**, también la tarea `UPDATE-ORDERS` en las versiones posteriores a 13.10.1. Una tienda en 13.10.1 o anterior, en `UPDATE-ORDERS`, todavía manda el ID numérico: quien reciba de tiendas de versiones distintas tiene que aceptar las dos formas. |
| `paid` | integer |  | Estado de pago. 0 = a cobrar · 1 = pagado. Con pagos avanzados habilitados en la tienda, además 2 = consignación · 3 = bonificado · 4 = cuenta corriente. Un valor fuera de esa lista se ignora. El primer aviso de pago dispara los mismos efectos que un pago registrado en la tienda ( mail, cambio de estado si corresponde ). ⚠ La generación automática de guía al recibir el pago está invertida (BUG-301). |
| `paymentMethod` | integer |  | Forma de pago, **ID interno** de la tienda. Si difiere de la actual se reemplaza y queda nota del cambio. |
| `cardFactor` | number |  | Coeficiente financiero del pago con tarjeta. Se aplica sólo junto con el primer aviso de pago. |
| `tracking_number` | string |  | Número de seguimiento del envío. **Sólo se guarda si el pedido todavía no tiene uno**: no pisa una guía existente. En pedidos de MercadoLibre se guarda como ID de envío de ML, no como guía. |
| `notes` | string |  | Se antepone a las notas internas del pedido tal cual, **HTML incluido**: mandar texto plano (BUG-1142). |
| `marketplace` | object |  | Alternativa al `{id}` de la ruta: si vienen `code` y `order_id`, el pedido se busca por esa pareja ( la misma de la deduplicación del alta ) y el `{id}` se ignora. |

### `OrdersListResponse`

Listado paginado de `/api/pedidos/orders`.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | Sin resultados: *"No se encontraron órdenes para la búsqueda solicitada."* |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `pagination` | object |  |  |
| `orders` | array de object |  | Cada elemento tiene la forma de `order` en `GET /api/pedidos/order/{id}`. Ver `minify`. ⚠ Los importes de la lista no coinciden con los del detalle (BUG-1134). |

### `PaymentConditionItem`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | string |  | ID de la condición |
| `enabled` | string (`1`) |  | Siempre 1: las deshabilitadas no se listan |
| `code` | string |  | Código interno |
| `name` | string |  | Nombre de la condición |
| `display_name` | string |  | Nombre para mostrar al cliente |
| `notes` | string |  | Notas adicionales |

### `PaymentConditionsResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado de condiciones de pago` |
| `list` | object |  | Mapa de condiciones de pago indexado por ID |

### `PaymentCreateRequest`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `payment` | object | sí | Ningún campo es obligatorio para el endpoint: lo que falte toma el default indicado. |

### `PaymentDetailResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Referencia de pago ID #123 encontrada` |
| `payment` | [`PaymentSerialized`](#paymentserialized) |  |  |

### `PaymentSerialized`

Pago serializado por PagosController::_serializePayment(). La lista de columnas que NO salen es negra: una columna nueva de `pagos` viaja cruda hasta que alguien la saque.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  |  |
| `order_id` | integer |  |  |
| `reference_id` | string |  | Referencia del pago en el gateway |
| `external_reference` | string |  |  |
| `amount` | number |  | Monto cobrado; puede incluir costo financiero |
| `monto_pedido` | number |  | Monto sin financiar |
| `monto_parcial` | number |  |  |
| `installments` | integer |  |  |
| `installments_amount` | number |  |  |
| `factor` | number |  | Coeficiente financiero |
| `date` | string |  | Alta del pago, hora argentina, sin zona |
| `app` | string |  | Forma de pago (código API). tobeagreed si no se reconoce |
| `app_id` | integer |  | ID interno de la forma de pago |
| `method` | string |  | Marca/medio. Atención: vale el literal `emptytext` cuando está vacío (BUG-1157) |
| `status` | string (`approved` · `pending` · `cancelled` · `rejected` · `refunded` · `unknown`) |  |  |
| `currency` | string |  | ej.: `ARS` |
| `channel` | string |  | Variante del gateway. MercadoPago: pro o api |
| `name` | string |  |  |
| `mail` | string |  |  |
| `phone` | string |  |  |
| `identification` | string |  |  |
| `notes` | string |  |  |
| `authorization_code` | string |  |  |
| `attempts` | integer |  |  |
| `sources` | integer |  |  |
| `source_count` | integer |  |  |
| `pedido_mail` | string |  |  |
| `card_history` | string |  |  |
| `status_detail` | string |  |  |
| `card` | object |  |  |

### `PaymentsListResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado generado correctamente` |
| `payments` | array de [`PaymentSerialized`](#paymentserialized) |  | Array de pagos serializados |
| `pagination` | object |  |  |

### `PedidosStatusInfoResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Se encontraron 8 estados habilitados.` |
| `list` | object |  | Información extendida por estado (clave = ID interno). No incluye conteos de pedidos. |

### `PedidosStatusListResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Se encontraron 8 estados habilitados.` |
| `list` | object |  | Mapa de estados habilitados: **ID interno** (en `statusList`) o **código API** (en `order_status_list`) → nombre del estado en MAYÚSCULAS, tal como lo nombró la tienda. |

### `PointsLoadRequest`

Request para cargar puntos a un cliente.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `type` | integer (`1` · `2` · `3` · `4` · `5` · `6` · `7`) | sí | Origen: 1 compra · 2 review · 3 manual · 4 encuesta · 5 consumo · 6 registro · 7 recuperados. Otro valor = no se carga nada. |
| `points` | integer | sí | Puntos a sumar; negativo para descontar. 0 = no se carga nada. |
| `notes` | string |  | Opcional. No usar comas ni punto y coma (rompen el historial). |

### `ProvincesInfoResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `provinces` | array de object |  | Provincias habilitadas con información extendida para mapeo entre sistemas |

### `ProvincesResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `provinces` | object |  | Mapa province_id → nombre de la provincia, en mayúscula inicial (tabla provincias, sólo habilitadas) |

### `ReturnsListResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `list` | object |  | Mapa de devoluciones indexado por ID del ticket |
| `info` | object |  | Información complementaria (null si no hay resultados) |
| `pagination` | object |  |  |

### `SalesStatsResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `stats` | object |  |  |
| `dimension_names` | object |  | `marketplaces`, `channels`, `provinces`: id → nombre; `payments`: id → código API |
| `breakdown` | object |  | Sólo con `include=breakdown`: `top_items` (articulo_id → {description, code, brand_id, category_id, orders, units, billing}) y `top_brands` (marca_id → {description, orders, units, billing}) |
| `filters_applied` | object |  | Eco de los parámetros recibidos (no necesariamente aplicados: ver `to`) |

### `SellersListResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Ejecutivos encontrados.` |
| `sellers` | object |  | Mapa user_id → datos serializados del vendedor |

### `ShippingMethodsResponse`

Respuesta con los métodos de envío habilitados.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado de metodos de envio full.` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `list` | object |  | Mapa method_id → "{nombre de la logística} / {nombre del método}". Para las logísticas propias (10, 13) y "a convenir" (8) el segundo término es el nombre de la logística. Sólo métodos habilitados |

### `StockByDepositsRequest`

Request POST para consultar stock desglosado por depósito.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `codes` | array de string | sí | Array de SKUs o EANs a consultar |

### `StockByDepositsResponse`

Respuesta de stock desglosado por depósito. Los ítems se indexan por código y cada depósito por su code.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado de stocks por deposito` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `items` | object |  | Mapa código → { deposit_code: stock, ... }. La clave es el EAN (`barcode`) del ítem si lo tiene y, si no, su SKU: no necesariamente el código pedido. Sin resultados llega como array vacío `[]` |
| `pagination` | object |  |  |

### `StockItem`

Fila de stock. **Cada variante es una fila propia**, no un sub-array: el endpoint aplana las opciones para que cada código consultable tenga su renglón de stock. Un artículo con 3 variantes devuelve 3 filas; un artículo sin variantes devuelve 1. Los tres códigos (`sku`, `barcode`, `skuf`) son **los de la fila**: en filas de variante son los de la opción, no los del artículo padre. Acá los tres vienen **`null`** cuando no están cargados — a diferencia de `GET /api/articulos/item` e `/items`, donde a nivel ítem `barcode` y `skuf` vienen como cadena vacía `""`.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | — |  | Artículo sin variantes: el ID numérico del artículo (ej: `7190`). Fila de variante: el string `"{articuloID}-{opcionID}"` (ej: `"60-2195"`), que identifica la opción exacta. |
| `sku` | string |  | SKU interno. `null` si el artículo/opción no lo tiene cargado. |
| `barcode` | string |  | Código de barras (EAN). `null` si no está cargado. |
| `skuf` | string |  | SKU del fabricante (SKU-F). `null` si no está cargado. |
| `name` | string |  | Nombre del artículo. En filas de variante incluye el nombre de la variante concatenado. |
| `brand` | object |  | Marca del artículo. |
| `stock` | integer |  | Stock minorista. |
| `wholesale_stock` | integer |  | Stock mayorista (distribución). |
| `committed_stock` | integer |  | Stock comprometido (reservado por pedidos sin despachar). |

### `StockListResponse`

Respuesta paginada de stock. `items` es un **mapa indexado por código**, no un array: la clave es el SKU del artículo/opción o, si no tiene SKU, su código de barras (EAN). Está pensado para consultar stock por código sin recorrer una lista.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Ítems encontrados.` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `pagination` | object |  |  |
| `items` | object |  | Mapa `clave = SKU (o EAN si no hay SKU) → valor = fila de stock`. **Los artículos y opciones sin SKU y sin EAN no se incluyen**: sin código no hay clave posible, y este endpoint existe para consultar stock por código. Si se necesita el catálogo completo, incluidos los artículos sin código, usar `/api/articulos/items`. |

### `SuppliersListResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Proveedores encontrados` |
| `suppliers` | object |  | Mapa user_id → datos serializados del proveedor |

### `VerifiedConditionsResponse`

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  |  |
| `conditions` | object |  |  |

### `ZoneItem`

Zona de envío del sistema.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `id` | integer |  | ej.: `1` |
| `name` | string |  | ej.: `AMBA` |
| `zip_codes` | string |  | Códigos postales separados por coma (se toleran espacios) |

### `ZonesResponse`

Respuesta de listado de zonas de envío.

| Campo | Tipo | Oblig. | Descripción |
|---|---|---|---|
| `code` | integer |  | ej.: `900` |
| `status` | integer |  | ej.: `0` |
| `msg` | string |  | ej.: `Listado de zonas` |
| `referer` | string |  |  |
| `IP` | string |  |  |
| `zones` | object |  | Mapa zone_id → zona, ordenado por nombre. Sin zonas llega como array vacío `[]` |

