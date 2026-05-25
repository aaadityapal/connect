-- Master installer for WhatsApp Loop module
-- Runs drops, creates, and seed data in proper order

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Schemas
SOURCE whatsapp_loop_clients.sql;
SOURCE whatsapp_loop_templates.sql;
SOURCE whatsapp_loop_campaigns.sql;
SOURCE whatsapp_loop_campaign_deliveries.sql;
SOURCE whatsapp_loop_settings.sql;
SOURCE whatsapp_loop_tags.sql;
SOURCE whatsapp_loop_client_tags.sql;
SOURCE whatsapp_loop_user_activities.sql;
SOURCE whatsapp_loop_users.sql;
SOURCE whatsapp_loop_sequences.sql;
SOURCE whatsapp_loop_sequence_steps.sql;
SOURCE whatsapp_loop_sequence_subscriptions.sql;
SOURCE whatsapp_loop_sequence_deliveries.sql;

-- 2. Seeds
SOURCE whatsapp_loop_seed_templates.sql;
SOURCE whatsapp_loop_seed_clients_campaigns.sql;
SOURCE whatsapp_loop_seed_sequences.sql;
SOURCE whatsapp_loop_seed_subscriptions.sql;

SET FOREIGN_KEY_CHECKS = 1;

-- Done
SELECT 'whatsapp_loop_master completed' AS status;
