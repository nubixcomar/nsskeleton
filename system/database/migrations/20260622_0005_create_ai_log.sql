-- Migración: auditoría de llamadas al conector de IA.
CREATE TABLE IF NOT EXISTS ai_log (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider       VARCHAR(40)  NOT NULL,
    model          VARCHAR(80)  NULL,
    status         VARCHAR(20)  NOT NULL,            -- ok | failed
    prompt_chars   INT NOT NULL DEFAULT 0,
    response_chars INT NOT NULL DEFAULT 0,
    error          VARCHAR(500) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ai_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS ai_log;
