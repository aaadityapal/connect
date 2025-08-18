CREATE TABLE IF NOT EXISTS all_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                   -- actor who triggered the event (employee)
    recipient_id INT NULL,                  -- recipient of the notification (e.g., approver/manager)
    event ENUM('leave_created','leave_updated') NOT NULL,
    leave_request_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    payload JSON NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient_is_read (recipient_id, is_read),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_leave (leave_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


