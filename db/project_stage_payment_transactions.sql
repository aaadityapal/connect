-- Project Stage Payment Transactions Table
CREATE TABLE IF NOT EXISTS `hrm_project_stage_payment_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_type` enum('architecture','interior','construction') NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `stage_number` int(11) NOT NULL,
  `stage_date` date NOT NULL,
  `stage_notes` text DEFAULT NULL,
  `remaining_amount` decimal(15,2) DEFAULT NULL COMMENT 'Amount marked as remaining by user',
  `total_project_amount` decimal(15,2) DEFAULT NULL COMMENT 'Total project amount (if specified)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `project_id` (`project_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual Payments for Each Stage
CREATE TABLE IF NOT EXISTS `hrm_project_payment_entries` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `payment_mode` enum('cash','upi','net_banking','cheque','credit_card') NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL COMMENT 'Reference number for payment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foreign Key Constraints - Only for payment entries
ALTER TABLE `hrm_project_payment_entries`
  ADD CONSTRAINT `fk_payment_entry_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `hrm_project_stage_payment_transactions` (`transaction_id`) ON DELETE CASCADE; 