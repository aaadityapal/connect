CREATE TABLE IF NOT EXISTS `travel_payment_auth` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `can_pay` TINYINT(1) DEFAULT 0 COMMENT '1: Has permission to mark as Paid',
  `authorized_by` INT(11) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_auth` (`user_id`),
  CONSTRAINT `fk_auth_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
