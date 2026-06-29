# Instalación y uso

Guía rápida para poner en marcha un proyecto basado en nsSkeleton.

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
