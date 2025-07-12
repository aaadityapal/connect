-- Create table for storing geofence locations
CREATE TABLE IF NOT EXISTS `geofence_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Location name',
  `address` text NOT NULL COMMENT 'Physical address of the location',
  `latitude` decimal(10,7) NOT NULL COMMENT 'Latitude coordinate',
  `longitude` decimal(10,7) NOT NULL COMMENT 'Longitude coordinate',
  `radius` int(11) NOT NULL DEFAULT 50 COMMENT 'Radius in meters',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this location is active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_geofence_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores geofence locations for attendance tracking';

-- Create a table to associate users with specific geofence locations
CREATE TABLE IF NOT EXISTS `user_geofence_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User ID',
  `geofence_location_id` int(11) NOT NULL COMMENT 'Geofence location ID',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this is the primary location for the user',
  `effective_from` date NOT NULL COMMENT 'Date from which this assignment is effective',
  `effective_to` date DEFAULT NULL COMMENT 'Date until which this assignment is effective (NULL means indefinite)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_geofence` (`user_id`,`geofence_location_id`),
  KEY `fk_geofence_location` (`geofence_location_id`),
  CONSTRAINT `fk_user_geofence_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_geofence_location` FOREIGN KEY (`geofence_location_id`) REFERENCES `geofence_locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Associates users with geofence locations';

-- Insert a default location (example - can be modified)
INSERT INTO `geofence_locations` (`name`, `address`, `latitude`, `longitude`, `radius`)
VALUES ('Main Office', 'Company Headquarters', 28.636941, 77.302690, 50); 