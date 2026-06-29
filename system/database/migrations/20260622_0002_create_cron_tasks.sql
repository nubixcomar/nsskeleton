-- Migración: programador de tareas (cron) + historial de ejecuciones.
CREATE TABLE IF NOT EXISTS cron_tasks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120) NOT NULL,
    command     TEXT NOT NULL,
    schedule    VARCHAR(100) NOT NULL,           -- expresión cron de 5 campos
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    last_run_at DATETIME     NULL,
    last_status VARCHAR(20)  NULL,
    last_output MEDIUMTEXT   NULL,
    next_run_at DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_runs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id     INT UNSIGNED NOT NULL,
    started_at  DATETIME NOT NULL,
    finished_at DATETIME NULL,
    status      VARCHAR(20) NOT NULL,
    exit_code   INT NOT NULL DEFAULT 0,
    output      MEDIUMTEXT NULL,
    PRIMARY KEY (id),
    KEY idx_cron_runs_task (task_id),
    CONSTRAINT fk_cron_runs_task FOREIGN KEY (task_id)
        REFERENCES cron_tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
DROP TABLE IF EXISTS cron_runs;
DROP TABLE IF EXISTS cron_tasks;
