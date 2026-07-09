# Camino dorado de producción — Deploy de nsSkeleton

> Nace del **deploy real de nsCheckout** (auditoría 2026-07-09). La mayoría de los
> problemas de producción **no se ven en local**: aparecen al desplegar en un server
> real (nginx, pool PHP-FPM aislado). Esta guía + los templates evitan repetirlos.

## 1. Regla de oro: el docroot apunta a `system/public`

El front-controller vive en `system/public/index.php`. El **DocumentRoot / `root`
del server debe apuntar ahí**, NO a la raíz del proyecto. Así `docs/`, `tests/`,
`installer/`, `agentic/`, el código fuente y los `.env` quedan **fuera del webroot**
y no son accesibles por HTTP (hallazgos 1.3 y 1.5).

```
/var/www/tu-sitio/            ← raíz del proyecto (FUERA del webroot)
├── system/
│   ├── public/               ← DocumentRoot / root del server  ✅
│   │   └── index.php
│   └── storage/sessions/     ← sesiones (escribible)
├── docs/ tests/ installer/   ← NO accesibles por HTTP
└── .env                      ← NO accesible por HTTP
```

## 2. Templates de server

- **nginx:** [`nginx.conf.example`](nginx.conf.example) — incluye `try_files`, bloque
  `~ \.php$`, fix de `SCRIPT_NAME`, protección de dotfiles/secretos y nota del pool
  PHP-FPM aislado.
- **Apache:** [`apache-vhost.conf.example`](apache-vhost.conf.example) — DocumentRoot
  correcto + `AllowOverride All` (el `.htaccess` ya hace el rewrite).

## 3. Subdirectorio / alias / proxy → `APP_BASE_PATH`

Si servís en un subdirectorio, detrás de un `alias` de nginx o un proxy, y las rutas
se resuelven mal (la app busca `/login` en vez de `/admin/login`), seteá en el `.env`:

```
APP_BASE_PATH=/tu-subdir
```

Es un fallback explícito y robusto que no depende de `SCRIPT_NAME` (hallazgo 1.3).

## 4. Sesiones en pools aislados → ya resuelto por el core

En hostings con aislamiento (usuario propio + `open_basedir`), el `save_path` global
de PHP no es escribible → login/CSRF/carrito rotos (hallazgo 1.4). El core ya apunta
`session.save_path` a `system/storage/sessions/` (ver `Core\Session`). Configurable
por `SESSION_PATH` en el `.env`. Asegurate de que ese directorio sea **escribible**.

## 5. Checklist antes de desplegar: `deploy:doctor`

Corré el diagnóstico (no hace cambios, solo reporta):

```
php system/console/deploy.php doctor
```

Valida: versión de PHP, extensiones (`pdo_mysql`, `mbstring`, `openssl`, `curl`,
`zip`, `fileinfo`), docroot, permisos de `storage/*`, `session.save_path` escribible,
`APP_KEY`, `.env`, y `APP_DEBUG` apagado en producción. Sale con código ≠ 0 si algún
chequeo de severidad **error** falla.

## 6. Pasos típicos

1. Subir el proyecto (idealmente solo el runtime — ver `Deployer` y el backlog de
   allowlist v1.17). Docroot → `system/public`.
2. `cp .env.example .env` y completar (DB, APP_KEY con `php system/console/key.php`,
   `APP_ENV=production`, `APP_DEBUG=false`).
3. Importar el esquema (`docs/` / `schema.sql`) y correr migraciones.
4. Configurar el server con el template correspondiente.
5. `php system/console/deploy.php doctor` → todo en OK.
6. Verificar `/health` y el login.
