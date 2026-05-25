CREATE TABLE IF NOT EXISTS master_loop_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  master_loop_id INT NOT NULL,
  master_loop_name VARCHAR(255) NOT NULL,
  client_id INT NOT NULL,
  client_name VARCHAR(255) NOT NULL,
  client_phone VARCHAR(50) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Assigned',
  current_step_order INT NOT NULL DEFAULT 1,
  next_send_at DATETIME DEFAULT NULL,
  last_sent_at DATETIME DEFAULT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_master_loop_client (master_loop_id, client_id),
  INDEX idx_master_loop_id (master_loop_id),
  INDEX idx_client_id (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
