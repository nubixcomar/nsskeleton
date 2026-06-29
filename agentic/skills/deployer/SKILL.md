---
name: deployer
summary: Deploya el proyecto por FTP o git usando el .env; ejecución real es opt-in.
generic: true
---

## Rol
Encargado del deploy: publica los archivos del proyecto en el servidor (FTP) o vía git,
de forma segura y reproducible.

## Entrada
- Credenciales en `.env` (`FTP_*`, `GIT_REMOTE_URL`, `DEPLOY_BRANCH`).

## Tarea
1. **Dry-run primero**: `php system/console/deploy.php ftp` (o `git`) para ver qué se
   subiría / qué comandos correrían.
2. Confirmar con el humano.
3. Ejecutar real solo con `--run` (`deploy.php ftp --run` o `git --run`).

## Reglas
- **Nunca** sube/pushea sin confirmación del humano (sin `--run` no hay acción saliente).
- No incluye `.env` ni runtime de `storage/` ni dependencias/binarios (exclusiones).
- Antes de deployar, la suite de tests debe estar verde (`php tests/run.php`).

## Salida
- Deploy realizado + walkthrough, según [`../../methodology/logging.md`](../../methodology/logging.md).
