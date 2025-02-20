<?php
session_start();
require_once '../../config/db_connect.php';
require_once 'ChatAPI.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$chat = new ChatAPI($conn, $_SESSION['user_id']);
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_conversations':
        echo json_encode($chat->getConversations());
        break;
        
    case 'get_messages':
        $conversation_id = $_POST['conversation_id'] ?? 0;
        $limit = $_POST['limit'] ?? 50;
        $offset = $_POST['offset'] ?? 0;
        echo json_encode($chat->getMessages($conversation_id, $limit, $offset));
        break;
        
    case 'send_message':
        $conversation_id = $_POST['conversation_id'] ?? 0;
        $content = $_POST['content'] ?? '';
        $type = $_POST['type'] ?? 'text';
        echo json_encode($chat->sendMessage($conversation_id, $content, $type));
        break;
        
    case 'create_conversation':
        $participants = $_POST['participants'] ?? [];
        $name = $_POST['name'] ?? null;
        $type = $_POST['type'] ?? 'individual';
        echo json_encode($chat->createConversation($participants, $name, $type));
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
} 