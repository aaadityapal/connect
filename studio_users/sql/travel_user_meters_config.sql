CREATE TABLE IF NOT EXISTS travel_user_meters_config (
    user_id INT PRIMARY KEY,
    require_meters TINYINT(1) DEFAULT 0
);
