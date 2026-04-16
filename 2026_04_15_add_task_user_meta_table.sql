
CREATE TABLE `studio_task_user_meta` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `meta_key` VARCHAR(255) NOT NULL,
  `meta_value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `task_user_meta_idx` (`task_id`, `user_id`, `meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `studio_task_user_meta`
ADD CONSTRAINT `fk_stum_task_id`
FOREIGN KEY (`task_id`) REFERENCES `studio_assigned_tasks`(`id`)
ON DELETE CASCADE;

ALTER TABLE `studio_task_user_meta`
ADD CONSTRAINT `fk_stum_user_id`
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
ON DELETE CASCADE;
