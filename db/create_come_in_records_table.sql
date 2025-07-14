-- Create table for storing Come In records with geofence information
CREATE TABLE IF NOT EXISTS `come_in_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User who recorded the come in',
  `latitude` decimal(10,7) DEFAULT NULL COMMENT 'Latitude coordinate',
  `longitude` decimal(10,7) DEFAULT NULL COMMENT 'Longitude coordinate',
  `accuracy` decimal(10,2) DEFAULT NULL COMMENT 'GPS accuracy in meters',
  `address` text DEFAULT NULL COMMENT 'Physical address from reverse geocoding',
  `photo_path` varchar(255) DEFAULT NULL COMMENT 'Path to the photo taken',
  `is_within_geofence` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether user was within a geofence',
  `closest_location` varchar(255) DEFAULT NULL COMMENT 'Name of the closest geofence location',
  `outside_location_reason` text DEFAULT NULL COMMENT 'Reason for being outside geofence',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the record was created',
  PRIMARY KEY (`id`),
  KEY `idx_come_in_user` (`user_id`),
  KEY `idx_come_in_created` (`created_at`),
  KEY `idx_come_in_geofence` (`is_within_geofence`),
  CONSTRAINT `fk_come_in_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores Come In records with geofence information'; 