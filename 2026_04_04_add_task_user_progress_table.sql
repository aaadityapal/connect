-- Per-user task progress mapping table
CREATE TABLE IF NOT EXISTS studio_task_user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_task_user (task_id, user_id),
    INDEX idx_task (task_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional FK constraints (uncomment if your schema is clean and keys exist)
-- ALTER TABLE studio_task_user_progress
--   ADD CONSTRAINT fk_task_user_progress_task
--     FOREIGN KEY (task_id) REFERENCES studio_assigned_tasks(id) ON DELETE CASCADE,
--   ADD CONSTRAINT fk_task_user_progress_user
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
