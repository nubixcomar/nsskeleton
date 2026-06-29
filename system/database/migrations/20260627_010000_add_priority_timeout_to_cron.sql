-- Cronmaster v2: prioridad y timeout por tarea.
ALTER TABLE cron_tasks
    ADD COLUMN priority INT NOT NULL DEFAULT 0,
    ADD COLUMN timeout INT UNSIGNED NOT NULL DEFAULT 0;

-- @DOWN
ALTER TABLE cron_tasks
    DROP COLUMN priority,
    DROP COLUMN timeout;
