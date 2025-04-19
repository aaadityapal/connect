-- Stage Chat Messages Table
-- This table stores chat messages for project stages and substages

CREATE TABLE IF NOT EXISTS `stage_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `substage_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `timestamp` datetime NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `stage_id` (`stage_id`),
  KEY `substage_id` (`substage_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints
ALTER TABLE `stage_chat_messages`
  ADD CONSTRAINT `stage_chat_messages_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_chat_messages_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_chat_messages_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Add index for faster retrieval of messages for a specific stage
CREATE INDEX `idx_stage_messages` ON `stage_chat_messages` (`project_id`, `stage_id`, `timestamp`);

-- Add index for faster retrieval of messages for a specific substage
CREATE INDEX `idx_substage_messages` ON `stage_chat_messages` (`project_id`, `stage_id`, `substage_id`, `timestamp`); 