# Manual API NubixStore
**Versión:** 2.1 — **Fecha:** 2026-04-23
**Responsable de mantenimiento:** Agente `api-nubixstore`

> Este manual es la referencia completa de la API de **nubixstore**. Está pensado para que los agentes y desarrolladores integren o consuman endpoints sin necesidad de leer el código fuente. Mantenerlo actualizado es responsabilidad del agente `api-nubixstore`.
>
> **En nsSkeleton:** manual portado desde nsCentral; es la fuente de verdad del skill `nubixstore-api`. El driver que lo implementa en el stack por defecto (PHP MVC) es `App\Services\Ecommerce\NubixstoreConnector` (su constante `API_MANUAL_VERSION` debe coincidir con la **Versión** de arriba). El conector genérico multi-plataforma se documenta en [`../ecommerce/ecommerce-apis.md`](../ecommerce/ecommerce-apis.md).

---

## 1. Información General

| Parámetro | Valor |
|-----------|-------|
| **Framework** | CakePHP 2 + PHP 8.2 |
| **Base URL local** | `http://localhost/nubixstore/api` |
| **Protocolo** | HTTP/HTTPS |
| **Formato de request** | Query string (GET) / JSON body (POST, PUT) |
| **Formato de response** | JSON |
| **Charset** | UTF-8 |

### Convención de rutas

```
/api/{controlador_sin_controller}/{funcion_sin_api_}
```

**Ejemplos:**
- `ArticulosController::api_items()` → `GET /api/articulos/items`
- `PedidosController::api_order($id)` → `GET /api/pedidos/order/123`

---

## 2. Autenticación

### 2.1 Bearer Token

Todos los endpoints `api_*` requieren autenticación mediante **Bearer Token** en el header HTTP:

```http
Authorization: Bearer {TOKEN}
```

### 2.2 Obtención del token — Endpoint de Login

El token se obtiene mediante el endpoint de login:

#### `POST /usuarios/token`

Autentica un consumidor de la API y retorna un `access_token` válido.

**Body JSON:**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `USER` | string | Sí | Login del usuario API |
| `PASSWORD` | string | Sí | Contraseña en texto plano (se hashea con `md5` internamente) |
| `API_KEY` | string | Sí | API Key del entorno. Obligatorio para autenticarse con la API |

**Ejemplo de request:**
```json
{
  "USER": "mi_usuario",
  "PASSWORD": "mi_contraseña",
  "API_KEY": "472c785591bb06838810b641f71970ea"
}
```

**Respuesta exitosa:**
```json
{
  "code": 200,
  "status": "EXITO",
  "msg": "Credenciales de acceso válidas. Bienvenid@ mi_usuario !",
  "access_token": "abc123def456...",
  "expires_in": 1745539200
}
```

**Respuesta de error:**
```json
{
  "code": 403,
  "status": "ERROR",
  "msg": "Credenciales de acceso incorrectas."
}
```

**Errores posibles:**

| Situación | Mensaje |
|-----------|---------|
| Usuario no encontrado | `Credenciales de acceso incorrectas.` |
| Contraseña incorrecta | `Contraseña incorrecta.` |
| Usuario deshabilitado | `Credenciales de acceso deshabilitadas.` |
| API Key incorrecta | `API Key incorrecta.` |

**Notas:**
- La ruta es `/usuarios/token` (sin prefix `api`), no requiere Bearer Token previo.
- Los tres campos (`USER`, `PASSWORD`, `API_KEY`) son obligatorios para obtener el token.
- El `access_token` retornado debe usarse como Bearer Token en los endpoints `api_*`.
- El campo `expires_in` es un timestamp UNIX que indica la expiración del token.

### 2.3 Credenciales de entorno local

| Campo | Valor |
|-------|-------|
| **API Key** | `472c785591bb06838810b641f71970ea` |
| **URL Base** | `http://localhost/nubixstore/api` |
| **URL Login** | `http://localhost/nubixstore/usuarios/token` |

> En nsSkeleton las credenciales NO se hardcodean: se guardan cifradas en `settings`
> (grupo `ecommerce`, AES-256-GCM) y las lee `StoreConnectorFactory::fromSettings()`.
> Para multi-tienda, pasalas explícitas a `StoreConnectorFactory::make('nubixstore', [...])`.

---

## 3. Formato de Respuestas

### Respuesta exitosa

```json
{
  "code": 200,
  "status": "EXITO",
  "msg": "Operación realizada correctamente",
  "campo_dato": { }
}
```

### Respuesta de error

```json
{
  "code": 400,
  "status": "ERROR",
  "msg": "Descripción del error"
}
```

### Códigos de estado HTTP

| Código | Significado |
|--------|-------------|
| `200` | OK — operación exitosa |
| `201` | Created — recurso creado |
| `400` | Bad Request — parámetros inválidos o faltantes |
| `401` | Unauthorized — token inválido o ausente |
| `404` | Not Found — recurso no encontrado |
| `409` | Conflict — dato duplicado o condición inválida |
| `500` | Internal Server Error |

### Paginación estándar

Los endpoints de listado incluyen paginación con los parámetros:

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Página actual (default: `1`) |
| `limit` | int | Registros por página (default varía por endpoint, máx. definido por `API_HARD_LIMIT_RECORDS`) |

Respuesta de paginación:
```json
{
  "pagination": {
    "limit": 50,
    "page": 1,
    "offset": 0,
    "results": 23
  }
}
```

---

## 4. Librería REST Interna

**Ubicación:** `app/Vendor/NubixStoreRest/rest.php`

La librería `NubixStoreRest` es el cliente HTTP interno usado por los `API*Component` para consumir la propia API.

```php
// Uso básico
$this->rest->get('articulos/items', ['brandId' => 5]);
$this->rest->post('pedidos/order/123', $bodyArray);
$this->rest->put('usuarios/client_updates', $bodyArray);
$this->rest->delete('cupones/del', ['id' => 10]);
```

**Características:**
- Soporte para `GET`, `POST`, `PUT`, `PATCH`, `DELETE`
- Ejecución asíncrona
- Soporte multipart/form-data
- Headers automáticos (Authorization, Content-Type)
- Logging integrado

---

## 5. Endpoints por Módulo

---

### 5.1 Artículos
**Controlador:** `app/Controller/ArticulosController.php`
**Componente:** `APIArticuloComponent`

---

#### `GET /api/articulos/categories`
Lista categorías habilitadas del catálogo.

**Parámetros de query:**
| Parámetro | Tipo | Obligatorio | Descripción |
|-----------|------|-------------|-------------|
| `id` | int | No | Filtra por `parent_id` de categoría |
| `only_leaf` | int (0/1) | No | Si es `1`, retorna solo categorías hoja |

**Respuesta:**
```json
{
  "code": 200,
  "status": "EXITO",
  "msg": "Listado de categorias listado correctamente.",
  "categories": [
    { "id": 1, "parent_id": null, "nivel": 1, "nombre": "Electrónica", "habilitada": 1, "leaf": 0, "slug": "electronica" }
  ]
}
```

---

#### `GET /api/articulos/item`
Obtiene un artículo por ID, opción ID, SKU o código de barras.

**Parámetros de query:**
| Parámetro | Tipo | Obligatorio | Descripción |
|-----------|------|-------------|-------------|
| `id` | int | No* | ID del artículo |
| `oid` | int | No* | ID de la opción/variante |
| `sku` / `code` | string | No* | SKU o código del artículo |
| `variants` | int (0/1) | No | Incluye variantes en la respuesta |
| `html` | int (0/1) | No | Incluye descripción HTML |
| `client_type` | int | No | ID de tipo de cliente para ajuste de precios |
| `in_stock` | int (0/1) | No | Filtra variantes en stock |
| `mercadolibre` | string (true/false) | No | Incluye datos ML |

> *Al menos uno de `id`, `oid`, `sku` o `code` es requerido.

**Respuesta:**
```json
{
  "code": 200,
  "status": "EXITO",
  "msg": "Ítem encontrado.",
  "item": {
    "id": 123, "name": "Producto X", "enabled": 1,
    "price": 1500.00, "deal_price": null, "off": 0,
    "barcode": "7791234567890", "sku": "ABC123",
    "stock": 10, "currency": "ARS",
    "brand": { "id": 5, "code": "SONY", "name": "Sony" },
    "pictures": [{ "pic": "https://...", "thumb": "https://..." }],
    "variants": []
  }
}
```

---

#### `GET /api/articulos/items`
Lista artículos del catálogo con paginación y filtros.

**Parámetros de query:**
| Parámetro | Tipo | Obligatorio | Descripción |
|-----------|------|-------------|-------------|
| `id` | int/CSV | No | IDs de artículos (separados por coma) |
| `sku` / `codes` | string/CSV | No | SKUs o códigos (separados por coma) |
| `categoryId` | int | No | Filtra por categoría |
| `brandId` | int/CSV | No | Filtra por ID de marca |
| `brandCode` | string/CSV | No | Filtra por código de marca |
| `filter` | string | No | Búsqueda por nombre, código o SKU |
| `page` | int | No | Página (default: 1) |
| `limit` | int | No | Registros por página |
| `compact` | bool | No | Respuesta compacta (default: true) |
| `description` | bool | No | Incluye descripción HTML (default: false) |
| `variants` | bool | No | Incluye variantes (default: true) |
| `mercadolibre` | bool | No | Incluye datos ML (default: false) |
| `client_type` | int | No | Tipo de cliente para ajuste de precios |

**Respuesta:**
```json
{
  "code": 200,
  "status": "EXITO",
  "items": [ { "id": 1, "name": "...", "price": 1500.00 } ],
  "pagination": { "limit": 50, "page": 1, "offset": 0, "results": 10 }
}
```

---

#### `GET /api/articulos/stock`
Consulta stock actual de artículos.

---

#### `GET /api/articulos/combo_details`
Retorna los componentes y proporciones de un combo o pack.

---

#### `POST /api/articulos/itemDiscountStock`
Descuenta stock de un artículo específico.

---

#### `GET|POST /api/articulos/sync`
Sincroniza artículos con sistemas externos.

---

#### `GET /api/articulos/new_codes/:lastDays`
Lista artículos con códigos creados en los últimos N días (default: 1 día = últimas 24hs).

---

#### `GET /api/articulos/change_codes/:lastDays`
Lista artículos con códigos modificados en los últimos N días (default: 1 día).

---

#### `GET /api/articulos/contabilium`
Exporta el catálogo en formato compatible con Contabilium.

---

### 5.2 Billing / Facturación
**Controlador:** `app/Controller/BillingController.php`
**Componente:** `APIBillingComponent`

---

#### `POST /api/billing/facturar`
Genera factura electrónica para una orden.

**Body JSON:**
```json
{ "order_id": 123 }
```

---

#### `GET /api/billing/comprobante/:wid`
Obtiene datos de un comprobante de facturación por su ID interno (`wid`).

---

#### `GET /api/billing/search`
Busca comprobantes de facturación con filtros.

---

### 5.3 Blog
**Controlador:** `app/Controller/BlogController.php`
**Componente:** `APIBlogComponent`

---

#### `GET /api/blog/categories`
Lista las categorías del blog habilitadas.

---

#### `GET /api/blog/posts`
Lista posts del blog con paginación.

---

### 5.4 Campañas
**Controlador:** `app/Controller/CampaingsController.php`
**Componente:** `APICampaignComponent`

---

#### `GET /api/campaings/list`
Lista las campañas activas disponibles.

---

### 5.5 Cupones
**Controlador:** `app/Controller/CuponesController.php`
**Componente:** `APICuponComponent`

---

#### `GET /api/cupones/status`
Consulta el estado y validez de un cupón.

**Parámetros de query:**
| Parámetro | Tipo | Obligatorio | Descripción |
|-----------|------|-------------|-------------|
| `code` | string | Sí | Código del cupón a verificar |

---

#### `DELETE|POST /api/cupones/del`
Invalida o elimina un cupón.

---

### 5.6 Depósitos
**Controlador:** `app/Controller/DepositsController.php`
**Componente:** `APIDepositComponent`

---

#### `GET /api/deposits/deposits`
Lista todos los depósitos disponibles en el sistema.

---

#### `GET /api/deposits/stock_by_deposits`
Retorna el stock de artículos desagregado por depósito.

---

#### `POST /api/deposits/sync`
Sincroniza el stock entre depósitos.

---

#### `POST /api/deposits/force_code`
Fuerza la asignación de un código a un depósito.

---

#### `GET /api/deposits/sku/:sku`
*(En construcción)* Consulta información de un depósito por SKU de artículo.

---

### 5.7 Envíos
**Controlador:** `app/Controller/EnviosController.php`
**Componente:** `APIEnvioComponent`

---

#### `POST /api/envios/cotizar`
Cotiza el costo de un envío.

**Body JSON:**
```json
{
  "order": {
    "shipping_type": "OCASA"
  },
  "weight": 500,
  "volume": 0.001,
  "zip_code_origin": "1640",
  "zip_code_destination": "5000"
}
```

**Respuesta:**
```json
{
  "code": 200,
  "status": "EXITO",
  "amount": 1200
}
```

---

#### `GET /api/envios/getFullShippingMethods`
Lista todos los métodos de envío configurados en el sistema (nombre, tipo, código).

---

#### `POST /api/envios/generate`
Genera la etiqueta de envío para una orden.

**Body JSON:**
```json
{ "orderID": 123 }
```

**Respuesta:**
```json
{
  "status": "EXITO",
  "trackID": "ABC123456",
  "labelUrl": "https://logistics.com/label/ABC123456.pdf"
}
```

---

#### `GET /api/envios/zones`
Lista las zonas de envío configuradas con sus códigos postales.

**Respuesta:**
```json
{
  "zones": {
    "1": { "id": 1, "name": "AMBA", "zip_codes": "1000,1001,1002" }
  }
}
```

---

#### `GET /api/envios/cp_zones_map`
Retorna el mapa completo de código postal → zona para asignación automática.

---

### 5.8 Garantías
**Controlador:** `app/Controller/GarantiasController.php`
**Componente:** `APIGarantiaComponent`

---

#### `GET /api/garantias/typeList`
Lista los tipos de garantía disponibles en el sistema.

---

#### `GET /api/garantias/statusList`
Lista los estados posibles de una garantía.

---

#### `GET /api/garantias/brandResolutionList`
Lista las resoluciones que puede aplicar la marca sobre una garantía.

---

#### `GET /api/garantias/clientResolutionList`
Lista las resoluciones que puede solicitar el cliente sobre una garantía.

---

#### `GET /api/garantias/list`
Lista garantías registradas con filtros y paginación.

---

### 5.9 Inbox
**Controlador:** `app/Controller/InboxController.php`
**Componente:** `APIInboxComponent`

---

#### `GET /api/inbox/departmentsList`
Lista los departamentos disponibles para asignación de mensajes del inbox.

---

### 5.10 Marcas
**Controlador:** `app/Controller/MarcasController.php`
**Componente:** `APIMarcaComponent`

---

#### `GET /api/marcas/list`
Lista las marcas habilitadas en el catálogo.

**Respuesta:**
```json
{
  "list": {
    "1": { "id": 1, "name": "Sony", "code": "SONY", "enabled": 1 }
  }
}
```

---

### 5.11 Marketplaces
**Controlador:** `app/Controller/MarketplacesController.php`
**Componente:** `APIMarketplaceComponent`

---

#### `GET /api/marketplaces/list`
Lista los marketplaces configurados (MercadoLibre, etc.) con sus códigos internos.

---

### 5.12 Newsletters
**Controlador:** `app/Controller/NewslettersController.php`
**Componente:** `APINewsletterComponent`

---

#### `POST /api/newsletters/subscribe`
Suscribe un email al newsletter.

**Body JSON:**
```json
{ "mail": "cliente@ejemplo.com" }
```

---

#### `GET /api/newsletters/subscribers`
Lista los suscriptores activos al newsletter.

---

### 5.13 Pagos
**Controlador:** `app/Controller/PagosController.php`
**Componente:** `APIPagoComponent`

---

#### `GET /api/pagos/spsConfig`
Retorna la configuración activa de PayWay/Decidir (claves públicas/privadas, sandbox, tarjetas habilitadas, cuotas).

**Respuesta:**
```json
{
  "code": 200,
  "status": "EXITO",
  "msg": "Servicio PawWay ACTIVADO",
  "config": {
    "publicKey": "...",
    "privateKey": "...",
    "sandbox": true,
    "cards": { }
  }
}
```

---

#### `GET|PUT /api/pagos/payment/:id`
Obtiene (`GET`) o actualiza (`PUT`) información del pago asociado a una orden.

**Parámetros de ruta:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | int | ID de la orden |

---

#### `GET /api/pagos/mpSearchByExternalReference/:externalReference/:mpUser`
Busca un pago de MercadoPago por referencia externa.

---

#### `GET /api/pagos/payment_conditions`
Lista las condiciones de pago habilitadas en el sistema.

**Respuesta:**
```json
{
  "list": {
    "1": "Contado",
    "2": "30 días",
    "3": "60 días"
  }
}
```

---

#### `GET /api/pagos/payments`
Lista los pagos registrados con filtros y paginación.

---

### 5.14 Pedidos
**Controlador:** `app/Controller/PedidosController.php`
**Componente:** `APIPedidoComponent`

---

#### `POST /api/pedidos/facturar/:pedidoID`
Genera factura electrónica para una orden específica.

**Parámetros de ruta:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `pedidoID` | int | ID de la orden |

---

#### `GET /api/pedidos/statusList`
Lista los estados de pedido disponibles en formato v1.

**Respuesta:**
```json
{ "list": { "1": "Pendiente", "2": "Confirmado", "3": "Enviado" } }
```

---

#### `GET /api/pedidos/order_status_list`
Lista los estados de pedido en formato v2 con códigos API normalizados. Reemplaza a `statusList`.

---

#### `GET /api/pedidos/statusInfo`
Retorna información extendida de cada estado (color, ícono, acciones habilitadas).

---

#### `GET|PUT /api/pedidos/order/:id`
Obtiene el detalle completo de una orden (`GET`) o la actualiza (`PUT`).

**Parámetros de ruta:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | int | ID de la orden |

**Body PUT (parcial):**
```json
{
  "status": "shipped",
  "tracking_number": "ABC123",
  "paid": 1,
  "paymentMethod": 3,
  "cardFactor": 1.15,
  "notes": "Nota interna"
}
```

**Respuesta GET:**
```json
{
  "order": {
    "id": 123, "quantity": 2, "units": 3,
    "status": "confirmed", "currency": "ARS",
    "amount": "5000.00", "amount_to_pay": "5500.00",
    "date": "2026-04-07T10:00:00"
  },
  "client": { "id": 45, "name": "Juan", "last_name": "Pérez", "mail": "juan@mail.com" },
  "shipping": { "type": "home_delivery", "cost": 500.00, "tracking_number": "" },
  "address": { "street": "Av. Corrientes", "number": "1234", "city": "CABA" },
  "payment": { "code": "mercadopago", "paid": true, "amount": "5500.00" },
  "items": [
    { "name": "Producto X", "sku": "ABC", "price": "2500.00", "quantity": 2 }
  ]
}
```

---

#### `GET /api/pedidos/orders`
Lista órdenes con filtros avanzados y paginación.

**Parámetros de query (principales):**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Página |
| `limit` | int | Registros por página |
| `status` | string | Filtro por estado API |
| `from` | date | Fecha desde (YYYY-MM-DD) |
| `to` | date | Fecha hasta (YYYY-MM-DD) |
| `marketplace` | string | Código de marketplace |

---

#### `POST /api/pedidos/regenerateExtrasInOrder`
Regenera los conceptos extra (cargos adicionales) en una orden existente.

---

### 5.15 Picking / Fulfillment
**Controlador:** `app/Controller/PickingController.php`
**Componente:** `APIPickingComponent`

El módulo de picking gestiona el proceso de preparación de órdenes para despacho.

**Estados posibles:** `pending` → `open` → `finished` / `cancelled`

---

#### `POST /api/picking/reset/:orderID`
Reinicia el estado de pickeo de una orden a `pending`.

**Body JSON:**
```json
{ "order_id": 123, "notes": "Reiniciado por error de operario" }
```

> No se puede resetear un pickeo con estado `finished`.

---

#### `GET|POST /api/picking/status/:orderID`
Consulta el estado de pickeo de una orden.

**Respuesta:**
```json
{
  "picking": {
    "id": 5, "order_id": 123, "user_id": 2,
    "status": "open", "start": "2026-04-07 10:00:00", "end": null, "time": 0
  }
}
```

---

#### `POST /api/picking/start/:orderID`
Inicia el proceso de pickeo de una orden. Requiere que el usuario operario exista en el sistema.

**Body JSON:**
```json
{ "order_id": 123, "user_mail": "operario@empresa.com", "notes": "Turno mañana" }
```

---

#### `POST /api/picking/finish/:orderID`
Finaliza el pickeo de una orden. Solo se puede finalizar si el estado es `open`.

**Body JSON:**
```json
{ "order_id": 123, "notes": "Pickeo completado sin novedades" }
```

---

#### `POST /api/picking/cancel/:orderID`
Cancela el pickeo de una orden. Solo se puede cancelar si el estado es `open`.

---

#### `GET /api/picking/list`
Lista pickeos con filtros y paginación.

**Parámetros de query:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `status` | string | Estado API del picking (`pending`, `open`, `finished`, `cancelled`) |
| `order_ids` | string (CSV) | IDs de órdenes separados por coma |
| `page` | int | Página |
| `limit` | int | Registros por página |

---

### 5.16 Seguridad
**Controlador:** `app/Controller/SecurityController.php`

---

#### `POST /api/security/check`
Control antifraude. Verifica datos del comprador contra la blacklist interna.

**Body JSON:**
```json
{
  "ip": "186.143.200.92",
  "mail": "comprador@mail.com",
  "zip_code": "1640",
  "card_id": "30556466",
  "alpha2_site_country_code": "AR",
  "credit_cards": ["4807994905", "5079909055"]
}
```

**Respuesta:**
```json
{
  "status": "EXITO",
  "msg": "Control de fraude finalizado correctamente.",
  "level": 0,
  "report": ""
}
```

**Niveles de fraude:**
| Nivel | Significado |
|-------|-------------|
| `0` | Sin alertas (FRAUD_SUCCESS) |
| `1` | Alerta menor |
| `2+` | Alerta de fraude (FRAUD_ALERT) |

---

#### `POST /api/security/report`
Reporta datos sospechosos para agregarlos a la blacklist.

**Body JSON:** igual que `/api/security/check`.

---

#### `POST /api/security/unfraud`
Elimina datos de la blacklist (limpia acusaciones previas).

**Body JSON:** igual que `/api/security/check`.

---

#### `GET|POST /api/security/blacklist`

- **GET**: Lista completa de entradas en la blacklist.
- **POST**: Agrega o actualiza una entrada.

**Body POST:**
```json
{ "type": "IP", "data": "186.143.200.92", "complaints": 3 }
```

**Tipos válidos:** `IP`, `MAIL`, `ID` (DNI), `ZIP_CODE`, `CREDIT_CARD`

---

### 5.17 Usuarios / Clientes
**Controlador:** `app/Controller/UsuariosController.php`
**Componente:** `APIClienteComponent`, `APIUserComponent`, `APISellerComponent`, `APISupplierComponent`

---

#### `GET /api/usuarios/verifiedConditions`
Retorna las condiciones mínimas configuradas para considerar un cliente como "verificado" (mínimo de facturación, cantidad de órdenes, perfil requerido).

---

#### `GET /api/usuarios/client`
Obtiene datos completos de un cliente por ID, código o mail.

**Parámetros de query:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | int | ID del usuario |
| `code` | string | Código interno del cliente |
| `mail` | string | Email del cliente |

> Al menos uno es requerido.

**Respuesta:**
```json
{
  "client": {
    "id": 45, "login": "cliente@mail.com", "name": "Juan", "last_name": "Pérez",
    "code": "CLI001", "enabled": 1, "verified": 1,
    "phone": "1134567890",
    "clientType": { "id": 2, "name": "Mayorista", "color": "#FF0000" },
    "company": { "name": "Mi Empresa SA", "id": "20123456780", "address": "Av. Corrientes 1234" },
    "shipping_addresses": [{ "street": "Av. Corrientes", "number": "1234", "city": "CABA" }],
    "payment": { "type": { "default_id": 3 }, "conditions": { "default_id": 2 } }
  }
}
```

---

#### `GET /api/usuarios/clients`
Lista clientes con filtros y paginación.

**Parámetros de query:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `sellerID` | int | Filtra por vendedor asignado |
| `ids` | string (CSV) | IDs de clientes separados por coma |
| `search` | string | Búsqueda por login, mail o código |
| `page` | int | Página |
| `limit` | int | Registros por página (máx. `API_HARD_LIMIT_RECORDS`) |

---

#### `GET /api/usuarios/client_profiles`
Lista los perfiles/tipos de cliente configurados en el sistema.

**Respuesta:**
```json
{
  "profiles": {
    "1": { "id": 1, "name": "Minorista", "description": "...", "color": "#00FF00", "default": 1, "shop": true }
  }
}
```

---

#### `PUT /api/usuarios/client_updates`
Actualiza créditos y datos financieros de clientes en lote. Usado para sincronización con ERP.

**Body JSON:**
```json
{
  "clients": [
    {
      "code": "CLI001",
      "credit_total": 50000.00,
      "credit_available": 45000.00,
      "consumido_usd": -500.00,
      "consumido_cta": -200.00,
      "consumido_con": 0.00,
      "consumido_che": 0.00
    }
  ]
}
```

**Campos actualizables:**
| Campo API | Campo DB | Descripción |
|-----------|----------|-------------|
| `credit_total` | `credito_total` | Crédito total otorgado |
| `credit_available` | `credito` | Crédito disponible |
| `consumido_usd` | `credito_cash` | Consumo en efectivo/USD |
| `consumido_cta` | `credito_cuenta_cte` | Consumo en cuenta corriente |
| `consumido_con` | `credito_consignacion` | Consumo en consignación |
| `consumido_che` | `credito_cheques` | Consumo en cheques |

---

#### `POST|GET /api/usuarios/points/:userID`
Carga (`POST`) o consulta (`GET`) los puntos de fidelidad de un cliente.

**Parámetros de ruta:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `userID` | int | ID del usuario |

**Body POST:**
```json
{ "type": "add", "points": 100, "notes": "Bonificación por compra" }
```

---

#### `GET /api/usuarios/suppliers`
Lista proveedores (branderss) con sus marcas asignadas.

---

#### `GET /api/usuarios/sellers`
Lista ejecutivos/vendedores por IDs.

**Parámetros de query:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `ids` | string (CSV) | IDs de vendedores separados por coma |

---

#### `GET /api/usuarios/user`
Obtiene un usuario del sistema (operador, admin — no cliente) por ID, código o mail.

---

### 5.18 Utils
**Controlador:** `app/Controller/UtilsController.php`

---

#### `GET /api/utils/connect`
Health check de conectividad. Retorna `200 EXITO` si la API está operativa.

> Cumple función de compatibilidad hacia atrás. Para autenticación real, usar el token.

---

#### `GET /api/utils/dbProvinces/:countryCode`
Retorna el listado estático de provincias en formato ISO 3166-2.

**Parámetros de ruta:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `countryCode` | string | Código de país: `AR` (default), `UY`, `CL` |

---

#### `GET /api/utils/provinces`
Lista provincias desde la base de datos con ID y nombre.

---

#### `GET /api/utils/provincesInfo`
Lista provincias con ID interno de **nubixstore**, código ISO y nombre completo.

**Respuesta:**
```json
{
  "provinces": [
    { "ns_id": 1, "code": "AR-B", "name": "BUENOS AIRES" }
  ]
}
```

---

#### `GET /api/utils/IVAConditions`
Lista las condiciones de IVA disponibles en el sistema (responsable inscripto, consumidor final, etc.).

---

#### `GET /api/utils/EAN/:number/:prefix`
Genera y valida un código de barras EAN-13.

**Parámetros de ruta:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `number` | int | Número base (se rellena con ceros a 9 dígitos) |
| `prefix` | string | Prefijo del código (default: `779`) |

**Respuesta:**
```json
{
  "EAN": "7790000001234",
  "CHECK": true
}
```

---

## 6. Endpoints de Cron (`api_cron_list`)

Todos los controladores exponen un endpoint `GET /api/{modulo}/cron_list` que retorna la lista de tareas cron definidas para ese módulo.

**Formato de respuesta:**
```json
{
  "list": {
    "TASK-KEY": {
      "enabled": 1,
      "name": "NOMBRE DE LA TAREA",
      "notes": "Descripción de lo que hace.",
      "group": "GRUPO",
      "link": "/cron/modulo/accion?apiKey=%tokenID%",
      "max_exec_time": 300,
      "frecuency": 3600,
      "priority": "ALTA",
      "underground": 0
    }
  }
}
```

**Módulos con cron_list:** articulos, billing, blog, bookings, campaings, categorias, cron, deposits, envios, garantias, inbox, marcas, marketplaces, newsletters, pagos, pedidos, quizzes, returns, settings, usuarios.

---

## 7. Componentes Internos (`API*Component`)

Los `API*Component` encapsulan la lógica de consumo de la API propia para ser usados dentro de otros controladores. **No son endpoints**, son utilidades PHP internas.

**Patrón de uso:**
```php
// En un controlador que necesita datos de artículos:
$this->API['APIArticulo'] = $this->Components->load('APIArticulo');
$this->API['APIArticulo']->initialize($this);

$items = $this->API['APIArticulo']->getItems(['brandId' => 5]);
```

**Todos heredan de `APIComponent`** que inicializa automáticamente:
- Librería `NubixStoreRest`
- Token de autenticación Bearer
- URL base de la API según entorno (localhost / producción)

---

## 8. Guía de Uso para Agentes

> Esta sección aplica a quien desarrolla **dentro del código de nubixstore** (lado servidor,
> CakePHP 2). Si tu proyecto solo **consume** la API desde nsSkeleton, usá el conector
> (`NubixstoreConnector`) y el skill `nubixstore-api`; esta sección es referencia de cómo se
> exponen los endpoints del otro lado.

### Antes de crear un nuevo endpoint

1. Verificar en el resumen de endpoints de la API (`agent-ns-api-resume.md`, en el repo de nubixstore) que no exista uno similar.
2. Seguir la convención de nombre: `api_{accion}` en el controlador correspondiente.
3. La ruta resultante será automáticamente `/api/{controlador}/{accion}`.

### Al crear un nuevo endpoint

```php
// En el controlador correspondiente:
function api_mi_endpoint() : void {

    // 1. Leer parámetros
    $param = empty($this->params->query['param']) ? null : $this->params->query['param'];

    // 2. Lógica de negocio
    $data = $this->MiModelo->find('all', [...]);

    // 3. Construir respuesta
    $this->apiResult['data'] = $data;
    $this->apiResult['msg'] = 'Operación exitosa.';

    // 4. Enviar respuesta (siempre al final)
    $this->_setAPIResponse();
}
```

### Al actualizar este manual

1. Agregar la sección del nuevo endpoint en el módulo correspondiente del **Paso 5**.
2. Agregar la fila en el resumen de endpoints (`agent-ns-api-resume.md`, repo nubixstore).
3. Si el endpoint fue solicitado como mejora, mover su fila a estado `DONE`.
4. **En nsSkeleton:** si cambia el número de versión del manual, actualizar también la
   constante `NubixstoreConnector::API_MANUAL_VERSION` y el test que la verifica.

### Errores comunes a evitar

| Error | Solución |
|-------|----------|
| No llamar a `_setAPIResponse()` | Siempre llamarlo al final de cada función `api_*` |
| SQL injection en filtros | Usar el array `conditions` de Cake2, nunca concatenar SQL |
| Cargar relaciones innecesarias | Usar `recursive => -1` y `contain` solo cuando sea necesario |
| No paginar resultados | Implementar `page` y `limit` en todos los endpoints de listado |

---

*Última actualización: 2026-04-23 — Agente `api-documenter` (claude-opus-4-6)*
