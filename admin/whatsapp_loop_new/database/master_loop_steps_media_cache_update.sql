ALTER TABLE master_loop_steps
  ADD COLUMN media_wa_id VARCHAR(255) DEFAULT NULL,
  ADD COLUMN media_wa_id_updated_at DATETIME DEFAULT NULL;
