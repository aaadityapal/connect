-- Update stage_chat_messages table to add read status columns
ALTER TABLE stage_chat_messages
ADD COLUMN message_read TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether message has been read (0=unread, 1=read)',
ADD COLUMN read_timestamp DATETIME NULL COMMENT 'When the message was marked as read';

-- Add index for performance on queries that filter by read status
ALTER TABLE stage_chat_messages
ADD INDEX idx_message_read (message_read);

-- Add combined index for querying unread messages for a specific stage/substage
ALTER TABLE stage_chat_messages
ADD INDEX idx_stage_substage_read (stage_id, substage_id, message_read); 