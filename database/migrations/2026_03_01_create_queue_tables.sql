-- =============================================================================
-- Queue Engine Tables for AtoM Heratio
-- Issue #167: Durable Workflow Engine for Background Jobs
-- =============================================================================

-- 1. ahg_queue_batch — Batch job groups
CREATE TABLE IF NOT EXISTS ahg_queue_batch (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    queue       VARCHAR(100) NOT NULL DEFAULT 'default',
    status      VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending, running, paused, completed, failed, cancelled',
    total_jobs      INT UNSIGNED NOT NULL DEFAULT 0,
    completed_jobs  INT UNSIGNED NOT NULL DEFAULT 0,
    failed_jobs     INT UNSIGNED NOT NULL DEFAULT 0,
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    max_concurrent  INT UNSIGNED NOT NULL DEFAULT 5,
    delay_between_ms INT UNSIGNED NOT NULL DEFAULT 0,
    max_retries     INT UNSIGNED NOT NULL DEFAULT 3,
    options         JSON NULL,
    on_complete_callback VARCHAR(255) NULL,
    user_id     INT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at  DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_batch_status (status),
    INDEX idx_batch_queue (queue),
    INDEX idx_batch_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ahg_queue_job — Central job queue
CREATE TABLE IF NOT EXISTS ahg_queue_job (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue       VARCHAR(100) NOT NULL DEFAULT 'default',
    job_type    VARCHAR(255) NOT NULL COMMENT 'Handler identifier, e.g. ingest:commit',
    payload     JSON NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending, reserved, running, completed, failed, cancelled',
    priority    TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 9=lowest',
    batch_id    BIGINT UNSIGNED NULL,
    chain_id    BIGINT UNSIGNED NULL,
    chain_order INT NULL,
    attempt_count   INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts    INT UNSIGNED NOT NULL DEFAULT 3,
    delay_seconds   INT UNSIGNED NOT NULL DEFAULT 0,
    backoff_strategy VARCHAR(20) NOT NULL DEFAULT 'exponential' COMMENT 'none, linear, exponential',
    available_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at     DATETIME NULL,
    started_at      DATETIME NULL,
    completed_at    DATETIME NULL,
    processing_time_ms INT UNSIGNED NULL,
    result_data     JSON NULL,
    error_message   TEXT NULL,
    error_code      VARCHAR(100) NULL,
    error_trace     TEXT NULL,
    worker_id       VARCHAR(100) NULL,
    user_id         INT NULL,
    progress_current INT UNSIGNED NOT NULL DEFAULT 0,
    progress_total   INT UNSIGNED NOT NULL DEFAULT 0,
    progress_message VARCHAR(500) NULL,
    rate_limit_group VARCHAR(100) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_job_dispatch (queue, status, priority, available_at),
    INDEX idx_job_batch (batch_id),
    INDEX idx_job_chain (chain_id, chain_order),
    INDEX idx_job_worker (worker_id),
    INDEX idx_job_status (status),
    INDEX idx_job_user (user_id),
    INDEX idx_job_type (job_type),
    CONSTRAINT fk_job_batch FOREIGN KEY (batch_id) REFERENCES ahg_queue_batch(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ahg_queue_failed — Archived failed jobs
CREATE TABLE IF NOT EXISTS ahg_queue_failed (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue       VARCHAR(100) NOT NULL,
    job_type    VARCHAR(255) NOT NULL,
    payload     JSON NULL,
    error_message TEXT NULL,
    error_trace TEXT NULL,
    original_job_id BIGINT UNSIGNED NULL,
    batch_id    BIGINT UNSIGNED NULL,
    user_id     INT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_failed_queue (queue),
    INDEX idx_failed_job_type (job_type),
    INDEX idx_failed_original (original_job_id),
    INDEX idx_failed_at (failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ahg_queue_log — Audit trail
CREATE TABLE IF NOT EXISTS ahg_queue_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      BIGINT UNSIGNED NULL,
    batch_id    BIGINT UNSIGNED NULL,
    event_type  VARCHAR(50) NOT NULL COMMENT 'dispatched, reserved, started, completed, failed, retried, cancelled',
    message     VARCHAR(500) NULL,
    details     JSON NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_job (job_id),
    INDEX idx_log_batch (batch_id),
    INDEX idx_log_event (event_type),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ahg_queue_rate_limit — Per-group rate limiter
CREATE TABLE IF NOT EXISTS ahg_queue_rate_limit (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_name  VARCHAR(100) NOT NULL,
    max_per_minute INT UNSIGNED NOT NULL DEFAULT 60,
    window_start DATETIME NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_rate_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
