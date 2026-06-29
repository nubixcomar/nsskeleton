-- Migración: tokens de API (se guarda el hash del token).
CREATE TABLE IF NOT EXISTS api_tokens (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id     INT UNSIGNED NULL,
    name         VARCHAR(120) NOT NULL,
    token_hash   CHAR(64) NOT NULL,
    last_used_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_tokens_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS api_tokens;
