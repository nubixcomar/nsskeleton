-- Webhooks salientes: suscripciones evento → URL (la entrega usa la cola de jobs).
CREATE TABLE IF NOT EXISTS webhooks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event VARCHAR(80) NOT NULL,
    url VARCHAR(255) NOT NULL,
    secret VARCHAR(64) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_event_active (event, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS webhooks;
