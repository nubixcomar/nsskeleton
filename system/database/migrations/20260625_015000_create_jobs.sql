-- Cola de jobs generalizada (no solo emails), con reintentos.
CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    handler VARCHAR(120) NOT NULL,
    payload TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending | processing | done | failed
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at DATETIME NULL,
    error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_status_available (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS jobs;
