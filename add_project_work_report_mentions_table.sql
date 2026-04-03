-- Stores project hashtags used in punch-out work reports
CREATE TABLE IF NOT EXISTS project_work_report_mentions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT NOT NULL,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    project_title VARCHAR(255) NOT NULL,
    report_date DATE NOT NULL,
    work_report TEXT NOT NULL,
    mention_text VARCHAR(300) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_attendance_project (attendance_id, project_id),
    KEY idx_project_date (project_id, report_date),
    KEY idx_user_date (user_id, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
