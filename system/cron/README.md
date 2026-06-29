# cron/ — Cronmaster (programador de tareas)

El patrón es el estándar de "una entrada de cron del SO → scheduler interno":
el sistema operativo invoca `run.php` **una vez por minuto**, y el cronmaster decide
qué tareas (definidas desde el panel `/admin/cron`) están vencidas y las ejecuta,
registrando salida, código de salida e historial.

## Cablear al sistema operativo

### Linux / cron
```
* * * * * /usr/bin/php /ruta/skeleton/system/cron/run.php >> /ruta/skeleton/system/storage/logs/cron.log 2>&1
```

### Windows / Programador de tareas (XAMPP)
- Crear una tarea básica que se repita **cada 1 minuto**.
- Acción: iniciar un programa
  - Programa: `C:\xampp\php\php.exe`
  - Argumentos: `C:\xampp\htdocs\skeleton\system\cron\run.php`

## Probar a mano
```
php system/cron/run.php
```
Muestra qué tareas se ejecutaron (o "sin tareas vencidas").

## Modelo de datos
- `cron_tasks`: definición (nombre, comando, expresión cron, activa, última/próxima corrida).
- `cron_runs`: historial de ejecuciones (inicio, fin, estado, exit code, salida).

## Notas
- Las expresiones cron son de 5 campos (`m h dom mon dow`). Soporta `*`, `*/n`,
  `a-b`, `a-b/n` y listas `a,b,c`. Ver `app/Services/CronExpression.php`.
- El comando se ejecuta en el shell del servidor: definí solo comandos de confianza.
