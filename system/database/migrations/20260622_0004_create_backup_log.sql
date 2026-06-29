-- Migración: auditoría de backups y restauraciones.
CREATE TABLE IF NOT EXISTS backup_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type       VARCHAR(20)  NOT NULL,            -- db | files | full | restore
    file       VARCHAR(255) NULL,
    size       BIGINT UNSIGNED NULL,
    status     VARCHAR(20)  NOT NULL,            -- ok | failed
    message    VARCHAR(500) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_backup_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS backup_log;
