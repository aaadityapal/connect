-- Create table for project stage payment transactions
CREATE TABLE IF NOT EXISTS `hrm_project_stage_payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL DEFAULT 0,
  `project_name` varchar(255) NOT NULL,
  `project_type` varchar(50) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `stage_number` int(11) NOT NULL,
  `stage_date` date NOT NULL,
  `stage_notes` text DEFAULT NULL,
  `remaining_amount` decimal(10,2) DEFAULT NULL,
  `total_project_amount` decimal(10,2) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `stage_number` (`stage_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for individual payment entries
CREATE TABLE IF NOT EXISTS `hrm_project_payment_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_mode` varchar(50) NOT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `hrm_project_payment_entries_ibfk_1` FOREIGN KEY (`transaction_id`) 
  REFERENCES `hrm_project_stage_payment_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 