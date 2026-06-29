# Reglas del CORE (stack y convenciones de nsSkeleton)

> **Propiedad del core.** Este archivo lo mantiene nsSkeleton y **el actualizador de core lo
> pisa**. NO lo edites en tu proyecto: para cambiar o agregar reglas, usá
> [`app-agentic/rules/app-rules.md`](../../app-agentic/rules/app-rules.md), que se carga
> **después** y tiene prioridad (app > core).
>
> Lo de abajo asume el **stack por defecto de nsSkeleton** (PHP MVC propio + MySQL +
> Tailwind/Alpine). Si tu proyecto usa otro stack, declaralo en `app-rules.md`.

## Contexto
- Core: nsSkeleton (framework agéntico + esqueleto de sistema). El contexto del **proyecto**
  va en `app-rules.md` y `docs/brief.md`.
- Stack default: PHP 8.2+ (MVC propio), MySQL/MariaDB, Tailwind CSS standalone, Alpine.js.
- Entorno de desarrollo: Windows / XAMPP (PHP CLI en `C:/xampp/php/php.exe`).

## Material de referencia (leer antes de codear sobre el core)
- **Manual del core** → [`../knowledge/core-manual.md`](../knowledge/core-manual.md):
  API y ejemplos de TODOS los módulos de `system/` (núcleo MVC, auth, cron/jobs, mail,
  backup, IA, ecommerce, archivos, UI) + receta para construir un módulo nuevo.
  **Regla:** antes de implementar algo, revisá si el core ya lo resuelve y reusalo.
- **Dónde va cada cosa** → [`../adapters/php-mvc/conventions.md`](../adapters/php-mvc/conventions.md).
- **Arquitectura de capas** → [`../../docs/architecture.md`](../../docs/architecture.md).

## Convenciones de código
- **PHP**: PSR-12 como guía de estilo. Clases en `PascalCase`, métodos en `camelCase`.
- **MVC**: controladores finos, lógica en servicios/modelos; vistas sin lógica de negocio.
- **DB**: nombres de tabla en `snake_case` plural; migraciones versionadas en `database/migrations/`.
- **Front**: mobile-first; compatible con Safari, Chrome, Edge y Brave.

## Seguridad
- Nunca confiar en el input: validar, sanitizar, castear.
- Consultas parametrizadas / prepared statements; **prohibido** concatenar SQL con input.
- No exponer credenciales; leerlas de `.env`. No commitear `.env`.
- Hash de contraseñas con `password_hash()` (bcrypt/argon2).

## Performance
- Evitar N+1; usar índices; paginar listados grandes.
- Sin `var_dump`/`print_r`/`dd()` en producción.

## Versionado
- nsSkeleton: semántico (`MAJOR.MINOR.PATCH`). Los proyectos derivados eligen el suyo.
- El proyecto declara sobre qué versión de core nació (para el actualizador de core).

## Frontera core ↔ proyecto (no editar core)
- El core es dueño de los archivos listados en `core-lock.json`; **todo lo demás es del proyecto**.
- No edites archivos de `agentic/` ni del core de `system/`: overridealos desde
  `app-agentic/` (reglas/agentes/skills/plantillas) o desde los puntos de extensión del stack.

## Herramientas de los agentes
- Preferir herramientas de archivo (Read/Write/Edit) sobre scripts ad-hoc.
- Si se necesita PHP por CLI en XAMPP: `C:/xampp/php/php.exe`.
