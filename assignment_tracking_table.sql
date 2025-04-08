-- Table for tracking assignment status changes
CREATE TABLE assignment_status_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('project', 'stage', 'substage') NOT NULL COMMENT 'Type of entity being tracked',
    entity_id INT NOT NULL COMMENT 'ID of the project, stage, or substage',
    previous_status VARCHAR(50) DEFAULT NULL COMMENT 'Previous assignment status',
    new_status VARCHAR(50) NOT NULL COMMENT 'New assignment status',
    assigned_to INT DEFAULT NULL COMMENT 'User ID assigned to the task',
    assigned_by INT DEFAULT NULL COMMENT 'User ID who made the assignment',
    project_id INT NOT NULL COMMENT 'Project ID for all entity types',
    stage_id INT DEFAULT NULL COMMENT 'Stage ID for stage and substage entities',
    substage_id INT DEFAULT NULL COMMENT 'Substage ID for substage entities',
    comments TEXT DEFAULT NULL COMMENT 'Optional comments about the assignment change',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the status change occurred',
    INDEX (entity_type, entity_id),
    INDEX (project_id),
    INDEX (stage_id),
    INDEX (substage_id),
    INDEX (assigned_to),
    INDEX (assigned_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 