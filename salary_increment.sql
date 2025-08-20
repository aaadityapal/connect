-- Create salary_increments table for tracking employee salary increments
CREATE TABLE `salary_increments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `increment_percentage` decimal(5,2) NOT NULL,
    `effective_from` date NOT NULL,
    `salary_after_increment` decimal(10,2) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) DEFAULT NULL,
    `status` enum('pending','applied','cancelled') NOT NULL DEFAULT 'applied',
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `effective_from` (`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Add foreign key constraint if your database supports it
-- ALTER TABLE `salary_increments` 
--     ADD CONSTRAINT `fk_salary_increments_user` FOREIGN KEY (`user_id`) 
--     REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Optional: Add foreign key for created_by
-- ALTER TABLE `salary_increments` 
--     ADD CONSTRAINT `fk_salary_increments_creator` FOREIGN KEY (`created_by`) 
--     REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;