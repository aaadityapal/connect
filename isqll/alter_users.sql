ALTER TABLE users ADD COLUMN notification_preferences longtext DEFAULT NULL AFTER bank_details;


CREATE TABLE IF NOT EXISTS leave_approval_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manager_id INT NOT NULL,
    employee_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (manager_id, employee_id)
);
