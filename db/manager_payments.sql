-- Manager Payments Transaction Table
CREATE TABLE IF NOT EXISTS `hrm_manager_payment_transactions` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `manager_id` int(11) NOT NULL,
  `manager_name` varchar(255) NOT NULL,
  `manager_type` varchar(100) NOT NULL,
  `project_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_type` enum('architecture','interior','construction') NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `project_stage` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `payment_mode` enum('cash','upi','net_banking','cheque','credit_card') NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL COMMENT 'Reference number for payment',
  `payment_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `manager_id` (`manager_id`),
  KEY `project_id` (`project_id`),
  KEY `payment_date` (`payment_date`),
  KEY `project_type` (`project_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manager Payment Summary Table (for faster reporting)
CREATE TABLE IF NOT EXISTS `hrm_manager_payment_summary` (
  `summary_id` int(11) NOT NULL AUTO_INCREMENT,
  `manager_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `architecture_commission` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interior_commission` decimal(15,2) NOT NULL DEFAULT 0.00,
  `construction_commission` decimal(15,2) NOT NULL DEFAULT 0.00,
  `fixed_remuneration` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_payable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `last_payment_date` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`summary_id`),
  UNIQUE KEY `manager_month_year` (`manager_id`,`month`,`year`),
  KEY `month_year` (`month`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers to update summary table when payments are added
DELIMITER //

-- Trigger to update summary table after inserting a new payment
CREATE TRIGGER IF NOT EXISTS `after_manager_payment_insert` 
AFTER INSERT ON `hrm_manager_payment_transactions` 
FOR EACH ROW 
BEGIN
  -- Extract month and year from payment date
  SET @payment_month = MONTH(NEW.payment_date);
  SET @payment_year = YEAR(NEW.payment_date);
  
  -- Update or insert into summary table
  INSERT INTO `hrm_manager_payment_summary` 
    (`manager_id`, `month`, `year`, `amount_paid`, `last_payment_date`) 
  VALUES 
    (NEW.manager_id, @payment_month, @payment_year, NEW.payment_amount, NEW.payment_date)
  ON DUPLICATE KEY UPDATE 
    `amount_paid` = `amount_paid` + NEW.payment_amount,
    `remaining_amount` = `total_payable` - (`amount_paid` + NEW.payment_amount),
    `last_payment_date` = IF(NEW.payment_date > `last_payment_date` OR `last_payment_date` IS NULL, NEW.payment_date, `last_payment_date`);
END//

-- Procedure to recalculate manager payment summaries for a specific month/year
CREATE PROCEDURE IF NOT EXISTS `recalculate_manager_payments`(IN p_month INT, IN p_year INT)
BEGIN
  -- Update payment summaries based on transactions
  UPDATE `hrm_manager_payment_summary` summary
  JOIN (
    SELECT 
      manager_id,
      SUM(payment_amount) as total_paid,
      MAX(payment_date) as last_date
    FROM `hrm_manager_payment_transactions`
    WHERE MONTH(payment_date) = p_month AND YEAR(payment_date) = p_year
    GROUP BY manager_id
  ) payments ON summary.manager_id = payments.manager_id
  SET 
    summary.amount_paid = payments.total_paid,
    summary.remaining_amount = summary.total_payable - payments.total_paid,
    summary.last_payment_date = payments.last_date
  WHERE summary.month = p_month AND summary.year = p_year;
END//

DELIMITER ; 