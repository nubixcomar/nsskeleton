-- Auditoría con diff: guarda los cambios (antes/después) como JSON.
ALTER TABLE audit_log
    ADD COLUMN changes TEXT NULL DEFAULT NULL AFTER details;

-- @DOWN
ALTER TABLE audit_log
    DROP COLUMN changes;
