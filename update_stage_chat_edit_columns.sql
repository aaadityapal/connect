-- Update stage_chat_messages table to add edit status columns
ALTER TABLE stage_chat_messages 
ADD COLUMN edited TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag indicating if the message has been edited',
ADD COLUMN edited_timestamp DATETIME NULL COMMENT 'Timestamp when the message was edited';

-- Add indexes for better performance
ALTER TABLE stage_chat_messages 
ADD INDEX idx_edited (edited); 