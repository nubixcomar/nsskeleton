# Roadmap — Sistema de Facturación

> EJEMPLO COMPLETADO. Estados: 📋 Pendiente · 🟡 En progreso · 🧪 En testing · ✅ Completo

## v0.1 — Catálogo (base para facturar)
| # | Funcionalidad | Estado | Sprint |
|---|---------------|--------|--------|
| 1 | Módulo Clientes (CUIT, condición IVA, contacto) | 📋 | S1 |
| 2 | Módulo Productos/Servicios (precio, alícuota IVA) | 📋 | S1 |
| 3 | Roles: admin / viewer (RBAC ya disponible) | 📋 | S1 |

## v0.2 — Comprobantes
| # | Funcionalidad | Estado | Sprint |
|---|---------------|--------|--------|
| 1 | Módulo Comprobantes (factura: cliente + ítems) | 📋 | S2 |
| 2 | Cálculo automático subtotal / IVA / total | 📋 | S2 |
| 3 | Numeración correlativa por punto de venta | 📋 | S2 |
| 4 | Estados: pendiente / pagada / anulada | 📋 | S2 |
| 5 | Anulación + nota de crédito | 📋 | S3 |

## v0.3 — Cobros y reportes
| # | Funcionalidad | Estado | Sprint |
|---|---------------|--------|--------|
| 1 | Registrar cobros contra comprobantes | 📋 | S3 |
| 2 | Estado de cuenta por cliente | 📋 | S3 |
| 3 | Reporte de ventas por período y por cliente | 📋 | S4 |
| 4 | Envío de la factura por email (cola ya disponible) | 📋 | S4 |

## v1.0 — Facturación electrónica (integración)
| # | Funcionalidad | Estado | Sprint |
|---|---------------|--------|--------|
| 1 | Integración ARCA/AFIP (CAE) vía conector | 📋 | S5 |
| 2 | Validaciones fiscales (tipo de comprobante por condición IVA) | 📋 | S5 |

---

## Cómo arrancar el primer sprint
```
/sprint open S1 "Catálogo"
/nuevo-modulo Cliente clientes "nombre:string cuit:string condicion_iva:string email:string telefono:string direccion:text activo:bool"
/nuevo-modulo Producto productos "nombre:string codigo:string precio:decimal iva:decimal activo:bool"
php system/database/migrate.php
# luego los agentes implementan validaciones y reglas de negocio, con tests
```
