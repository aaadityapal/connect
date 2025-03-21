UPDATE users 
SET shift_id = 1  -- Use 1 for Morning Shift
WHERE id = 1;


CREATE TABLE offer_letters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_name VARCHAR(255) NOT NULL,
    employee_email VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    offer_date DATE NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_viewed BOOLEAN DEFAULT FALSE,
    view_date TIMESTAMP NULL,
    is_accepted BOOLEAN DEFAULT FALSE,
    acceptance_date TIMESTAMP NULL,
    status ENUM('pending', 'viewed', 'accepted', 'rejected') DEFAULT 'pending'
);

ALTER TABLE offer_letters ADD COLUMN user_id INT NOT NULL AFTER id;
ALTER TABLE offer_letters ADD FOREIGN KEY (user_id) REFERENCES users(id);








CREATE TABLE IF NOT EXISTS offer_letters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);