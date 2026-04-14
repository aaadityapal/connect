-- ==========================================
-- TRAVEL TRANSPORT RATES TABLE
-- ==========================================

CREATE TABLE IF NOT EXISTS `travel_transport_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transport_mode` varchar(50) NOT NULL,
  `rate_per_km` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transport_mode` (`transport_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- INITIAL POPULATION WITH COMMON MODES
-- ==========================================

INSERT IGNORE INTO `travel_transport_rates` (`transport_mode`, `rate_per_km`) VALUES 
('Auto', 0.00),
('Bike', 5.00),
('Bike Taxi', 0.00),
('Bus', 0.00),
('Cab', 0.00),
('Car', 10.00),
('E-Rickshaw', 0.00),
('Flight', 0.00),
('Metro', 0.00),
('Other', 0.00),
('Train', 0.00);
