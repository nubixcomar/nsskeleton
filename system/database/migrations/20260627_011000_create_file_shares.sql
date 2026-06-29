-- Links públicos de archivos por token (descarga sin login).
CREATE TABLE IF NOT EXISTS file_shares (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token VARCHAR(64) NOT NULL,
    rel_path VARCHAR(500) NOT NULL,
    downloads INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token),
    UNIQUE KEY uq_path (rel_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS file_shares;
