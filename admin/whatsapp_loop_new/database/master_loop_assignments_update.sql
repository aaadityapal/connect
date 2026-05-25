ALTER TABLE master_loop_assignments
  ADD COLUMN current_step_order INT NOT NULL DEFAULT 1,
  ADD COLUMN next_send_at DATETIME DEFAULT NULL,
  ADD COLUMN last_sent_at DATETIME DEFAULT NULL;
