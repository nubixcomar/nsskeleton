# Brief — Sistema de Facturación

> EJEMPLO COMPLETADO. Así se ve un `docs/brief.md` listo para empezar a desarrollar.

## 1. Resumen ejecutivo
Sistema web para emitir y gestionar facturas de venta de una PyME, con clientes,
productos/servicios, y comprobantes. Apunta a reemplazar la facturación manual en
planilla, centralizando catálogo, comprobantes y cuentas por cobrar. Lo usan
administrativos desde el navegador (desktop y mobile).

## 2. Objetivos
- Emitir una factura en menos de 1 minuto.
- Tener el historial de comprobantes y el estado de cobro de cada cliente.
- Reducir errores de cálculo (subtotal, IVA, total) a cero.
- Base lista para integrar facturación electrónica (ARCA/AFIP) en una segunda etapa.

## 3. Alcance
### Incluye
- ABM de clientes, productos/servicios y comprobantes (factura/nota de crédito).
- Cálculo automático de subtotal, IVA y total.
- Numeración de comprobantes por punto de venta.
- Listados con búsqueda y estados de cobro (pendiente/pagada/anulada).
- Reportes básicos (ventas por período, por cliente).

### NO incluye (por ahora)
- Integración real con ARCA/AFIP (queda para v2).
- Gestión de stock avanzada / depósito (eso es el WMS, otro proyecto).
- Pagos online.

## 4. Usuarios y roles
| Rol | Descripción | Permisos clave |
|-----|-------------|----------------|
| superadmin | Dueño/responsable | Todo |
| admin | Administrativo | Clientes, productos, comprobantes, reportes |
| viewer | Consulta | Ver listados y reportes (sin editar) |

## 5. Módulos y funcionalidades
| Módulo | Descripción | Prioridad |
|--------|-------------|-----------|
| Clientes | Datos fiscales (CUIT, condición IVA, dirección, contacto). | Alta |
| Productos/Servicios | Nombre, precio, alícuota de IVA, código. | Alta |
| Comprobantes | Factura/NC: cliente, ítems, totales, estado, numeración. | Alta |
| Cobros | Registrar pagos contra comprobantes; estado de cuenta. | Media |
| Reportes | Ventas por período y por cliente; IVA del período. | Media |

## 6. Reglas de negocio clave
- Total = subtotal + IVA; el IVA se calcula por ítem según su alícuota.
- La numeración es correlativa por punto de venta; no se puede saltear ni repetir.
- Una factura emitida no se edita: se anula y se emite otra (trazabilidad).
- Un cliente sin CUIT válido no puede recibir factura A.

## 7. Integraciones externas
- **v1**: ninguna obligatoria (emisión interna).
- **v2**: ARCA/AFIP (CAE) y, opcionalmente, envío de la factura por email (ya hay módulo
  de emails con cola en la base).

## 8. Requisitos no funcionales
- Responsive (administrativos usan desktop, a veces el celular).
- Navegadores: Chrome, Edge, Brave, Safari.
- Auditoría de quién emitió/anuló cada comprobante (ya hay módulo de auditoría).
- Backups automáticos diarios (ya hay módulo de backup + cron).

## 9. Restricciones y supuestos
- PyME chica: 1–5 usuarios concurrentes.
- Hosting compartido con PHP 8.2 + MySQL (XAMPP en desarrollo).
- Se prioriza simplicidad y velocidad de emisión sobre features avanzadas.
