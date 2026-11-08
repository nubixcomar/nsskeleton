<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;

/**
 * Guard de autenticación configurable (v1.17 #7, hallazgo 1.9).
 *
 * Cada guard autentica contra su propia tabla y mantiene su sesión con una clave propia
 * → múltiples logins independientes conviven sin pisarse (admin / brand / customer…).
 * La config vive en `config/auth.php`. `Core\Auth` es la fachada del guard `default`.
 */
final class Guard
{
    /** @param array<string,mixed> $cfg */
    private function __construct(
        private readonly string $name,
        private readonly array $cfg,
    ) {
    }

    public static function for(string $name): self
    {
        $guards = Config::get('auth', 'guards', []);
        if (!is_array($guards) || !isset($guards[$name]) || !is_array($guards[$name])) {
            throw new InvalidArgumentException("Guard '{$name}' no está definido en config/auth.php");
        }
        return new self($name, $guards[$name]);
    }

    public static function default(): self
    {
        return self::for((string) Config::get('auth', 'default', 'admin'));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function table(): string
    {
        return (string) ($this->cfg['table'] ?? 'admin_users');
    }

    public function sessionKey(): string
    {
        return (string) ($this->cfg['session_key'] ?? ('_auth_' . $this->name . '_id'));
    }

    public function loginPath(): string
    {
        return (string) ($this->cfg['login_path'] ?? '/login');
    }

    public function redirect(): string
    {
        return (string) ($this->cfg['redirect'] ?? '/');
    }

    /**
     * Autentica y, si tiene éxito, inicia sesión. Devuelve el usuario (sin password) o null.
     * @return array<string,mixed>|null
     */
    public function attempt(string $login, string $password): ?array
    {
        $user = $this->verifyCredentials($login, $password);
        if ($user === null) {
            return null;
        }
        $this->login((int) $user['id']);
        return $user;
    }

    /**
     * Verifica credenciales SIN iniciar sesión (para flujos con 2FA).
     * @return array<string,mixed>|null
     */
    public function verifyCredentials(string $login, string $password): ?array
    {
        $cols = $this->cfg['login_columns'] ?? ['email'];
        $cols = is_array($cols) && $cols !== [] ? array_map('strval', $cols) : ['email'];

        $where = implode(' OR ', array_map(static fn (string $c): string => "`{$c}` = ?", $cols));
        $params = array_fill(0, count($cols), $login);

        $user = Database::selectOne(
            'SELECT * FROM ' . $this->table() . ' WHERE ' . $where . ' LIMIT 1',
            $params
        );

        if ($user === null) {
            return null;
        }

        $activeCol = $this->cfg['active_column'] ?? null;
        if (is_string($activeCol) && $activeCol !== '' && (int) ($user[$activeCol] ?? 0) !== 1) {
            return null;
        }

        if (!password_verify($password, (string) ($user['password'] ?? ''))) {
            return null;
        }

        unset($user['password']);
        return $user;
    }

    public function login(int $userId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::regenerate();
        }
        Session::set($this->sessionKey(), $userId);
    }

    public function check(): bool
    {
        return Session::has($this->sessionKey());
    }

    public function id(): ?int
    {
        $id = Session::get($this->sessionKey());
        return $id === null ? null : (int) $id;
    }

    /**
     * Usuario logueado, o null si no hay sesión, si el usuario ya no existe o si está inactivo
     * (columna `active_column` del guard, la misma que respeta el login). Nunca devuelve la
     * contraseña ni el secreto de 2FA (quien los necesite los lee con su propio SELECT).
     * `check()` sigue mirando solo la sesión; el admin valida `user()` en cada request.
     * @return array<string,mixed>|null
     */
    public function user(): ?array
    {
        $id = $this->id();
        if ($id === null) {
            return null;
        }
        $user = Database::selectOne('SELECT * FROM ' . $this->table() . ' WHERE id = ? LIMIT 1', [$id]);
        if ($user === null) {
            return null;
        }
        $activeCol = $this->cfg['active_column'] ?? null;
        if (is_string($activeCol) && $activeCol !== '' && array_key_exists($activeCol, $user)
            && (int) $user[$activeCol] !== 1) {
            return null;
        }
        unset($user['password'], $user['totp_secret']);
        return $user;
    }

    public function logout(): void
    {
        Session::remove($this->sessionKey());
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::regenerate();
        }
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
