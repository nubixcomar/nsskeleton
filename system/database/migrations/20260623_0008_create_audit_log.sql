-- Migración: auditoría de acciones de administradores.
CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id   INT UNSIGNED NULL,
    admin_name VARCHAR(120) NULL,
    action     VARCHAR(80)  NOT NULL,
    target     VARCHAR(190) NULL,
    details    VARCHAR(500) NULL,
    ip         VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS audit_log;
