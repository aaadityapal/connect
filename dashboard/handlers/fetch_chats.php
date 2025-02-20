<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to prevent unwanted output
ob_start();

header('Content-Type: application/json');
session_start();

try {
    // Log the request
    error_log("Fetch chats request received");

    // Check if the config file exists
    $config_path = __DIR__ . '/../../config/db_connect.php';
    if (!file_exists($config_path)) {
        throw new Exception('Database configuration file not found');
    }

    require_once $config_path;

    // Verify database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Connection not established'));
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('User not authenticated');
    }

    // Log the user ID
    error_log("Processing request for user ID: " . $user_id);

    // Check if tables exist
    $check_tables = $conn->query("
        SELECT COUNT(*) as count 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        AND table_name IN ('chats', 'messages')
    ");
    
    if (!$check_tables) {
        throw new Exception('Error checking tables: ' . $conn->error);
    }
    
    $result = $check_tables->fetch_assoc();
    
    if ($result['count'] < 2) {
        // Return empty data if tables don't exist yet
        ob_clean();
        echo json_encode([
            'status' => 'success',
            'data' => []
        ]);
        exit;
    }

    // Get all chats for the user
    $query = "SELECT 
        c.id,
        u.username as name,
        COALESCE(u.profile_picture, 'assets/images/default-avatar.png') as avatar,
        m.content as lastMessage,
        m.created_at as lastMessageTime,
        COUNT(CASE WHEN m.read_at IS NULL AND m.sender_id != ? THEN 1 END) as unreadCount
    FROM users u
    LEFT JOIN chats c ON (c.user1_id = ? AND c.user2_id = u.id) 
        OR (c.user2_id = ? AND c.user1_id = u.id)
    LEFT JOIN messages m ON m.chat_id = c.id
    WHERE u.id != ? 
    AND u.status = 'active'
    AND u.deleted_at IS NULL
    GROUP BY u.id
    ORDER BY m.created_at DESC NULLS LAST";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }

    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Failed to get result set: ' . $stmt->error);
    }

    $chats = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['lastMessageTime']) {
            $row['lastMessageTime'] = date('h:i A', strtotime($row['lastMessageTime']));
        }
        $chats[] = $row;
    }

    // Clear any output and send response
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'data' => $chats
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Chat API Error: " . $e->getMessage());
    
    // Clear any output
    ob_clean();
    
    // Send error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching chats: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush(); 