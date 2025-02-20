<?php
require_once '../../config/db_connect.php';
require_once '../../vendor/autoload.php'; // For WebSocket library

class ChatAPI {
    private $conn;
    private $user_id;
    
    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }
    
    // Get user's conversations
    public function getConversations() {
        $query = "SELECT 
                    c.*, 
                    MAX(m.sent_at) as last_message_time,
                    COUNT(CASE WHEN ms.status = 'delivered' AND m.sender_id != ? THEN 1 END) as unread_count
                 FROM conversations c
                 JOIN conversation_participants cp ON c.id = cp.conversation_id
                 LEFT JOIN messages m ON c.id = m.conversation_id
                 LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
                 WHERE cp.user_id = ?
                 GROUP BY c.id
                 ORDER BY last_message_time DESC";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $this->user_id, $this->user_id, $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get messages for a conversation
    public function getMessages($conversation_id, $limit = 50, $offset = 0) {
        // Verify user is part of conversation
        if (!$this->isParticipant($conversation_id)) {
            return ['error' => 'Unauthorized'];
        }
        
        $query = "SELECT 
                    m.*, 
                    u.username as sender_name,
                    ms.status as message_status
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
                 WHERE m.conversation_id = ?
                 ORDER BY m.sent_at DESC
                 LIMIT ? OFFSET ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiii", $this->user_id, $conversation_id, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Send a message
    public function sendMessage($conversation_id, $content, $type = 'text') {
        if (!$this->isParticipant($conversation_id)) {
            return ['error' => 'Unauthorized'];
        }
        
        $this->conn->begin_transaction();
        
        try {
            // Insert message
            $query = "INSERT INTO messages (conversation_id, sender_id, message_type, content) 
                     VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iiss", $conversation_id, $this->user_id, $type, $content);
            $stmt->execute();
            $message_id = $stmt->insert_id;
            
            // Update conversation timestamp
            $this->conn->query("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP 
                              WHERE id = $conversation_id");
            
            // Create message status for all participants
            $this->createMessageStatus($message_id, $conversation_id);
            
            $this->conn->commit();
            
            // Trigger WebSocket event
            $this->notifyParticipants($conversation_id, $message_id);
            
            return ['success' => true, 'message_id' => $message_id];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['error' => $e->getMessage()];
        }
    }
    
    // Create new conversation
    public function createConversation($participants, $name = null, $type = 'individual') {
        $this->conn->begin_transaction();
        
        try {
            // Create conversation
            $query = "INSERT INTO conversations (name, type) VALUES (?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ss", $name, $type);
            $stmt->execute();
            $conversation_id = $stmt->insert_id;
            
            // Add participants
            $participants[] = $this->user_id; // Add current user
            $participants = array_unique($participants);
            
            foreach ($participants as $user_id) {
                $role = ($user_id == $this->user_id) ? 'admin' : 'member';
                $query = "INSERT INTO conversation_participants (conversation_id, user_id, role) 
                         VALUES (?, ?, ?)";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("iis", $conversation_id, $user_id, $role);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return ['success' => true, 'conversation_id' => $conversation_id];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['error' => $e->getMessage()];
        }
    }
    
    // Helper methods
    private function isParticipant($conversation_id) {
        $query = "SELECT 1 FROM conversation_participants 
                 WHERE conversation_id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $conversation_id, $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function createMessageStatus($message_id, $conversation_id) {
        $query = "INSERT INTO message_status (message_id, user_id, status)
                 SELECT ?, user_id, 'delivered'
                 FROM conversation_participants
                 WHERE conversation_id = ? AND user_id != ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $message_id, $conversation_id, $this->user_id);
        $stmt->execute();
    }
    
    private function notifyParticipants($conversation_id, $message_id) {
        // Implement WebSocket notification here
        // You'll need to use a WebSocket library like Ratchet or ReactPHP
    }
} 