-- Create project_payouts table
CREATE TABLE IF NOT EXISTS `project_payouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_name` varchar(255) NOT NULL,
  `project_type` enum('Architecture', 'Interior', 'Construction') NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `project_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_mode` varchar(50) NOT NULL,
  `project_stage` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 