-- 2FA (TOTP) en administradores.
ALTER TABLE admin_users
    ADD COLUMN totp_secret VARCHAR(128) NULL DEFAULT NULL,
    ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0;

-- @DOWN
ALTER TABLE admin_users
    DROP COLUMN totp_secret,
    DROP COLUMN totp_enabled;
