ALTER TABLE campaigns
  ADD COLUMN template_key VARCHAR(100) DEFAULT NULL AFTER template_id,
  ADD COLUMN template_language VARCHAR(20) DEFAULT 'en_US' AFTER template_key,
  ADD COLUMN template_body TEXT DEFAULT NULL AFTER template_language,
  ADD COLUMN media_header_type VARCHAR(20) DEFAULT 'NONE' AFTER template_body,
  ADD COLUMN media_path VARCHAR(255) DEFAULT NULL AFTER media_header_type,
  ADD COLUMN media_filename VARCHAR(255) DEFAULT NULL AFTER media_path,
  ADD COLUMN media_wa_id VARCHAR(255) DEFAULT NULL AFTER media_filename,
  ADD COLUMN media_wa_id_updated_at DATETIME DEFAULT NULL AFTER media_wa_id,
  ADD COLUMN last_error TEXT DEFAULT NULL AFTER updated_at;

ALTER TABLE campaign_deliveries
  ADD COLUMN client_name VARCHAR(100) DEFAULT NULL AFTER client_id,
  ADD COLUMN client_phone VARCHAR(20) DEFAULT NULL AFTER client_name,
  ADD COLUMN template_name VARCHAR(100) DEFAULT NULL AFTER client_phone,
  ADD COLUMN whatsapp_message_id VARCHAR(255) DEFAULT NULL AFTER template_name;

DROP TABLE IF EXISTS campaign_message_logs;

CREATE TABLE campaign_message_logs (
  id INT(11) NOT NULL AUTO_INCREMENT,
  campaign_id INT(11) NOT NULL,
  campaign_delivery_id INT(11) DEFAULT NULL,
  client_id INT(11) DEFAULT NULL,
  client_name VARCHAR(100) DEFAULT NULL,
  client_phone VARCHAR(20) DEFAULT NULL,
  template_name VARCHAR(100) DEFAULT NULL,
  message_id VARCHAR(255) DEFAULT NULL,
  status VARCHAR(30) NOT NULL,
  details JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_campaign_id (campaign_id),
  KEY idx_campaign_delivery_id (campaign_delivery_id),
  KEY idx_message_id (message_id),
  CONSTRAINT fk_campaign_message_logs_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_message_logs_delivery FOREIGN KEY (campaign_delivery_id) REFERENCES campaign_deliveries(id) ON DELETE SET NULL
);
