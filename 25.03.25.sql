CREATE TABLE notification_read_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_type VARCHAR(20) NOT NULL,
    source_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_notification (user_id, notification_type, source_id)
);