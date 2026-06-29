# Adapter: php-mvc (stack por defecto)

Mapea los conceptos **genéricos** del dominio agéntico a los paths, clases y
convenciones del stack por defecto de nsSkeleton (PHP MVC propio + MySQL).

> Patrón Hexagonal (Ports & Adapters): el dominio (`../../skills`, `../../rules`) no
> conoce este stack. Toda referencia concreta vive acá. Para soportar otro stack, se
> crea `agentic/adapters/{otro-stack}/` sin tocar el dominio.

## Archivos
- [`conventions.md`](conventions.md) — paths, clases y convenciones del stack.

## Cuándo crear/editar
- Al cambiar de stack en el instalador.
- Al agregar un binding que un skill genérico necesita resolver (ej. "dónde van las
  migraciones", "cómo se loguea", "dónde está el router").
