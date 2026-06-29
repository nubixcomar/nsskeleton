-- Migración: cola de envío de emails.
CREATE TABLE IF NOT EXISTS email_queue (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    to_address VARCHAR(190) NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    body       MEDIUMTEXT NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending | sent | failed
    attempts   INT NOT NULL DEFAULT 0,
    error      VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_email_queue_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS email_queue;
