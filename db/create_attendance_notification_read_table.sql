-- Create table to track when attendance notifications are read
CREATE TABLE IF NOT EXISTS attendance_notification_read (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create index for faster queries
CREATE INDEX idx_attendance_notification_user ON attendance_notification_read(user_id);
CREATE INDEX idx_attendance_notification_date ON attendance_notification_read(attendance_date);