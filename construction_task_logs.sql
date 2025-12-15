-- Construction Task Logs Table
-- Tracks history of task creation, updates, and status changes

CREATE TABLE IF NOT EXISTS construction_task_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    action_type ENUM('CREATED', 'UPDATED', 'STATUS_CHANGE', 'DELETED') NOT NULL,
    
    -- Specific tracking for status changes
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    
    -- Who performed the action
    performed_by INT,
    
    -- additional details (e.g., "Title changed", "Assigned to new user")
    details TEXT NULL,
    
    -- When it happened
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (task_id) REFERENCES construction_site_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_task_id (task_id),
    INDEX idx_performed_by (performed_by),
    INDEX idx_action_type (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
