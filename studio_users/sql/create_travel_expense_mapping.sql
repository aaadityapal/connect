-- SQL: Create travel_expense_mapping table
-- Purpose: Hierarchical approval workflow (Level 1: Manager, Level 2: HR, Level 3: Senior Manager)

CREATE TABLE IF NOT EXISTS travel_expense_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL UNIQUE,
    manager_id INT NOT NULL,
    hr_id INT NOT NULL,
    senior_manager_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE NO ACTION,
    FOREIGN KEY (hr_id) REFERENCES users(id) ON DELETE NO ACTION,
    FOREIGN KEY (senior_manager_id) REFERENCES users(id) ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
