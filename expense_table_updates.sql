-- Add payment status columns to travel_expenses table
ALTER TABLE travel_expenses 
ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Pending' AFTER hr_status,
ADD COLUMN paid_on_date DATETIME DEFAULT NULL AFTER payment_status,
ADD COLUMN paid_by INT DEFAULT NULL AFTER paid_on_date;

-- Create expense payment logs table
CREATE TABLE IF NOT EXISTS expense_payment_logs (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  expense_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  paid_on_date DATETIME NOT NULL,
  paid_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (expense_id) REFERENCES travel_expenses(id) ON DELETE CASCADE,
  FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Add index for faster queries
CREATE INDEX idx_expense_payment_status ON travel_expenses(payment_status);
CREATE INDEX idx_expense_paid_on_date ON travel_expenses(paid_on_date); 