-- Migración: settings genéricos (clave/valor por grupo) + log de emails.
CREATE TABLE IF NOT EXISTS settings (
    `key`   VARCHAR(120) NOT NULL,
    `value` TEXT NULL,
    `group` VARCHAR(60) NOT NULL DEFAULT 'general',
    PRIMARY KEY (`key`),
    KEY idx_settings_group (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    to_address VARCHAR(190) NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    status     VARCHAR(20)  NOT NULL,
    error      VARCHAR(500) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS email_log;
DROP TABLE IF EXISTS settings;
