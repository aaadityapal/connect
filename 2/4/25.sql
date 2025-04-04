
CREATE TABLE chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    file_url VARCHAR(255) DEFAULT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

CREATE TABLE chat_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Add file_id column to chat_messages table
ALTER TABLE chat_messages 
ADD COLUMN file_id INT NULL,
ADD FOREIGN KEY (file_id) REFERENCES chat_files(id);

CREATE TABLE message_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_reaction (message_id, user_id)
);


ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL;


-- Add columns to chat_messages table if they don't exist
ALTER TABLE chat_messages
ADD COLUMN IF NOT EXISTS group_id INT NULL,
ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;

-- Add columns to users table if they don't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS status ENUM('online', 'away', 'offline') DEFAULT 'offline',
ADD COLUMN IF NOT EXISTS last_active TIMESTAMP NULL DEFAULT NULL;

-- Create reactions table if it doesn't exist
CREATE TABLE IF NOT EXISTS chat_message_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create group members table if it doesn't exist
CREATE TABLE IF NOT EXISTS chat_group_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_messages_sent_at ON chat_messages(sent_at);
CREATE INDEX IF NOT EXISTS idx_messages_receiver ON chat_messages(receiver_id);
CREATE INDEX IF NOT EXISTS idx_messages_sender ON chat_messages(sender_id);
CREATE INDEX IF NOT EXISTS idx_user_last_active ON users(last_active);



ALTER TABLE chat_messages
ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL DEFAULT NULL,
ADD INDEX idx_read_status (read_at, receiver_id);

ALTER TABLE chat_messages
ADD INDEX idx_message_check (sent_at, receiver_id, read_at, deleted_at);


CREATE TABLE IF NOT EXISTS chat_message_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_reaction (message_id, user_id, reaction)
);