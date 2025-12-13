-- Construction Site Tasks Table
-- Stores all construction project tasks with assignment and status tracking

CREATE TABLE IF NOT EXISTS construction_site_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('planned', 'in_progress', 'on_hold', 'review', 'completed', 'blocked', 'cancelled') DEFAULT 'planned',
    assign_to VARCHAR(255),
    assigned_user_id INT,
    created_by INT,
    updated_by INT,
    images LONGTEXT COMMENT 'JSON array of base64 encoded images or file paths',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for better query performance
    INDEX idx_project_id (project_id),
    INDEX idx_assigned_user_id (assigned_user_id),
    INDEX idx_status (status),
    INDEX idx_start_date (start_date),
    INDEX idx_end_date (end_date),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for date range queries
CREATE INDEX idx_date_range ON construction_site_tasks(start_date, end_date);

-- Index for combined queries
CREATE INDEX idx_project_status ON construction_site_tasks(project_id, status);
