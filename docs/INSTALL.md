# Instalación y uso

Guía rápida para poner en marcha un proyecto basado en nsSkeleton.

## 0. Crear un proyecto nuevo desde el skeleton (en esta máquina)

> **No copies y pegues la carpeta del skeleton.** Te arrastra cosas que no van en un proyecto
> nuevo: el `.git` apuntando al repo del skeleton (tus commits irían ahí), `landing/downloads/*.zip`,
> `tools/bin/` (binario de ~108 MB), `.env` con credenciales del skeleton y los logs/VERSION del core.

**Patrón recomendado:** mantené `c:\xampp\htdocs\skeleton` como **"master"** (sincronizado con el
repo; es la fuente del core y la que genera paquetes). Cada proyecto vive en **su propia carpeta**,
con copia limpia y git propio.

**1) Copia limpia** — elegí una:
```bash
# Opción A — clonar y cortar el vínculo con el repo del skeleton:
git clone https://github.com/nubixcomar/nsskeleton.git c:/xampp/htdocs/miproyecto
cd c:/xampp/htdocs/miproyecto && rm -rf .git

# Opción B — desde el paquete oficial (ya excluye .git, .env, tools/bin, downloads, runtime):
#   en el master:  php landing/build-download.php   → landing/downloads/nsSkeleton-<version>.zip
#   descomprimir ese zip en  c:/xampp/htdocs/miproyecto
```

**2) Instalar** (configura el proyecto; NO se edita el core):
```bash
cd c:/xampp/htdocs/miproyecto
/instalar     # con tu IA: Q&A que genera .env, app-agentic/rules/app-rules.md y docs/brief.md
#   (parte mecánica equivalente:  php system/console/install.php --answers=respuestas.json)
```

**3) Base de datos:**
```bash
php system/database/migrate.php        # tablas del core
php system/database/seed-demo.php      # opcional: datos demo
```

**4) Git propio del proyecto** (remoto separado del skeleton):
```bash
git init && git add -A && git commit -m "init miproyecto desde nsSkeleton"
# git remote add origin <tu-repo-nuevo> && git push -u origin main
```

**5) Desarrollá SIEMPRE en lo "tuyo"** (lo que el actualizador de core nunca pisa): módulos con
`/nuevo-modulo`, overrides (`config/overrides/`, `routes.app.php`, `app/Views/overrides/`,
`database/migrations/app/`) y tus reglas/agentes en `app-agentic/`. Cuando salga una versión nueva
del core: `/actualizar-core <zip|url>` y tu trabajo queda intacto (ver [`CORE-UPDATE.md`](CORE-UPDATE.md)).

> Luego seguí con los pasos 1–6 de abajo (entorno, DB, levantar) y con [`empezar.md`](empezar.md)
> para el ciclo de desarrollo (brief → sprints → módulos → release).

---

## Requisitos
- PHP 8.2+ (XAMPP lo trae en `C:\xampp\php\php.exe`).
- MySQL / MariaDB (módulo MySQL de XAMPP).
- Apache (XAMPP) o el servidor PHP integrado para desarrollo.
- Extensiones: PDO MySQL, cURL, ZipArchive (todas en XAMPP).

## 1. Configurar el entorno
```
copy .env.example .env      # (Windows)   ·   cp .env.example .env (Unix)
```
Editá `.env` y completá al menos `DB_*`. Opcional: `MAIL_*`, `AI_*`, `BACKUP_*`.

## 2. Crear la base y las tablas
Iniciá MySQL en XAMPP y ejecutá:
```
php system/database/migrate.php     # crea la base + aplica migraciones
php system/database/seed.php        # crea el admin por defecto
```
Admin inicial: `admin@nsskeleton.local` / `admin1234` (cambialo tras entrar).

## 3. Levantar la aplicación

### Opción A — servidor PHP integrado (desarrollo)
```
php -S 127.0.0.1:8080 -t system/public system/public/index.php
```
Abrí http://127.0.0.1:8080 y http://127.0.0.1:8080/admin/login

### Opción B — Apache / XAMPP
Apuntá el DocumentRoot del virtualhost a `system/public/`. Si no, accedé vía
`http://localhost/skeleton/system/public/` (el router resuelve el subdirectorio).

## 4. Backend de administración
- `/admin/login` → ingresar
- `/admin` → dashboard con métricas y gráficos
- Módulos: Perfiles, Tareas/Cron, Emails, Backups, Archivos, Conector IA.

## 5. Tareas programadas (cron)
Programá en el SO, **cada minuto**:
```
* * * * * C:\xampp\php\php.exe C:\xampp\htdocs\skeleton\system\cron\run.php
```
(En Windows: Programador de tareas con repetición de 1 minuto.) Ver `system/cron/README.md`.

## 6. Backups automáticos
Programá `php system/backup/run.php` (ej. cron `0 3 * * *`) desde el cronmaster o el SO.
Ver `system/backup/README.md`.

---

## Trabajar con agentes de IA
Cualquier IA arranca leyendo **`AGENTS.md`** en la raíz (no hay carpetas propietarias).
Ese archivo deriva a `agentic/rules/rules.md` → metodología → `docs/`. Ver `README.md`.
