CREATE TABLE `policy_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `policy_name` varchar(255) NOT NULL,
  `policy_type` varchar(50) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_size` varchar(20) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `policy_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `policy_documents` 
ADD COLUMN `status` ENUM('pending', 'acknowledged', 'rejected', 'accepted') NOT NULL DEFAULT 'pending';

CREATE TABLE policy_acknowledgments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policy_id INT NOT NULL,
    user_id INT NOT NULL,
    acknowledged_at DATETIME NOT NULL,
    FOREIGN KEY (policy_id) REFERENCES policy_documents(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);