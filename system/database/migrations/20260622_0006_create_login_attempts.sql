-- Migración: control de intentos de login (rate-limit / lockout).
CREATE TABLE IF NOT EXISTS login_attempts (
    identifier      VARCHAR(190) NOT NULL,           -- email (normalizado)
    attempts        INT NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    locked_until    DATETIME NULL,
    PRIMARY KEY (identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS login_attempts;
