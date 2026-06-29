-- Scopes (permisos) por token de API.
ALTER TABLE api_tokens
    ADD COLUMN scopes VARCHAR(64) NOT NULL DEFAULT 'read,write' AFTER name;

-- @DOWN
ALTER TABLE api_tokens
    DROP COLUMN scopes;
