# Manual del Core — nsSkeleton (para agentes IA)

> **Versión del core documentada:** 1.11.0 · **Audiencia:** una IA agente que va a
> construir un sistema (facturador, CRM, inventario, lo que sea) **encima** del
> esqueleto, sin tener que leer el código fuente de cada clase.
>
> Este documento es la **fuente de verdad** de QUÉ módulos vienen preinstalados en
> `system/` y CÓMO usarlos. Es conocimiento agnóstico (vive en `agentic/knowledge/`).
> Para CÓMO se desarrolla (reglas, metodología, skills) ver [`../rules/rules.md`](../rules/rules.md)
> y [`../methodology/`](../methodology/). Para la arquitectura de capas ver
> [`../../docs/architecture.md`](../../docs/architecture.md).

---

## 0. Modelo mental en 30 segundos

- Micro-framework **PHP MVC propio, sin dependencias** (PHP 8.2+, MySQL). No hay Composer en runtime: el autoloader es propio.
- Tres familias de código en `system/app/`:
  - **`Core/`** — el núcleo MVC (kernel, router, request/response, modelo base, sesión, vista…). Lo usás, casi nunca lo tocás.
  - **`Services/`** — lógica reutilizable y desacoplada (auth, cron, jobs, mail, backup, IA, ecommerce, archivos…). **Aquí vive el 80% de lo que vas a invocar.**
  - **`Jobs/` y `Alerts/`** — handlers de cola y proveedores de alertas (extensibles por contrato).
- Flujo de una petición:
  ```
  system/public/index.php → Env::load → (new App)->run()
     → Session::start → Request::capture
     → config/routes.php registra rutas → Router::dispatch
     → Controller@método → Response::send
  ```
- **Lo que construís vos** (modelos, controladores, vistas, migraciones de tu dominio) NO es core: el actualizador del framework no lo pisa. Generá módulos CRUD con `php system/console/make-module.php` (ver §6).

### Convenciones transversales (valen para casi todo el core)

| Patrón | Qué significa para vos |
|---|---|
| **Servicios estáticos** | Casi todos los `Services` son clases con métodos `static`. Se llaman `App\Services\Foo::bar(...)`, no se instancian. |
| **Best-effort logging** | cron, email, backup, auditoría, IA y notificaciones **nunca rompen** la request si la BD falla: capturan `Throwable` y siguen. No confíes en que un log se escribió. |
| **Fail-open vs fail-closed** | `RateLimiter` es **fail-open** (si la BD falla, deja pasar). `LoginThrottle` cuenta de verdad. Elegí la defensa según criticidad. |
| **Anti SQL-injection** | Los helpers parametrizan **valores**, pero varios interpolan **nombres de tabla/columna/order/limit** sin escapar (`Model::where`, `Paginator`, `GlobalSearch`, `Notifier`). Nunca pases input de usuario a esos parámetros: whitelistealos. |
| **`config/` files** | Muchos servicios leen `system/config/<algo>.php` que hace `return [...]` (rutas, permisos, jobs, prompts, features, menú, api…). La app puede overridear vía `system/config/overrides/`. |
| **Settings con grupos** | Config persistente en BD vía `Settings::get/set/group` por "grupo" (`app`, `mail`, `ai`, `ecommerce`, `flags`, `rbac_roles`…). Secretos con `setSecret` (cifrado AES-256-GCM). |
| **`BASE_PATH` / `PROJECT_PATH`** | `BASE_PATH` = carpeta `system/`. `PROJECT_PATH` = raíz del proyecto (un nivel arriba). Los scripts CLI definen ambas; el `.env` vive en `PROJECT_PATH/.env`. |
| **Respuestas normalizadas** | Los clientes HTTP/IA/ecommerce devuelven arrays `['ok'=>bool, ...]` y **no lanzan**: siempre chequeá `['ok']`. |

### Índice de módulos

1. [Núcleo MVC](#1-núcleo-mvc-systemappcore) — `App, Router, Request, Response, Controller, View, Model, Database, Session, Env, Url, Assets, Icons, autoload`
2. [Seguridad & Auth](#2-seguridad--auth) — `Auth, Crypto, Security, Rbac, Totp, LoginThrottle, ApiToken, PasswordReset, RateLimiter`
3. [Datos, persistencia & config](#3-datos-persistencia--config) — `Settings, AppSettings, Migrator, Paginator, RelationOptions, FeatureFlags, UserTypes, Validator`
4. [Cron & Jobs](#4-cron--jobs) — `CronExpression, CronRunner, ScheduleBuilder, JobQueue, Jobs, Job, LogJob, WebhookDeliverJob` + CLI
5. [Mail, alertas & notificaciones](#5-mail-alertas--notificaciones) — `Mailer, SmtpMailer, EmailQueue, Notifier, AlertService, AlertProvider` (+4 providers)
6. [Backup, deploy, instalación & sistema](#6-backup-deploy-instalación--sistema) — `Backup, Deployer, Installer, ModuleScaffold, Version, Health, Audit, DemoSeeder` + CLI
7. [IA, ecommerce, API & HTTP](#7-ia-ecommerce-api--http) — `AiConnector, PromptLibrary, Http, Webhook, OpenApiGenerator` + familia Ecommerce
8. [Archivos & helpers de UI](#8-archivos--helpers-de-ui) — `FileManager, FileShare, Exporter, Charts, Breadcrumb, Dashboard, GlobalSearch`

---

## 1. Núcleo MVC (`system/app/Core/`)

Micro-framework propio (sin dependencias). PHP 8.2+.

### `autoload.php` — `system/app/Core/autoload.php`
**Qué hace:** Autoloader PSR-4 mínimo (sin Composer): `Core\*` → `BASE_PATH/app/Core/`, `App\*` → `BASE_PATH/app/`.
**API pública:** No expone clases; al incluirse llama `spl_autoload_register`.
**Uso típico:**
```php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/autoload.php';
// new \App\Controllers\HomeController() se carga solo.
```
**Notas:** Requiere `BASE_PATH` definida ANTES de incluirlo. Usa `require` (error fatal si la clase existe pero está rota). No soporta clases sin namespace.

### `App` — `system/app/Core/App.php`
**Qué hace:** Kernel: arranca entorno (timezone, errores, sesión), captura la request, carga rutas, despacha y emite la respuesta con cabeceras de seguridad.
**API pública:** `$app->run(): void` — ejecuta todo el ciclo HTTP (emite con `echo`).
**Uso típico:**
```php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/autoload.php';
\Core\Env::load(BASE_PATH . '/.env');
(new \Core\App())->run();
```
**Notas:** Carga rutas desde `config/routes.php` (debe devolver `function(Router $r){...}`). Ante `Throwable` no capturado: loguea en `storage/logs/app.log` y renderiza `errors/500`. En modo debug muestra stack trace. Depende de `Core\Config`, `Core\Security`, `App\Services\AppSettings::timezone()`.

### `Router` — `system/app/Core/Router.php`
**Qué hace:** Registra rutas con parámetros `{param}` y despacha la `Request` al handler (`'Clase@metodo'`, `[Clase, metodo]` o `Closure`).
**API pública (instancia):** `get/post/put/patch/delete(string $path, mixed $handler): void` · `add(string $method, string $path, mixed $handler): void` · `dispatch(Request $req): Response`.
**Uso típico:**
```php
// config/routes.php
return function (\Core\Router $r): void {
    $r->get('/', 'HomeController@index');
    $r->get('/facturas/{id}', 'FacturaController@show');
    $r->post('/facturas', [\App\Controllers\FacturaController::class, 'store']);
};
```
**Notas:** `{param}` compila a regex `([^/]+)` (no cruza `/`). El handler recibe SIEMPRE `Request` como primer argumento y luego los params en orden: `metodo(Request $req, $id, ...)`. Para `'Clase@metodo'` antepone `App\Controllers\`. Sin coincidencia → `errors/404`.

### `Request` — `system/app/Core/Request.php`
**Qué hace:** Encapsula la petición HTTP entrante (inmutable, factory).
**API pública:** `Request::capture(): self` (static) · `method()` · `path()` · `input(key,$default=null)` · `query(key,$default=null)` · `all()` · `only(array $keys)` · `has(key)` · `header(key,$default=null)` · `isAjax()` · `wantsJson()`.
**Uso típico:**
```php
public function store(\Core\Request $req): \Core\Response {
    $data = $req->only(['cliente_id', 'total']);
    if (!$req->has('total')) return \Core\Response::json(['error' => 'total requerido'], 422);
    return $req->wantsJson() ? \Core\Response::json($data) : $this->redirect('/facturas');
}
```
**Notas:** Constructor `private` (usá `capture()`). Soporta method override por `$_POST['_method']` (PUT/PATCH/DELETE sobre POST). Decodifica body JSON si `Content-Type: application/json`. `input()` busca en body y luego query.

### `Response` — `system/app/Core/Response.php`
**Qué hace:** Representa y emite la respuesta HTTP.
**API pública:** `new Response(string $content='', int $status=200, array $headers=[])` · `Response::html($c,$status=200)` · `Response::json($data,$status=200)` · `Response::redirect($to,$status=302)` · `Response::download($content,$filename,$contentType='application/octet-stream')` · `$res->header($n,$v): self` · `$res->status($s): self` · `$res->send(): void`.
**Uso típico:**
```php
return \Core\Response::json(['ok' => true], 201)->header('X-Total', '5');
return \Core\Response::download($csv, 'reporte.csv', 'text/csv');
```
**Notas:** `json()` usa `JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES`. `download()` sanea el filename a `[A-Za-z0-9._-]`. `header()`/`status()` son encadenables.

### `Controller` — `system/app/Core/Controller.php`
**Qué hace:** Clase base abstracta; helpers `protected` para construir respuestas.
**API pública (protected, para subclases):** `view(string $view, array $data=[], ?string $layout='layouts/app'): Response` · `json($data,$status=200)` · `redirect($to)` (siempre 302) · `abort(int $status, string $message='')`.
**Uso típico:**
```php
namespace App\Controllers;
use Core\{Controller, Request, Response};
final class FacturaController extends Controller {
    public function show(Request $req, string $id): Response {
        $f = \App\Models\Factura::find((int) $id);
        return $f ? $this->view('facturas/show', ['f' => $f]) : $this->abort(404, 'No existe');
    }
}
```
**Notas:** `abstract`. El Router lo instancia con `new Clase()` (constructor sin args).

### `View` — `system/app/Core/View.php`
**Qué hace:** Renderiza vistas PHP planas con layout y patrón de **overrides** (la app puede sobrescribir cualquier vista del core).
**API pública (static):** `render(string $view, array $data=[], ?string $layout='layouts/app', int $status=200): Response` · `partial(string $view, array $data=[]): string` · `exists(string $view): bool` · `e(mixed $value): string` (escape HTML).
**Uso típico:**
```php
return \Core\View::render('facturas/index', ['facturas' => $rows]);
// en la vista:  <h1><?= \Core\View::e($title) ?></h1>
//               <?= \Core\View::partial('partials/factura-card', ['f' => $f]) ?>
```
**Notas:** Resolución: PRIMERO `app/Views/overrides/{view}.php`, LUEGO `app/Views/{view}.php` (poné personalizaciones en `overrides/` para que el update no las pise). El layout recibe el HTML en `$content`. Las claves de `$data` se vuelven variables locales (`extract`). Lanza `RuntimeException` si no resuelve. Gotcha: `exists()` NO mira `overrides/`.

### `Model` — `system/app/Core/Model.php`
**Qué hace:** Modelo base ligero sobre `Database`; CRUD por tabla devolviendo arrays asociativos.
**API pública (static):** `all(string $orderBy='')` · `find(int|string $id): ?array` · `findBy(string $col, $value): ?array` · `where(string $col, $value): array` · `create(array $data): int` (lastInsertId) · `update(int|string $id, array $data): int` (filas afectadas) · `delete(int|string $id): int` · `tableName(): string`.
**Propiedades en subclase:** `protected static string $table` (obligatoria) · `$primaryKey='id'` · `$fillable=[]` (vacío = todas las columnas).
**Uso típico:**
```php
namespace App\Models;
use Core\Model;
final class Factura extends Model {
    protected static string $table = 'facturas';
    protected static array $fillable = ['cliente_id', 'total', 'estado'];
}
$id = Factura::create(['cliente_id' => 3, 'total' => 1500]);
$f  = Factura::find($id);
Factura::update($id, ['estado' => 'pagada']);
```
**Notas:** **Gotcha de seguridad:** `$orderBy`, el nombre de columna de `findBy`/`where` y `$primaryKey` se concatenan SIN escapar → nunca pases input de usuario crudo (SQLi). `create/update` filtran por `$fillable`. `update` reserva la clave interna `:__pk`.

### `Database` — `system/app/Core/Database.php`
**Qué hace:** Conexión PDO única (singleton) + helpers de consulta preparada.
**API pública (static):** `connection(): PDO` · `run($sql,$params=[]): PDOStatement` · `select($sql,$params=[]): array` · `selectOne($sql,$params=[]): ?array` · `insert($sql,$params=[]): int` (lastInsertId) · `affected($sql,$params=[]): int` (rowCount).
**Uso típico:**
```php
$rows = \Core\Database::select('SELECT * FROM facturas WHERE estado = ?', ['pagada']);
$one  = \Core\Database::selectOne('SELECT * FROM facturas WHERE id = ?', [7]);
```
**Notas:** Lee `config/database.php` (`host, port, name, user, pass` + opc. `driver=mysql`, `charset=utf8mb4`). PDO con `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`. Acepta params posicionales (`?`) o nombrados (`:x`).

### `Session` — `system/app/Core/Session.php`
**Qué hace:** Wrapper de sesión PHP con flash y CSRF.
**API pública (static):** `start()` · `get(k,$d=null)` · `set(k,$v)` · `has(k)` · `remove(k)` · `regenerate()` · `destroy()` · `flash(k,$v)` · `getFlash(k,$d=null)` (lee y BORRA) · `csrf(): string` · `verifyCsrf(?string $token): bool`.
**Uso típico:**
```php
\Core\Session::regenerate();
\Core\Session::set('user_id', $user['id']);
\Core\Session::flash('success', 'Guardado');
if (!\Core\Session::verifyCsrf($req->input('_csrf'))) return \Core\Response::html('CSRF inválido', 419);
// form:  <input type="hidden" name="_csrf" value="<?= \Core\View::e(\Core\Session::csrf()) ?>">
```
**Notas:** Cookie `httponly`, `samesite=Lax`. `verifyCsrf` usa `hash_equals` (constante en tiempo). `regenerate` cambia el id pero NO el token CSRF.

### `Env` — `system/app/Core/Env.php`
**Qué hace:** Carga `.env` (sin dependencias) y lee variables con casteo de literales.
**API pública (static):** `load(string $path): void` · `get(string $key, $default=null): mixed`.
**Uso típico:**
```php
\Core\Env::load(BASE_PATH . '/.env');
$debug = \Core\Env::get('APP_DEBUG', false); // bool
```
**Notas:** Soporta comentarios `#`, comillas, expansión `${VAR}`. **No sobrescribe** variables ya presentes en el entorno. Castea solo `true/false/null`; el resto es string.

### `Url` — `system/app/Core/Url.php`
**Qué hace:** Construye URLs relativas al directorio público (funciona en subdirectorio).
**API pública (static):** `base(): string` · `to(string $path='/'): string`.
**Uso típico:**
```php
<a href="<?= \Core\Url::to('/facturas') ?>">Facturas</a>
```
**Notas:** Depende de `$_SERVER['SCRIPT_NAME']` (vacío en CLI). No genera URLs absolutas (sin host/esquema).

### `Assets` — `system/app/Core/Assets.php`
**Qué hace:** Resuelve assets de frontend (CSS/JS) en modo local o CDN de fallback y genera el `<head>`.
**API pública (static):** `useLocal(): bool` · `css()` · `alpine()` · `chart()` · `head(bool $withChart=false, bool $withAlpine=true): string`.
**Uso típico:**
```php
// en layouts/app.php, dentro de <head>:
<?= \Core\Assets::head(withChart: true) ?>
```
**Notas:** Modo por `.env` `ASSETS_MODE` (`local|cdn`); cae a CDN si no existe `public/assets/css/app.css`. Incluye `theme.css` y script anti-flash de dark mode.

### `Icons` — `system/app/Core/Icons.php`
**Qué hace:** Renderiza iconos SVG inline (heroicons outline).
**API pública (static):** `render(string $name, string $class='h-5 w-5'): string`.
**Iconos:** `home, cube, tag, clipboard, folder, users, user-circle, user-group, shield, list, adjustments, envelope, cpu, key, bolt, server, archive, heart, clock, queue, grid`.
**Uso típico:** `<?= \Core\Icons::render('users', 'h-6 w-6 text-blue-500') ?>`
**Notas:** Si el nombre no existe → fallback `grid`. Para añadir iconos hay que editar la constante `PATHS` (no extensible en runtime).

---

## 2. Seguridad & Auth

### `Auth` — `system/app/Core/Auth.php`
**Qué hace:** Autenticación de administradores contra `admin_users` vía sesión; `password_verify`.
**API pública (static):** `attempt(string $login, string $password): ?array` (verifica + inicia sesión, devuelve user sin `password`) · `verifyCredentials($login,$password): ?array` (verifica sin iniciar sesión, para flujos 2FA) · `login(int $userId): void` · `check(): bool` · `id(): ?int` · `user(): ?array` (recarga de BD) · `logout(): void` · `hash(string $password): string`.
**Uso típico:**
```php
use Core\Auth;
if (Auth::attempt($login, $password)) { /* dashboard */ }
$current = Auth::user();
```
**Notas:** Tabla `admin_users(id,name,email,username,password,role,active)`. El lookup es `email` OR `username`, exige `active=1` en login. No incluye throttle: combiná con `LoginThrottle`.

### `Crypto` — `system/app/Core/Crypto.php`
**Qué hace:** Cifrado simétrico autenticado AES-256-GCM para secretos en reposo, clave derivada de `APP_KEY`.
**API pública (static):** `generateKey(): string` · `key(): ?string` · `isEncrypted(string $v): bool` · `encrypt(string $plain): string` (`enc::...` o plano si no hay clave) · `decrypt(string $payload): ?string` · `maybeDecrypt(?string $v): ?string`.
**Uso típico:**
```php
$stored = \Core\Crypto::encrypt($secret);
$plain  = \Core\Crypto::maybeDecrypt($stored);
```
**Notas:** `.env` `APP_KEY` (`base64:<32 bytes>`), generable con `php system/console/key.php`. **Gotcha crítico:** `encrypt()` cae a texto plano si falta `APP_KEY` → verificá `Crypto::key() !== null` antes de confiar en que algo quedó cifrado.

### `Security` — `system/app/Core/Security.php`
**Qué hace:** Genera el set de cabeceras de seguridad HTTP (CSP, X-Frame-Options, HSTS…).
**API pública (static):** `headers(bool $https=false): array` · `isHttps(): bool`.
**Uso típico:**
```php
foreach (\Core\Security::headers(\Core\Security::isHttps()) as $n => $v) header("$n: $v");
```
**Notas:** Lo aplica `App::run()` automáticamente. La CSP cambia según `Assets::useLocal()` (con CDN permite `unsafe-inline`/`unsafe-eval`). HSTS solo si `$https=true`.

### `Rbac` — `system/app/Services/Rbac.php`
**Qué hace:** Control de acceso por roles: permisos por defecto + overrides de rol (settings) + overrides por usuario (BD).
**API pública (static):** `roles()` · `catalog()` · `permissionsFor(string $role): array` (`superadmin`→`['*']`) · `setRolePermissions($role,$perms)` · `userOverrides(int $userId): array` · `setUserPermission(int $userId, string $permission, ?bool $effect)` (`true`=allow, `false`=deny, `null`=heredar) · `can(string $permission, ?array $user=null): bool`.
**Uso típico:**
```php
use App\Services\Rbac;
if (!Rbac::can('facturas.edit')) abort(403);
Rbac::setUserPermission($userId, 'reportes.ver', false); // deny explícito
```
**Notas:** Config `config/permissions.php` (`roles`, `catalog`). Tablas `user_permissions`, settings grupo `rbac_roles`. Precedencia: override de usuario > rol; `*` concede todo. `superadmin` hardcodeado con todo.

### `Totp` — `system/app/Services/Totp.php`
**Qué hace:** TOTP (RFC 6238) sin dependencias: 6 dígitos, periodo 30s; compatible con Google Authenticator/Authy.
**API pública (static):** `generateSecret(int $bytes=20): string` · `code(string $secret, ?int $time=null): string` · `verify(string $secret, string $code, ?int $time=null, int $window=1): bool` · `uri(string $secret, string $label, string $issuer): string` · `base32encode/base32decode`.
**Uso típico:**
```php
use App\Services\Totp;
$secret = Totp::generateSecret();
$qrUri  = Totp::uri($secret, $user['email'], 'nsSkeleton');
$ok     = Totp::verify($secret, $codeIngresado);
```
**Notas:** Guardá el secreto cifrado con `Crypto`. `verify` rechaza lo que no sea `\d{6}`; `window=1` tolera ±30s.

### `LoginThrottle` — `system/app/Services/LoginThrottle.php`
**Qué hace:** Limita intentos de login: 5 fallos → bloqueo 900s (15 min). Se reinicia con éxito o al expirar.
**API pública (static):** `tooManyAttempts(string $id): bool` · `secondsRemaining(string $id): int` · `hit(string $id): void` · `clear(string $id): void`.
**Uso típico:**
```php
use App\Services\LoginThrottle;
$key = strtolower($login) . '|' . $ip;
if (LoginThrottle::tooManyAttempts($key)) abort('Espera ' . LoginThrottle::secondsRemaining($key) . 's');
Auth::attempt($login, $pass) ? LoginThrottle::clear($key) : LoginThrottle::hit($key);
```
**Notas:** Tabla `login_attempts`. El identificador lo elegís vos (define la granularidad: por email, por email+IP…). Case-insensitive.

### `ApiToken` — `system/app/Services/ApiToken.php`
**Qué hace:** Tokens Bearer de API para admins; persiste solo el hash SHA-256, el token en claro se ve UNA vez.
**API pública (static):** `generate(int $adminId, string $name, string $scopes='read,write'): string` · `normalizeScopes(string): string` · `resolve(string $token): ?array` (`{admin,scopes:[],token_id}`) · `validate(string $token): ?array` (solo admin) · `all(): array` · `revoke(int $id): void`.
**Uso típico:**
```php
use App\Services\ApiToken;
$plain = ApiToken::generate($adminId, 'CI deploy', 'read,write'); // mostrar UNA vez
$ctx = ApiToken::resolve($bearer);
if ($ctx === null) abort(401);
if (!in_array('write', $ctx['scopes'], true)) abort(403);
```
**Notas:** Tabla `api_tokens` + `admin_users`. Prefijo `nsk_`. Cada validación actualiza `last_used_at`. No hay expiración.

### `PasswordReset` — `system/app/Services/PasswordReset.php`
**Qué hace:** Tokens de recuperación de un solo uso, TTL 1h; persiste solo el hash.
**API pública (static):** `createToken(string $email): ?string` (null si el email no existe) · `valid(string $email, string $token): bool` · `consume(string $email, string $token, string $newPassword): bool`.
**Uso típico:**
```php
use App\Services\PasswordReset;
$token = PasswordReset::createToken($email);
if ($token) Mailer::send($email, 'Reset', "...?token=$token&email=$email");
if (PasswordReset::consume($email, $token, $newPass)) { /* OK */ }
```
**Notas:** Tabla `password_resets`. **Gotcha de seguridad:** no expongas que `createToken` devolvió `null` (enumeración de cuentas): respondé igual exista o no.

### `RateLimiter` — `system/app/Services/RateLimiter.php`
**Qué hace:** Rate-limit genérico por clave con ventana fija.
**API pública (static):** `hit(string $key, int $limit, int $window=60): array` → `{allowed,remaining,limit}`.
**Uso típico:**
```php
$r = \App\Services\RateLimiter::hit('api:' . $ip, 100, 60);
if (!$r['allowed']) abort(429);
```
**Notas:** Tabla `rate_limits`. **Es FAIL-OPEN:** si la BD falla, deja pasar (`allowed=true`). No lo uses como única defensa de algo crítico.

---

## 3. Datos, persistencia & config

### `Settings` — `system/app/Services/Settings.php`
**Qué hace:** Config clave/valor agrupada (tabla `settings`), con caché por petición; secretos cifrados automáticamente.
**API pública (static):** `get(string $key, $default=null): mixed` · `group(string $group): array` (descifrado, indexado por clave corta) · `set(string $key, $value, string $group='general'): void` · `setSecret(string $key, $value, string $group='general'): void`.
**Uso típico:**
```php
use App\Services\Settings;
Settings::set('app.name', 'Facturador', 'app');
Settings::setSecret('mail.smtp_pass', $pass, 'mail');
$mail = Settings::group('mail'); // ['smtp_pass' => '<descifrado>', ...]
```
**Notas:** UPSERT por clave única. Caché viva solo en la petición; `set` la resetea.

### `AppSettings` — `system/app/Services/AppSettings.php`
**Qué hace:** Accesores tipados de la config general (grupo `app`) con fallback a `.env`; resiliente sin BD.
**API pública (static):** `name(): string` · `tagline(): string` · `timezone(string $fallback='UTC'): string` · `logo(): ?string`.
**Uso típico:**
```php
date_default_timezone_set(\App\Services\AppSettings::timezone('America/Argentina/Buenos_Aires'));
```
**Notas:** Lee `Settings::group('app')` en try/catch → si la BD falla, cae a env (`APP_NAME`, `APP_TIMEZONE`).

### `Migrator` — `system/app/Services/Migrator.php`
**Qué hace:** Migraciones SQL por archivos con rollback; estado en `schema_migrations`.
**API pública (static):** `dir()` · `ensureTable()` · `files(?dir)` · `parse(path): {up,down}` · `appliedNames()` · `migrate(?dir): array` · `rollback(int $steps=1, ?dir): array` · `status(?dir): array`.
**Uso típico:**
```php
\App\Services\Migrator::migrate();
\App\Services\Migrator::rollback(1);
```
**Notas:** Archivos `*.sql` ordenados alfabéticamente. El marcador de línea `-- @DOWN` separa `up` de `down`. El SQL se ejecuta con PDO multi-statement (DDL de archivos del repo, no input de usuario). Normalmente se invoca vía `php system/database/migrate.php`.

### `Paginator` — `system/app/Services/Paginator.php`
**Qué hace:** Paginación + búsqueda LIKE reutilizable. `meta()` es matemática pura; `paginate()` consulta la BD.
**API pública (static):** `const PER_PAGE=15` · `meta(int $total, int $page, int $perPage): array` · `paginate(string $table, array $opts=[]): array`. Opciones: `page, perPage, order('id DESC'), search, searchable(array de columnas), filter(SQL fijo)`.
**Uso típico:**
```php
$result = \App\Services\Paginator::paginate('facturas', [
    'page' => (int)($_GET['page'] ?? 1),
    'search' => $_GET['q'] ?? '', 'searchable' => ['numero', 'cliente'],
    'order' => 'created_at DESC', 'filter' => 'anulada = 0',
]); // $result['rows'], ['pages'], ['hasNext']...
```
**Notas:** **Gotcha de seguridad:** `table`, `order`, `filter` y `searchable` se interpolan directo en el SQL (solo el término va parametrizado). Whitelisteá esos campos; nunca input de usuario crudo.

### `RelationOptions` — `system/app/Services/RelationOptions.php`
**Qué hace:** Resuelve opciones `id => etiqueta` de una tabla relacionada (FK belongsTo), eligiendo la mejor columna-etiqueta.
**API pública (static):** `labelColumn(string $table): string` · `forTable(string $table): array` (`id=>etiqueta`, máx 1000, ordenado).
**Uso típico:**
```php
$opciones = \App\Services\RelationOptions::forTable('clientes'); // [1 => 'ACME SA', ...] para un <select>
```
**Notas:** Valida la tabla con `^[a-z0-9_]+$`. Columnas candidatas: `nombre, name, titulo, title, razon_social, descripcion, email`. Resiliente (try/catch).

### `FeatureFlags` — `system/app/Services/FeatureFlags.php`
**Qué hace:** Feature flags con defaults en `config/features.php` + override en runtime (settings grupo `flags`).
**API pública (static):** `defaults(): array` · `all(): array` · `enabled(string $name): bool` · `set(string $name, bool $on): void`.
**Uso típico:**
```php
if (\App\Services\FeatureFlags::enabled('factura_electronica')) { /* ... */ }
\App\Services\FeatureFlags::set('factura_electronica', true);
```
**Notas:** Solo aparecen flags presentes en los defaults. Override en settings se lee con try/catch (sin BD → defaults).

### `UserTypes` — `system/app/Services/UserTypes.php`
**Qué hace:** Registro de tipos de usuario (login unificado), definidos en `config/user_types.php`.
**API pública (static):** `all(): array` (`clave=>etiqueta`) · `label(string $key): string` · `isValid(string $key): bool` · `current(): ?string`.
**Uso típico:**
```php
if (!\App\Services\UserTypes::isValid($input['user_type'])) { /* rechazar */ }
$tipo = \App\Services\UserTypes::current(); // 'admin' | null
```
**Notas:** Fallback `['admin' => 'Administrador']`. `current()` lee `Auth::user()['user_type']`.

### `Validator` — `system/app/Services/Validator.php`
**Qué hace:** Validación simple por reglas; devuelve `campo => primer error` (vacío = válido).
**API pública (static):** `make(array $data, array $rules, ?string $table=null, ?int $ignoreId=null): array`.
**Reglas:** `required, email, numeric, integer, unique` (las desconocidas se ignoran; salvo `required`, las demás pasan si el valor está vacío).
**Uso típico:**
```php
$errors = \App\Services\Validator::make($_POST, [
    'numero' => ['required'],
    'email'  => ['required', 'email', 'unique'],
    'total'  => ['numeric'],
], 'facturas', $editId);
if ($errors === []) { /* guardar */ }
```
**Notas:** Un error por campo (el primero). `unique` valida tabla/columna con regex y consulta parametrizada (try/catch). Mensajes en español.

---

## 4. Cron & Jobs

Hay **dos mecanismos distintos** que conviene no confundir:
- **Cron** (`cron_tasks`): tareas programadas por expresión cron. El `command` puede ser shell o un **job interno** con prefijo `job:` (closures de `config/jobs.php`, vía `Jobs`).
- **JobQueue** (`jobs`): cola de trabajos asíncronos con reintentos; handlers son **clases** que implementan `App\Jobs\Job` (mapeadas en `config/job_handlers.php`).

### `CronExpression` — `system/app/Services/CronExpression.php`
**Qué hace:** Evalúa expresiones cron de 5 campos (`min hora dom mes dow`). Soporta `*`, `*/n`, `a-b`, `a-b/n`, listas. Pura, sin I/O.
**API pública (static):** `isValid(string $expr): bool` · `isDue(string $expr, DateTimeImmutable $dt): bool` · `nextRunAfter(string $expr, DateTimeImmutable $from): ?DateTimeImmutable`.
**Uso típico:**
```php
\App\Services\CronExpression::isDue('0 9 * * 1', new DateTimeImmutable()); // ¿lunes 09:00?
```
**Notas:** No soporta nombres (JAN/MON) ni `@hourly`, `?`, `L`, `#`. `nextRunAfter` es fuerza bruta (minuto a minuto hasta ~366 días).

### `CronRunner` — `system/app/Services/CronRunner.php`
**Qué hace:** Ejecuta las tareas de `cron_tasks` vencidas, con lock anti-solapamiento y throttle, y registra en `cron_runs`.
**API pública (static):** `runDue(DateTimeImmutable $now): array` (resúmenes) · `runTask(array $task, ?DateTimeImmutable $now=null): array` ("ejecutar ahora").
**Uso típico:**
```php
\App\Services\CronRunner::runDue(new DateTimeImmutable('now')); // lo llama system/cron/run.php
$res = \App\Services\CronRunner::runTask(\App\Models\CronTask::find($id)); // puntual desde el panel
```
**Notas:** Lockfile `storage/cache/cron.lock` (si está tomado, `runDue` → `[]`). Settings `cron.min_interval`/`cron.last_tick`. Salida truncada a 5000 chars. Comandos shell con `exec($command.' 2>&1')` → riesgo de inyección si `command` viene de input no confiable.

### `ScheduleBuilder` — `system/app/Services/ScheduleBuilder.php`
**Qué hace:** Genera cron desde presets amigables y describe una expresión en español (para UI no técnica). Pura.
**API pública (static):** `fromPreset(array $p): string` (`type`: `minutes|hourly|daily|weekly|monthly`) · `describe(string $expr): string`.
**Uso típico:**
```php
\App\Services\ScheduleBuilder::fromPreset(['type'=>'daily','hour'=>9,'minute'=>30]); // "30 9 * * *"
\App\Services\ScheduleBuilder::describe('0 8 * * 1'); // "Los Lunes a las 08:00"
```
**Notas:** `describe` solo reconoce patrones simples; lo complejo vuelve crudo. Día 0 = Domingo.

### `JobQueue` — `system/app/Services/JobQueue.php`
**Qué hace:** Cola persistida en `jobs` con reintentos, backoff lineal y lock optimista.
**API pública (static):** `push(string $handler, array $payload=[], int $maxAttempts=3, int $delaySeconds=0): int` · `work(int $limit=25): array` · `retry(int $id)` · `forget(int $id)` · `stats(): array` · `recent(int $limit=30): array`.
**Uso típico:**
```php
use App\Services\JobQueue;
JobQueue::push('log', ['message' => 'factura #42 emitida'], maxAttempts: 5, delaySeconds: 30);
JobQueue::work(50); // lo hace queue.php o el job interno queue:work
```
**Registrar un handler:** en `config/job_handlers.php`: `'mi_handler' => \App\Jobs\MiJob::class,` (la clase implementa `App\Jobs\Job`).
**Notas:** Estados `pending → processing → done|failed`. Backoff = `attempts*60`s. Lock optimista (seguro multi-worker), pero un job muerto en `processing` no se auto-recupera. Errores truncados a 500 chars.

### `Jobs` — `system/app/Services/Jobs.php`
**Qué hace:** Jobs internos del cron: closures en `config/jobs.php` (los que invoca `CronRunner` con prefijo `job:`). Distintos de `JobQueue`.
**API pública (static):** `all(): array` · `names(): array` · `has(string $name): bool` · `run(string $name): array` (`{ok,output,code}`).
**Uso típico:**
```php
// config/jobs.php
return ['queue:work' => fn() => \App\Services\JobQueue::work(50)['processed'] . ' jobs'];
// crear tarea cron con command = 'job:queue:work'
```
**Notas:** Captura `Throwable` (no rompe). No confundir con `App\Jobs\Job` (clases con `handle()`).

### `Job` (interfaz) — `system/app/Jobs/Job.php`
**Qué hace:** Contrato de los handlers de `JobQueue`.
**API:** `handle(array $payload): void` — **lanza excepción si falla** (→ reintento); si retorna, el job es `done`.
**Uso típico:**
```php
namespace App\Jobs;
final class EmitirFacturaJob implements Job {
    public function handle(array $payload): void {
        if (empty($payload['factura_id'])) throw new \RuntimeException('falta factura_id');
        // ... trabajo ...
    }
}
```

### `LogJob` — `system/app/Jobs/LogJob.php`
**Qué hace:** Job de ejemplo: escribe una línea con timestamp en `logs/jobs-demo.log`. Implementa `Job`.
**Notas:** Escritura silenciosa (`@file_put_contents`) → siempre termina `done`. Útil como plantilla.

### `WebhookDeliverJob` — `system/app/Jobs/WebhookDeliverJob.php`
**Qué hace:** Entrega un webhook: POST JSON firmado con HMAC; lanza si la respuesta no es 2xx (→ reintento). Implementa `Job`.
**Payload:** `url` (obligatorio), `event`, `data`, `webhook_id` (para resolver el `secret`).
**Notas:** Usa `Http::postRaw`, `Webhook::sign`, tabla `webhooks`. Lo encola `Webhook::dispatch` (handler `webhook:deliver`).

### CLI — `system/console/queue.php` y `system/cron/run.php`
```bash
php system/console/queue.php work --max=50     # drena la cola de jobs
php system/cron/run.php                          # tick del cron (1×/min desde el SO)
```
- `queue.php`: solo subcomando `work` (`--max=N`, default 25). Aborta si no es CLI.
- `run.php`: despachador de cron. Agendalo en crontab/Programador de tareas **cada minuto**; el throttle/lock real vive en `CronRunner::runDue()`.
```bash
# crontab Linux:
* * * * * php /ruta/skeleton/system/cron/run.php >> /ruta/skeleton/system/storage/logs/cron.log 2>&1
```

---

## 5. Mail, alertas & notificaciones

### `Mailer` — `system/app/Services/Mailer.php`
**Qué hace:** Facade de email: arma `SmtpMailer` desde `Settings`/`.env`, renderiza plantillas, envía síncrono (registrando en `email_log`) o encola.
**API pública (static):** `fromSettings(): SmtpMailer` · `render(string $view, array $data=[], string $layout='emails/layout'): string` · `queue(string $to, string $subject, string $htmlBody): int` · `send(string $to, string $subject, string $htmlBody): array` (`{ok,error,log}`).
**Uso típico:**
```php
use App\Services\Mailer;
$html = Mailer::render('factura', ['f' => $factura]); // app/Views/emails/factura.php
$res  = Mailer::send($cliente['email'], 'Tu factura', $html);
if (!$res['ok']) { /* $res['error'] */ }
Mailer::queue($cliente['email'], 'Tu factura', $html); // diferido
```
**Notas:** Settings grupo `mail` con fallback env (`MAIL_HOST`, `MAIL_PORT=587`, `MAIL_USER`, `MAIL_PASS`, `MAIL_ENCRYPTION=tls`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`). No lanza por fallo de envío: revisá `['ok']`. `render` requiere las vistas `emails/$view` y `emails/layout`.

### `SmtpMailer` — `system/app/Services/SmtpMailer.php`
**Qué hace:** Cliente SMTP minimalista sobre sockets (sin dependencias): `AUTH LOGIN`, `tls`(STARTTLS)/`ssl`/`none`. Nunca lanza.
**API pública:** `__construct(host,port,user,pass,encryption='tls',fromAddress='',fromName='',timeout=15)` · `send(to,subject,htmlBody): array{ok,error,log}`.
**Uso típico:**
```php
$smtp = new \App\Services\SmtpMailer('smtp.gmail.com', 587, 'me@gmail.com', 'app-pass', 'tls', 'me@gmail.com', 'Mi App');
$res  = $smtp->send('dest@x.com', 'Hola', '<b>contenido</b>');
```
**Notas:** Cuerpo siempre `text/html; charset=UTF-8` en base64. Asunto/nombre en MIME `=?UTF-8?B?...?=`. Un destinatario por llamada. Normalmente lo armás vía `Mailer::fromSettings()`.

### `EmailQueue` — `system/app/Services/EmailQueue.php`
**Qué hace:** Cola persistida de emails (`email_queue`); `process()` la drena (job `email:queue`), reintenta hasta 3 veces.
**API pública (static):** `push(to,subject,body): int` · `process(int $limit=20): array{processed,sent,failed}` · `recent(int $limit=100): array` · `counts(): array`.
**Uso típico:**
```php
\App\Services\EmailQueue::push($cliente['email'], 'Asunto', '<p>cuerpo</p>');
\App\Services\EmailQueue::process(20); // desde el job de cron
```
**Notas:** Cada envío de `process()` usa `Mailer::send` (y escribe también en `email_log`). Estados `pending|sent|failed`.

### `Notifier` — `system/app/Services/Notifier.php`
**Qué hace:** Notificaciones in-app por usuario (campanita). Best-effort (atrapa `Throwable`, nunca rompe).
**API pública (static):** `notify(int $userId, string $title, ?string $body=null, ?string $url=null): void` · `notifyAll(title, body=null, url=null, ?int $exceptUserId=null): void` · `unreadCount(int $userId): int` · `forUser(int $userId, bool $onlyUnread=false, int $limit=20): array` · `markRead(int $id, int $userId)` · `markAllRead(int $userId)`.
**Uso típico:**
```php
use App\Services\Notifier;
Notifier::notify($userId, 'Factura aprobada', 'La factura #42 fue aprobada.', '/admin/facturas/42');
Notifier::notifyAll('Nuevo cliente', null, '/admin/clientes', exceptUserId: $actorId);
```
**Notas:** Tablas `notifications`, `admin_users`. Gotcha: `forUser` interpola `$limit` directo en el SQL → no pasar input no confiable.

### `AlertService` — `system/app/Services/AlertService.php`
**Qué hace:** Agrega alertas computadas de todos los `AlertProvider` registrados, ordenadas por severidad (`danger`<`warning`<`info`).
**API pública (static):** `providers(): array` · `all(): array` · `sort(array): array` · `count(): int`.
**Uso típico:**
```php
$alerts = \App\Services\AlertService::all();  // danger primero
$badge  = \App\Services\AlertService::count();
```
**Notas:** Lee `config/alert_providers.php` (`return [Provider::class, ...]`). Un provider que lanza se ignora. `count()` re-ejecuta todas las queries (sin caché).

### `AlertProvider` (interfaz) — `system/app/Alerts/AlertProvider.php`
**Qué hace:** Contrato de un proveedor de alertas.
**API:** `key(): string` · `collect(): array` → items `{severity:danger|warning|info, title, detail, url, icon}` (`[]` si no hay nada).
**Registrar uno nuevo:**
```php
namespace App\Alerts\Providers;
use App\Alerts\AlertProvider;
final class FacturasVencidasProvider implements AlertProvider {
    public function key(): string { return 'facturas_vencidas'; }
    public function collect(): array {
        $n = \Core\Database::selectOne("SELECT COUNT(*) c FROM facturas WHERE vencimiento < NOW() AND estado='impaga'")['c'] ?? 0;
        return $n ? [['severity'=>'warning','title'=>"$n facturas vencidas",'detail'=>'...','url'=>'/admin/facturas','icon'=>'⏰']] : [];
    }
}
// registrar en config/alert_providers.php
```

### Providers incluidos (en `system/app/Alerts/Providers/`)
| Provider | Severidad | Dispara cuando | Tabla |
|---|---|---|---|
| `FailedJobsAlertProvider` | `danger` | hay jobs `status='failed'` | `jobs` |
| `FailedCronAlertProvider` | `warning` | tarea cron activa con `last_status='failed'` | `cron_tasks` |
| `OldBackupAlertProvider` | `warning` | nunca hubo backup o el último > 7 días | `backup_log` |
| `PendingQueueAlertProvider` | `warning` | ≥ 20 jobs `pending` (worker caído/atrasado) | `jobs` |

---

## 6. Backup, deploy, instalación & sistema

### `Backup` — `system/app/Services/Backup.php`
**Qué hace:** Backup/restore en PHP puro (sin `mysqldump`): base a `.sql`, archivos a `.zip`, en `system/storage/backups`.
**API pública (static):** `dir()` · `createDatabaseBackup(): array{ok,file?,size?,error?}` · `createFilesBackup(): array` · `restoreDatabase(string $name): array` · `list(): array` · `delete(string $name): bool` · `cleanup(int $days): int` · `safePath(string $name): ?string`.
**Uso típico:**
```php
use App\Services\Backup;
$r = Backup::createDatabaseBackup();
Backup::cleanup(30); // retención 30 días
```
**Notas:** Requiere `ZipArchive` para archivos. Registra en `backup_log` (best-effort). `restoreDatabase` ejecuta el SQL completo de un `.sql` de la carpeta (sin transacción). `safePath` neutraliza traversal con `basename`.

### `Deployer` — `system/app/Services/Deployer.php`
**Qué hace:** Deploy por FTP o git leyendo credenciales del `.env`. Opt-in: nunca sube/pushea por sí solo.
**API pública (static):** `config(): array` · `ftpConfigured(): bool` · `filesToDeploy(): array` · `gitCommands(): array` · `ftpDeploy(?callable $log=null): array{ok,uploaded,error}`.
**Uso típico:**
```php
if (\App\Services\Deployer::ftpConfigured())
    \App\Services\Deployer::ftpDeploy(fn($f) => print("subido: $f\n"));
```
**Notas:** `.env`: `FTP_HOST, FTP_PORT, FTP_USER, FTP_PASS, FTP_REMOTE_PATH, GIT_REMOTE_URL, DEPLOY_BRANCH`. Requiere extensión FTP. No borra remotos huérfanos (solo sube). Git siempre a `origin`.

### `Installer` — `system/app/Services/Installer.php`
**Qué hace:** Parte mecánica del instalador: de respuestas Q&A genera `.env` y secciones de doc. Pura (la interacción la maneja el skill `installer`/`/instalar`).
**API pública (static):** `buildEnv(array $answers): string` · `stackDoc(array $answers): string` · `summary(array $answers): array`.
**Notas:** Toma `.env.example` como plantilla y reemplaza claves existentes (`APP_NAME, APP_URL, DB_*, AI_PROVIDER`). No genera `APP_KEY` (eso es `console/key.php`). El write real lo hace `console/install.php`.

### `ModuleScaffold` — `system/app/Services/ModuleScaffold.php`
**Qué hace:** Helpers puros de parsing y mapeo de tipos para el generador CRUD (testeable). La generación de archivos la hace `console/make-module.php`.
**API pública (static):** `studly/snake/label` · `sqlType(string): string` · `inputType(string): string` · `isValidType(string): bool` · `parseFields(string): array` · `parseRelations(string): array` · `parseRules(string): array`.
**Notas:** Tipos lógicos: `string, text, int, decimal, bool, date, datetime, fk`. FK como `campo:fk:tabla`; reglas inline como `email:string:required,email,unique`.

### `Version` — `system/app/Services/Version.php`
**Qué hace:** Versionado dual: framework (`core`, archivo `VERSION`) vs app (`app`).
**API pública (static):** `core(): string` · `coreName(): string` (`'nsSkeleton'`) · `app(): string` · `all(): array{app,core,core_name}`.
**Notas:** `app()` = setting `app.version` > env `APP_VERSION` > `1.0.0`. Resiliente sin BD.

### `Health` — `system/app/Services/Health.php`
**Qué hace:** Estado del sistema para healthcheck público y métricas internas.
**API pública (static):** `dbUp(): bool` · `version(): string` · `summary(): array` (público, sin datos sensibles) · `full(): array` (autenticado: +php, email_queue_pending, disk_free_mb, last_backup).
**Uso típico:**
```php
echo json_encode(\App\Services\Health::summary()); // endpoint /health
```
**Notas:** `status` = `degraded` si la BD no responde. Todo tolerante a fallos.

### `Audit` — `system/app/Services/Audit.php`
**Qué hace:** Auditoría de acciones de admins con diff de campos. Best-effort.
**API pública (static):** `log(string $action, string $target='', string $details=''): void` · `logChange(string $action, string $target, array $before, array $after): void` · `diff(array $before, array $after, array $ignore=['password','created_at','updated_at']): array`.
**Uso típico:**
```php
use App\Services\Audit;
Audit::log('login', 'admin');
Audit::logChange('factura.update', "factura#$id", $before, $after);
```
**Notas:** Tabla `audit_log`; `changes` como JSON. Usa `Auth::user()` e IP. Errores silenciosos.

### `DemoSeeder` — `system/app/Services/DemoSeeder.php`
**Qué hace:** Genera datos de ejemplo (todos marcados con `[demo]`/`@demo.local`) para ver el dashboard "vivo"; reversible.
**API pública (static):** `isSeeded(): bool` · `seed(): array` (idempotente) · `undo(): array`.
**Uso típico:**
```php
if (!\App\Services\DemoSeeder::isSeeded()) \App\Services\DemoSeeder::seed();
\App\Services\DemoSeeder::undo();
```
**Notas:** Crea admins demo (password `demo1234`), cron, emails, jobs, auditoría, notifs, webhooks, tokens. Asume que esas tablas existen.

### CLI de sistema (`system/console/`)
```bash
php system/console/install.php --answers=answers.json [--dry-run] [--force]   # genera/escribe .env
php system/console/key.php                                                       # imprime APP_KEY=... (no escribe)
php system/console/deploy.php ftp|git [--run]                                    # deploy (dry-run sin --run)
php system/console/make-module.php <Model> <tabla> "campo:tipo ..."             # genera CRUD completo
php system/console/core-manifest.php [--check]                                   # genera/verifica core-lock.json
```
- **`make-module.php`** es la herramienta clave para construir tu dominio: genera migración + modelo + controlador admin (index con paginación/búsqueda, create/store/edit/update, soft-delete, trash/restore, export CSV/Excel/PDF) + vistas Tailwind + rutas, y actualiza el menú. Luego corré la migración. Ver §0 y el skill `module-generator`.
- **`core-manifest.php`** clasifica archivos core vs app (reglas en `core-manifest.json`) y produce `core-lock.json` con checksums; lo usa `/release` y el actualizador.

---

## 7. IA, ecommerce, API & HTTP

### `AiConnector` — `system/app/Services/AiConnector.php`
**Qué hace:** Chat con LLMs vía proveedores compatibles OpenAI (OpenAI, Deepseek) y Anthropic; resuelve credenciales/modelo de `Settings`/`.env`, soporta streaming SSE y loguea en `ai_log`.
**API pública (static):** `providers(): array` (`openai, deepseek, anthropic`) · `config(): array` · `chat(array $messages, array $opts=[]): array{ok,content,error}` · `chatStream(array $messages, callable $onToken, array $opts=[]): array` · `chatPrompt(string $promptName, array $vars=[], array $opts=[]): array` · (helpers: `buildRequest`, `extractContent`, `withSystem`, `splitSystem`, `parseSse*`).
**Uso típico:**
```php
use App\Services\AiConnector;
$r = AiConnector::chat([['role'=>'user','content'=>'Resumí esta factura: ...']]);
if ($r['ok']) echo $r['content'];
AiConnector::chatStream([['role'=>'user','content'=>'...']], fn($tok) => print($tok));
```
**Notas:** Settings grupo `ai` (secretos cifrados) con fallback env (`AI_PROVIDER, AI_MODEL, AI_API_KEY, AI_SYSTEM_PROMPT`). `chat()` loguea en `ai_log` (best-effort); `chatStream()` NO loguea. Nunca lanza: revisá `['ok']`. `$opts`: `temperature` (0.7), `max_tokens`, `system`.

### `PromptLibrary` — `system/app/Services/PromptLibrary.php`
**Qué hace:** Catálogo de prompts reutilizables (`config/prompts.php`) con interpolación `{{var}}`.
**API pública (static):** `all()` · `names()` · `has(string)` · `get(string): ?string` · `render(string $name, array $vars=[]): string`.
**Uso típico:**
```php
$texto = \App\Services\PromptLibrary::render('resumen_factura', ['cliente' => 'ACME', 'total' => 1500]);
```
**Notas:** Se combina con `AiConnector::chatPrompt()`. Sin caché (require por llamada).

### `Http` — `system/app/Services/Http.php`
**Qué hace:** Cliente HTTP minimalista (cURL con fallback a stream) para JSON/streaming. Nunca lanza.
**API pública (static):** `postJson(url, payload, headers=[], timeout=30): array{ok,status,error,data}` · `postRaw(url, body, headers=[], timeout=10): array{ok,status,body,error}` (para webhooks con firma exacta) · `postStream(url, payload, headers, callable $onLine, timeout=60): array`.
**Uso típico:**
```php
$res = \App\Services\Http::postJson('https://api.x/v1/cosa', ['k'=>'v'], ['Authorization: Bearer ...']);
if ($res['ok']) $data = $res['data'];
```
**Notas:** `postRaw`/`postStream` requieren cURL. `postJson` con fallback de streams puede dar `status=0` si no parsea el header.

### `Webhook` — `system/app/Services/Webhook.php`
**Qué hace:** Suscripciones de webhooks salientes (evento→URL); encola la entrega asíncrona (firma HMAC-SHA256).
**API pública (static):** `events(): array` · `sign(string $secret, string $body): string` · `subscribe(string $event, string $url): int` · `all(): array` · `toggle(int $id)` · `delete(int $id)` · `dispatch(string $event, array $payload=[]): int`.
**Uso típico:**
```php
use App\Services\Webhook;
Webhook::subscribe('admin.created', 'https://hook.site/abc');
$n = Webhook::dispatch('admin.created', ['id'=>42]); // encola N jobs webhook:deliver
```
**Notas:** Tabla `webhooks`. La entrega real (HTTP + firma) la hace el job `webhook:deliver` (`WebhookDeliverJob`), no esta clase. Eventos por defecto: `ping`, `admin.created` (extendé `events()` para los tuyos).

### `OpenApiGenerator` — `system/app/Services/OpenApiGenerator.php`
**Qué hace:** Genera un documento OpenAPI 3.0.3 (array PHP) del CRUD REST de los recursos en `config/api.php`.
**API pública (static):** `spec(): array`.
**Uso típico:**
```php
header('Content-Type: application/json');
echo json_encode(\App\Services\OpenApiGenerator::spec());
```
**Notas:** `config/api.php` con `['resources' => ['nombre' => ['fields' => ['campo'=>'tipo', ...]]]]`. Seguridad `bearerAuth` (combina con `ApiToken`).

### Familia Ecommerce (`system/app/Services/Ecommerce/`)

Integración multi-plataforma con un **contrato común** y un **factory**. Toda respuesta se normaliza a `['ok'=>bool,'status'=>int,'data'=>mixed,'error'=>?string]` y **nunca se lanza** (salvo `Factory::make` con plataforma inválida).

**`StoreConnector` (interfaz):** `platform()` · `ping()` · `getProducts($filters=[])` · `getProduct($id,$params=[])` · `getOrders($filters=[])` · `getOrder($id,$params=[])` · `getCustomers($filters=[])` · `get($path,$query=[])` · `post($path,$body=[],$query=[])`.

**`StoreConnectorFactory` (static):** `platforms(): array` · `make(string $platform, array $credentials, int $timeout=20): StoreConnector` (lanza `InvalidArgumentException` si no existe) · `fromSettings(?string $platform=null): ?StoreConnector` (lee `Settings::group('ecommerce')`; devuelve `null`, no lanza) · `defaultPlatform(): string` · `registry(): array`.

**`AbstractStoreConnector`:** base con cliente cURL (TLS nunca desactivado), `get/post/put/delete`, `request()` normalizada. Cada driver implementa `authHeaders()` y `apiBase()`.

```php
use App\Services\Ecommerce\StoreConnectorFactory;
$store = StoreConnectorFactory::fromSettings();              // tienda activa según settings
if ($store && $store->ping()['ok']) $orders = $store->getOrders(['status' => 'paid']);

$shop = StoreConnectorFactory::make('shopify', ['shop'=>'mi-tienda','access_token'=>'shpat_...']);
```

| Driver | `apiBase()` | Auth header | Credenciales |
|---|---|---|---|
| `nubixstore` | `{base_url}/api` | `Authorization: Bearer` (login lazy + renovación) | `base_url, user, password, api_key` |
| `shopify` | `…/admin/api/{api_version}` (def `2024-04`) | `X-Shopify-Access-Token` | `shop, access_token, api_version?` |
| `woocommerce` | `{site}/wp-json/wc/v3` | `Authorization: Basic ck:cs` (HTTPS) | `site, consumer_key, consumer_secret` |
| `tiendanube` | `https://api.tiendanube.com/v1/{store_id}` | `Authentication: bearer` + `User-Agent` (obligatorio) | `store_id, access_token, user_agent?` |
| `magento` | `{site}/rest/{store_code}/V1` | `Authorization: Bearer` | `site, access_token, store_code?` |

**Notas:** `nubixstore` cachea token y lo renueva 30s antes de expirar (`API_MANUAL_VERSION='2.1'`, extra `getStock()`); su password va en texto plano (la API lo hashea). `magento` aplica `searchCriteria[...]` por defecto en listados; su `getProduct($id)` espera SKU. Conocimiento de dominio: [`ecommerce/ecommerce-apis.md`](ecommerce/ecommerce-apis.md) y [`nubixstore/manual-api-nubixstore.md`](nubixstore/manual-api-nubixstore.md).

---

## 8. Archivos & helpers de UI

### `FileManager` — `system/app/Services/FileManager.php`
**Qué hace:** Gestor de archivos acotado a `system/storage/uploads` (anti path-traversal): lista, sube, crea carpetas, renombra, mueve, borra.
**API pública (static):** `isImage(name)` · `root()` · `normalizeRel(rel): ?string` · `safeDir(rel): ?string` · `resolve(rel): ?string` · `cleanName(name): ?string` · `list(rel): array` (`['dirs'=>...,'files'=>...]`) · `breadcrumb(rel): array` · `upload(rel, $file): array` · `makeDir(rel,name)` · `rename(rel,newName)` · `move(rel,destRel)` · `delete(rel)`.
**Uso típico:**
```php
use App\Services\FileManager;
$res = FileManager::upload($_POST['dir'] ?? '', $_FILES['file']);
$content = FileManager::list('documentos/2026');
```
**Notas:** `config/files.php` (`max_upload_bytes`, `allowed_ext`). Rutas SIEMPRE relativas a la raíz; nunca aceptes rutas absolutas del cliente. `..` se rechaza.

### `FileShare` — `system/app/Services/FileShare.php`
**Qué hace:** Links públicos de descarga por token (`/a/{token}`), sin login.
**API pública (static):** `share(rel): string` (token 32 hex) · `unshare(rel)` · `byPath(rel): ?array` · `byToken(token): ?array` · `countDownload(int $id)` · `map(): array`.
**Uso típico:**
```php
$token = \App\Services\FileShare::share('documentos/factura.pdf'); // URL: /a/$token
$share = \App\Services\FileShare::byToken($token);
if ($share) \App\Services\FileShare::countDownload((int)$share['id']);
```
**Notas:** Tabla `file_shares`. `byToken` valida formato `^[a-f0-9]{32}$`. No verifica que el archivo exista: combiná con `FileManager::resolve`.

### `Exporter` — `system/app/Services/Exporter.php`
**Qué hace:** Exporta filas a CSV, Excel (HTML servido como `.xls`) e imprimible HTML (PDF vía navegador). Sin dependencias.
**API pública (static):** `csv(rows, columns): string` (UTF-8 con BOM) · `excelHtml(rows, columns, title): string` · `printableHtml(rows, columns, title): string` · `filename(base, ext): string`.
**Uso típico:**
```php
use App\Services\Exporter;
$columns = ['id'=>'ID','numero'=>'N°','total'=>'Total'];
$csv = Exporter::csv($facturas, $columns);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . Exporter::filename('facturas','csv') . '"');
echo $csv;
```
**Notas:** `$columns` (mapa `clave=>etiqueta`) define qué columnas y en qué orden. Booleans → `Sí`/`No`, `null` → `''`. Solo devuelve strings; los headers/echo los emitís vos. El controlador generado por `make-module.php` ya cablea esto.

### `Charts` — `system/app/Services/Charts.php`
**Qué hace:** Construye configs de Chart.js desde PHP; el partial `partials/chart` las serializa.
**API pública (static):** `palette(int $n): array` · `bar(id, title, labels, data): array` · `doughnut(...)` · `line(...)`.
**Uso típico:**
```php
$chart = \App\Services\Charts::bar('ventas', 'Ventas por mes', ['Ene','Feb','Mar'], [120,90,210]);
return $this->view('admin/dashboard', ['charts' => [$chart]]);
```
**Notas:** El `id` es el del `<canvas>` (único por página). `labels` y `data` alineados por posición (no valida largos). Para mostrar el chart, incluir `partials/chart`. Renderizá assets con `Assets::head(withChart: true)`.

### `Breadcrumb` — `system/app/Services/Breadcrumb.php`
**Qué hace:** Genera el rastro de migas del backend derivándolo de la ruta actual + menú.
**API pública (static):** `trail(string $reqPath, ?string $extra=null): array` → `[['label','url'?], ...]`.
**Uso típico:**
```php
$crumbs = \App\Services\Breadcrumb::trail($_SERVER['REQUEST_URI'], $factura['numero'] ?? null);
// o desde el controlador, pasar 'breadcrumbExtra' a la vista
```
**Notas:** Lee `config/menu.php` y `config/modules_menu.php`. El layout consume variables de vista `breadcrumbExtra` (string) o `breadcrumb` (trail custom).

### `Dashboard` — `system/app/Services/Dashboard.php`
**Qué hace:** Resuelve el preset de dashboard activo y sus bloques (`config/dashboard.php` + override por settings).
**API pública (static):** `presets(): array` · `active(): string` · `blocks(): array`.
**Uso típico:**
```php
foreach (\App\Services\Dashboard::blocks() as $block) { /* render del partial del bloque */ }
```
**Notas:** `config/dashboard.php` = `['default'=>..., 'presets'=>['nombre'=>['blocks'=>[...]]]]`. El preset activo se cambia persistiendo el setting `dashboard.preset` (fuera de esta clase). Tolera ausencia de BD.

### `GlobalSearch` — `system/app/Services/GlobalSearch.php`
**Qué hace:** Búsqueda global del backend: recorre los módulos de `config/modules_menu.php`, deriva su tabla y busca en columnas de texto, agrupando por módulo.
**API pública (static):** `tableFromPath(path): string` · `textColumns(table): array` · `search(string $query, int $perModule=5): array` → grupos `[['label','path','matches'=>[['id','label','url'],...]],...]`.
**Uso típico:**
```php
$groups = \App\Services\GlobalSearch::search($_GET['q'] ?? '', 5);
// cada match['url'] = '/admin/<tabla>/<id>/edit'
```
**Notas:** LIKE `%query%` sobre columnas de texto (detectadas por `SHOW COLUMNS`), excluye `deleted_at IS NULL` si existe. Valida tabla/columna con regex; `$perModule` se interpola en el LIMIT → pasá un int de confianza.

---

## Cómo encarar un módulo nuevo (receta)

Para construir, p.ej., un facturador encima del skeleton:

1. **Generá el CRUD base** con `make-module.php` (modelo, tabla, campos y FKs):
   ```bash
   php system/console/make-module.php Factura facturas "numero:string:required,unique cliente_id:fk:clientes total:decimal estado:string emitida:date"
   php system/database/migrate.php
   ```
   Obtenés modelo, controlador admin (paginación, búsqueda, soft-delete, export), vistas y rutas, y el ítem de menú.
2. **Lógica de dominio** → un `Service` propio en `system/app/Services/` (o `app-agentic/modules/`), reusando los del core (`Validator`, `Mailer`, `AiConnector`, `Webhook`, `Exporter`, `Audit`…). Servicios estáticos, sin tocar `$_GET`/`$_POST` (reciben datos validados).
3. **Validaciones** con `Validator::make`; **autorización** con `Rbac::can` por permiso (declaralo en `config/permissions.php`).
4. **Tareas periódicas** → job interno en `config/jobs.php` + tarea en `cron_tasks`, o `JobQueue` para asíncrono con reintentos.
5. **Integraciones** (mail, IA, ecommerce, webhooks) vía los servicios de §5 y §7.
6. **Auditá** cambios sensibles con `Audit::logChange` y **notificá** con `Notifier`/`AlertProvider`.
7. **Documentá** el módulo en `docs/modules/` (plantilla en [`../templates/module-manual.template.md`](../templates/module-manual.template.md)) y cumplí el logging obligatorio de [`../methodology/logging.md`](../methodology/logging.md).

> **Regla de oro:** lo tuyo va en capa de proyecto (no es core). Si necesitás cambiar
> comportamiento del core, **overrídelo** (vistas en `app/Views/overrides/`, config en
> `system/config/overrides/`, agéntica en `app-agentic/`) en vez de editar `system/`.
