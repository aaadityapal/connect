CREATE TABLE global_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(100) NOT NULL COMMENT 'e.g., punch_in, punch_out, task_created',
    entity_type VARCHAR(100) NOT NULL COMMENT 'e.g., attendance, tasks, leaves',
    entity_id INT NULL COMMENT 'ID of the specific record',
    description TEXT NOT NULL COMMENT 'Human-readable description',
    metadata JSON NULL COMMENT 'Extra data like tags, geofence status, etc.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
