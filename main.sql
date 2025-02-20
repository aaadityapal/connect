-- Create the users table with all necessary fields
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_id VARCHAR(20) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    reporting_manager VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for better performance
ALTER TABLE users
ADD INDEX idx_email (email),
ADD INDEX idx_role (role),
ADD INDEX idx_unique_id (unique_id),
ADD INDEX idx_status (status);

-- Create a table for role permissions (optional, for future use)
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    permission VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role, permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create a table for user activity logs
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles and permissions
INSERT INTO role_permissions (role, permission) VALUES
('admin', 'manage_users'),
('admin', 'manage_roles'),
('admin', 'view_reports'),
('HR', 'manage_employees'),
('HR', 'view_reports'),
('Senior Manager (Studio)', 'manage_team'),
('Senior Manager (Studio)', 'view_reports'),
('Senior Manager (Site)', 'manage_team'),
('Senior Manager (Marketing)', 'manage_team'),
('Senior Manager (Sales)', 'manage_team');

-- Create a table for department information
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default departments
INSERT INTO departments (name) VALUES
('Administration'),
('Human Resources'),
('Studio'),
('Site'),
('Marketing'),
('Sales'),
('Business Development');

-- Create a table for reporting structure
CREATE TABLE IF NOT EXISTS reporting_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    manager_id INT NOT NULL,
    department_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (manager_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add stored procedure for user registration
DELIMITER //
CREATE PROCEDURE register_user(
    IN p_username VARCHAR(100),
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255),
    IN p_role VARCHAR(50),
    IN p_reporting_manager VARCHAR(100)
)
BEGIN
    DECLARE v_unique_id VARCHAR(20);
    DECLARE v_next_id INT;
    
    -- Generate unique ID based on role
    SELECT COALESCE(MAX(CAST(SUBSTRING(unique_id, 4) AS UNSIGNED)), 0) + 1
    INTO v_next_id
    FROM users
    WHERE unique_id LIKE CONCAT(
        CASE p_role
            WHEN 'admin' THEN 'ADM'
            WHEN 'HR' THEN 'HR'
            WHEN 'Senior Manager (Studio)' THEN 'SMS'
            WHEN 'Senior Manager (Site)' THEN 'SMT'
            WHEN 'Senior Manager (Marketing)' THEN 'SMM'
            WHEN 'Senior Manager (Sales)' THEN 'SML'
            WHEN 'Design Team' THEN 'DT'
            WHEN 'Working Team' THEN 'WT'
            WHEN '3D Designing Team' THEN '3DT'
            WHEN 'Studio Trainees' THEN 'STR'
            WHEN 'Business Developer' THEN 'BD'
            WHEN 'Social Media Manager' THEN 'SMM'
            WHEN 'Site Manager' THEN 'STM'
            WHEN 'Site Supervisor' THEN 'STS'
            WHEN 'Site Trainee' THEN 'STT'
            WHEN 'Relationship Manager' THEN 'RM'
            WHEN 'Sales Manager' THEN 'SM'
            WHEN 'Sales Consultant' THEN 'SC'
            WHEN 'Field Sales Representative' THEN 'FSR'
            ELSE 'EMP'
        END,
        '%'
    );
    
    SET v_unique_id = CONCAT(
        CASE p_role
            WHEN 'admin' THEN 'ADM'
            WHEN 'HR' THEN 'HR'
            WHEN 'Senior Manager (Studio)' THEN 'SMS'
            WHEN 'Senior Manager (Site)' THEN 'SMT'
            WHEN 'Senior Manager (Marketing)' THEN 'SMM'
            WHEN 'Senior Manager (Sales)' THEN 'SML'
            WHEN 'Design Team' THEN 'DT'
            WHEN 'Working Team' THEN 'WT'
            WHEN '3D Designing Team' THEN '3DT'
            WHEN 'Studio Trainees' THEN 'STR'
            WHEN 'Business Developer' THEN 'BD'
            WHEN 'Social Media Manager' THEN 'SMM'
            WHEN 'Site Manager' THEN 'STM'
            WHEN 'Site Supervisor' THEN 'STS'
            WHEN 'Site Trainee' THEN 'STT'
            WHEN 'Relationship Manager' THEN 'RM'
            WHEN 'Sales Manager' THEN 'SM'
            WHEN 'Sales Consultant' THEN 'SC'
            WHEN 'Field Sales Representative' THEN 'FSR'
            ELSE 'EMP'
        END,
        LPAD(v_next_id, 3, '0')
    );
    
    -- Insert new user
    INSERT INTO users (unique_id, username, email, password, role, reporting_manager)
    VALUES (v_unique_id, p_username, p_email, p_password, p_role, p_reporting_manager);
    
    -- Log the activity
    INSERT INTO user_activity_logs (user_id, activity_type, description)
    VALUES (LAST_INSERT_ID(), 'REGISTRATION', CONCAT('New user registered with role: ', p_role));
END //
DELIMITER ;

-- Add trigger for updating timestamps
DELIMITER //
CREATE TRIGGER before_user_update
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END //
DELIMITER ;

-- Create view for active users
CREATE VIEW active_users AS
SELECT 
    u.id,
    u.unique_id,
    u.username,
    u.email,
    u.role,
    u.reporting_manager,
    u.created_at,
    u.last_login
FROM 
    users u
WHERE 
    u.status = 'active';

-- Add some useful indexes
ALTER TABLE users
ADD INDEX idx_username (username),
ADD INDEX idx_created_at (created_at),
ADD INDEX idx_last_login (last_login);