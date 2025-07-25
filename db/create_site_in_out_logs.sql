CREATE TABLE IF NOT EXISTS site_in_out_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action ENUM('site_in', 'site_out') NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    address VARCHAR(255),
    geofence_location_id INT,
    distance_from_geofence DECIMAL(8,3),
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    device_info VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (geofence_location_id) REFERENCES geofence_locations(id)
); 