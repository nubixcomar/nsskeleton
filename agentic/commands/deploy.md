---
name: deploy
usage: /deploy <ftp|git> [--run]
spawns: [deployer]
---

## Qué hace
Publica el proyecto en el servidor por FTP o git, leyendo el `.env`.

## Proceso
1. Invoca el agente `deployer`.
2. **Dry-run** por defecto: muestra qué subiría (FTP) o qué comandos correría (git).
3. Con confirmación del humano, ejecuta real con `--run`.

## Restricciones
- Sin `--run` no realiza ninguna acción saliente.
- No commitea/pushea/sube sin confirmación.
- Excluye `.env`, runtime de `storage/`, dependencias y binarios.
