-- Login unificado: username (además de email) + tipo de usuario del sistema.
ALTER TABLE admin_users
    ADD COLUMN username VARCHAR(60) NULL AFTER name,
    ADD COLUMN user_type VARCHAR(30) NOT NULL DEFAULT 'admin' AFTER role,
    ADD UNIQUE KEY uq_admin_users_username (username);

-- @DOWN
ALTER TABLE admin_users
    DROP INDEX uq_admin_users_username,
    DROP COLUMN username,
    DROP COLUMN user_type;
