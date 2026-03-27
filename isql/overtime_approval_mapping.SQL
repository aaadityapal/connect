CREATE TABLE overtime_approval_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    employee_id INT NOT NULL, 
    manager_id INT NOT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    INDEX (employee_id), 
    INDEX (manager_id)
);
