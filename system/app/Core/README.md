# Core — Núcleo MVC

Micro-framework propio (sin dependencias) que sostiene el sistema base. PHP 8.2+.

> 📖 **API completa de cada clase (firmas + ejemplos):**
> [`agentic/knowledge/core-manual.md`](../../../agentic/knowledge/core-manual.md) §1.
> Esta tabla es solo el mapa rápido.

| Clase            | Rol                                                              |
|------------------|------------------------------------------------------------------|
| `autoload.php`   | Autoloader PSR-4 (`Core\*`, `App\*`).                            |
| `Env`            | Carga y lee `.env`.                                              |
| `App`            | Kernel: arranca, enruta y emite; maneja excepciones.            |
| `Router`         | Rutas con parámetros `{param}`; despacha a controladores/closures.|
| `Request`        | Petición HTTP (método, path, input, headers); method override.  |
| `Response`       | Respuesta HTTP (`html`, `json`, `redirect`, `download`).        |
| `Session`        | Sesión + flash + CSRF.                                          |
| `Database`       | PDO singleton + helpers (`select`, `insert`, `affected`).       |
| `Model`          | Modelo base (CRUD simple sobre `$table`).                       |
| `Controller`     | Controlador base (`view`, `json`, `redirect`, `abort`).         |
| `View`           | Render de vistas PHP con layout + overrides + `e()` para escapar.|
| `Auth`           | Login de administradores contra `admin_users`.                 |
| `Crypto`         | Cifrado AES-256-GCM de secretos (clave `APP_KEY`).             |
| `Security`       | Cabeceras de seguridad HTTP (CSP, HSTS, X-Frame-Options).      |
| `Url`            | URLs relativas al directorio público (subdirectorio-safe).     |
| `Assets`         | Resuelve CSS/JS local o CDN y arma el `<head>`.                |
| `Icons`          | Iconos SVG inline (heroicons).                                  |

## Flujo de una petición

```
public/index.php → Env::load → App::run
   → Session::start → Request::capture
   → config/routes.php (registra rutas) → Router::dispatch
   → Controller@método → Response::send
```

## Cómo correrlo

- DocumentRoot ideal: `system/public/`. En XAMPP sin vhost:
  `http://localhost/skeleton/system/public/` (el Router resuelve el subdirectorio).
- Configurar `.env` en la raíz del proyecto (DB, APP_*).
- Ruta de prueba: `/` (home) y `/health` (JSON de estado).

## Convenciones

- Controladores: `App\Controllers\*` en `app/Controllers/`.
- Modelos: `App\Models\*` en `app/Models/`.
- Servicios: `App\Services\*` en `app/Services/` (mail, backup, cron, ai, files).
- Vistas: `app/Views/*.php`; layout por defecto `layouts/app`.
