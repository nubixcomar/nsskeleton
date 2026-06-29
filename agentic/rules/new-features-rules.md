# Reglas para features nuevos (aislamiento)

> Principio central portado de nubixstore: **máxima independencia** de cada feature.

Todo feature nuevo debe:

- Estar **100% aislado** en sus propios controladores, modelos y vistas.
- **No modificar** código existente, salvo autorización explícita del humano.
- **No refactorizar "de paso"** (eso es una tarea aparte, con su propio agente).
- Ser **desactivable** sin comprometer otros módulos (feature flag / ruta aislada).
- Si necesita tocar otro módulo, **proponer alternativas** y pedir aprobación antes.

## Por qué
El aislamiento mantiene el sistema estable y permite que múltiples agentes (y
humanos) trabajen en paralelo sin pisarse, y que cualquier feature se pueda revertir
limpiamente.
