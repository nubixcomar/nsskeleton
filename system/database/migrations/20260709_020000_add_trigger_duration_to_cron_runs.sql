-- v1.19 fase 3: historial de cron enriquecido (tipo de disparo + duración).
ALTER TABLE cron_runs
    ADD COLUMN trigger_type VARCHAR(20) NOT NULL DEFAULT 'cron' AFTER status,
    ADD COLUMN duration_ms  INT UNSIGNED NULL AFTER exit_code;

CREATE INDEX idx_cron_runs_started ON cron_runs (started_at);

-- @DOWN
DROP INDEX idx_cron_runs_started ON cron_runs;
ALTER TABLE cron_runs DROP COLUMN trigger_type, DROP COLUMN duration_ms;
