CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_name VARCHAR(255) NOT NULL,
    project_type ENUM('architecture', 'interior', 'construction') NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    mobile VARCHAR(15),
    location VARCHAR(255),
    total_cost DECIMAL(10, 2) NOT NULL,
    employee_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
