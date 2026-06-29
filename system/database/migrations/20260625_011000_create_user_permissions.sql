-- Overrides de permisos por usuario (allow/deny sobre lo que da el rol).
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    permission VARCHAR(64) NOT NULL,
    effect TINYINT(1) NOT NULL DEFAULT 1, -- 1 = permitir, 0 = denegar
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_perm (user_id, permission),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS user_permissions;
