---
code: PL-NNN
title: <Nombre del plan>
status: ✏️ Borrador
brief: —            # BR-NNN o —
rm: —               # RM-NNN o —
origin: brief       # roadmap | brief | urgencia | imprevisto | mejora-técnica
exception: —        # si se salteó una fase: cuál y por qué
size: M             # M | L | XL
replaces: —         # PL-NNN si reemplaza a otro plan
planned_with: <modelo y esfuerzo usados para planificar>
execution_hint: estándar / esfuerzo medio
loop_ready: false   # true cuando todas las fases cumplen el formato ejecutable
created: YYYY-MM-DD
updated: YYYY-MM-DD
---

# PL-NNN — <Nombre del plan>

> **Plan = contrato ejecutable.** Otro agente, en otra sesión, con un modelo más económico,
> tiene que poder ejecutarlo sin hacer preguntas. Si una fase requiere "pensar mucho", falta
> detalle acá.

## 1. Resumen
<!-- 3-5 líneas: qué se implementa y qué resultado queda funcionando. -->

## 2. Fuentes y trazabilidad
- Brief: BR-NNN (o — con motivo) · Roadmap: RM-NNN (o —)
| Criterio de éxito (del brief) | Se cubre en | Se verifica con |
|-------------------------------|-------------|-----------------|
|                               | F?          |                 |

## 3. Contexto técnico actual
<!-- Qué existe hoy en el código relacionado. Qué se reusa del core (ver core-manual.md) y del
     proyecto. Archivos/módulos relevantes leídos para planificar. -->
- 

## 4. Diseño
### 4.1 Modelo de datos
<!-- Tablas/entidades nuevas o modificadas, campos, tipos, índices, relaciones. -->
### 4.2 Contratos
<!-- Rutas/endpoints, firmas de servicios, eventos, payloads, permisos. -->
### 4.3 Flujos
<!-- Secuencias principales y de error, paso a paso. -->
### 4.4 UI (si aplica)
<!-- Pantallas, estados (vacío/cargando/error), validaciones. -->

## 5. Decisiones y alternativas descartadas
| Decisión | Alternativas | Motivo |
|----------|--------------|--------|
|          |              |        |

## 6. Fases
<!-- Fases chicas, ordenadas, cada una deja el sistema funcionando. Copiar el bloque por fase. -->

### F1 — <nombre> · estado: 📋 pendiente
- **Objetivo:** 
- **Depende de:** —
- **Paralelizable con:** —
- **Archivos:** (crear / modificar)
  - `ruta/archivo` — qué cambia
- **Pseudocódigo:**
  ```
  función X(entrada):
      validar …
      si … entonces …
      persistir …
      devolver …
  ```
- **Criterios de aceptación (verificables):**
  - [ ] 
- **Verificación:** `<comando de test / chequeo>`
- **Notas para el ejecutor:** 

### F2 — <nombre> · estado: 📋 pendiente
- …

## 7. Plan de pruebas
<!-- Tests unitarios/feature a agregar, casos borde del brief cubiertos, prueba manual. -->
- 

## 8. Migraciones y rollback
<!-- Cómo se aplica y cómo se revierte. Datos existentes afectados. -->
- 

## 9. Impacto y aislamiento
<!-- Qué otros módulos toca y por qué; cómo se desactiva (feature flag/ruta). -->
- 

## 10. Riesgos técnicos
| Riesgo | Probabilidad | Mitigación |
|--------|--------------|------------|
|        |              |            |

## 11. Fuera de alcance
- 

## 12. Recomendación de ejecución
- **Modelo/esfuerzo sugerido para ejecutar:** estándar / medio (el plan ya resolvió lo difícil).
- **Apto para loop:** sí / no — motivo.
- **Fases paralelizables:** 
- **Puntos de control humano:** (fases tras las que conviene revisar antes de seguir)

## 13. Revisión del plan
| Fecha | Revisor (agente/modelo) | Hallazgos | Resolución |
|-------|-------------------------|-----------|------------|
|       |                         |           |            |

## 14. Registro de ejecución
| Fase | Fecha | Agente | Modelo | Resultado | Walkthrough |
|------|-------|--------|--------|-----------|-------------|
|      |       |        |        |           |             |

## 15. Desvíos
<!-- Todo lo que se hizo distinto a lo planificado, con motivo. -->
- 

## 16. Cierre
- [ ] Todas las fases ✅ (u ⏭️ con motivo).
- [ ] Criterios de éxito del brief verificados.
- [ ] Tests verdes.
- [ ] BR/RM vinculados actualizados.
- [ ] Definición de Hecho de [`sprints.md`](../../agentic/methodology/sprints.md) cumplida.
