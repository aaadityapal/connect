-- Create table to store short leave preferences for each user per month
CREATE TABLE IF NOT EXISTS short_leave_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filter_month VARCHAR(7) NOT NULL, -- Format: YYYY-MM
    use_for_one_hour_late TINYINT(1) DEFAULT 0, -- 0 = use for regular late, 1 = use for 1-hour late
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_month (user_id, filter_month),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);