# Cómo empezar un proyecto desde nsSkeleton (post-instalación)

Esta guía explica qué hacés **después de instalar** nsSkeleton para empezar a desarrollar
tu propio sistema (facturación, WMS, stock, turnos, lo que sea).

---

## Paso 0 — Instalar (una sola vez)
1. Creá una **copia limpia** del skeleton en su propia carpeta (no copies/pegues la carpeta
   del master). Cómo hacerlo bien (clonar y cortar git, o desde el paquete) está en
   [`INSTALL.md` §0](INSTALL.md).
2. Configurá el entorno y la base:
   ```
   copy .env.example .env      (y completá DB_*)
   php system/database/migrate.php
   php system/database/seed.php
   ```
   O, si trabajás con una IA, pedile `/instalar` y te hace el Q&A.
3. Levantá la app: `php -S 127.0.0.1:8080 -t system/public system/public/index.php`
   → `/admin/login` (admin@nsskeleton.local / admin1234).

> Detalle completo en [`INSTALL.md`](INSTALL.md).

---

## Paso 1 — Completar la documentación (lo que define TU proyecto)
Son **4 archivos**. Esto es lo que el humano completa; los agentes los leen antes de
desarrollar. Usá las plantillas de `agentic/templates/`.

| Archivo | Qué poner | Plantilla |
|---------|-----------|-----------|
| **`docs/stack.md`** | El stack que vas a usar (o confirmás el default). | `agentic/templates/` (ya viene) |
| **`docs/brief.md`** ⭐ | Qué es el sistema, para quién, módulos, reglas de negocio, integraciones. **Es la fuente de verdad funcional.** | `agentic/templates/brief.template.md` |
| **`docs/roadmap.md`** | Las funcionalidades por versión, con estados. | `agentic/templates/roadmap.template.md` |
| **`.env`** | Accesos: base de datos, FTP/deploy, repo, email, IA. | `.env.example` |

El **brief** es lo más importante: cuanto mejor lo completes, mejor desarrolla la IA.
No hace falta que sea perfecto — podés empezar con lo básico e ir refinando.

---

## Paso 2 — Arrancar a desarrollar con la capa agéntica
Una vez completado el brief, cualquier IA arranca leyendo **`AGENTS.md`**, que la deriva a
`agentic/rules/rules.md` → metodología → **`docs/brief.md`**. A partir de ahí:

1. **Abrí un sprint**: `/sprint open S1 "Núcleo de facturación"` (toma ítems del roadmap).
2. **Generá las entidades** con el acelerador:
   ```
   /nuevo-modulo Cliente clientes "nombre:string cuit:string email:string condicion_iva:string"
   /nuevo-modulo Producto productos "nombre:string precio:decimal iva:decimal stock:int"
   /nuevo-modulo Factura facturas "numero:string cliente_id:int total:decimal fecha:date estado:string"
   ```
   Cada uno crea migración + modelo + ABM + API + menú, en minutos.
3. **Los agentes implementan** la lógica de negocio (controladores, servicios) siguiendo el
   brief y la metodología. **Siempre** registran lo que hacen (walkthrough + log) y
   **agregan tests** (`tests/`).
4. **Verificás**: `php tests/run.php` (debe quedar verde) y probás en `/admin`.

---

## Paso 3 — El ciclo de desarrollo
```
brief → /sprint open → /nuevo-modulo (entidades) → agentes implementan features
      → tests verdes → walkthrough → /sprint close → /release <version> → /deploy
```
- **Bugs**: `/bug` detecta, `/fix` corrige (ciclo OPEN→DONE, ver `agentic/methodology/bug-tracking.md`).
- **Calidad**: `/audit` (seguridad+performance), `/test` (suite).
- **Versionado y publicación**: `/release` (changelog + paquete), `/deploy` (FTP/git).

---

## Ejemplo completo
En [`examples/sistema-facturacion/`](examples/sistema-facturacion/) tenés un **brief,
stack y roadmap ya completados** para un sistema de facturación, para que veas cómo se ve
todo listo para empezar. Copiá ese estilo en tus propios `docs/` y arrancá.
