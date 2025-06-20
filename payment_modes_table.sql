-- Create payment_modes table
CREATE TABLE IF NOT EXISTS payment_modes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  payment_mode VARCHAR(50) NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES project_payouts(id) ON DELETE CASCADE
);
