<?php
require_once 'config.php';
require_once 'config/auth.php';

// Ensure user is authenticated
if (!isAuthenticated()) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['file_id']) || !isset($data['comment']) || empty(trim($data['comment']))) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

try {
    $db = getDBConnection();
    
    // Insert the new comment
    $stmt = $db->prepare("
        INSERT INTO file_comments (file_id, user_id, content) 
        VALUES (:file_id, :user_id, :content)
    ");
    
    $stmt->execute([
        ':file_id' => $data['file_id'],
        ':user_id' => $_SESSION['user_id'],
        ':content' => trim($data['comment'])
    ]);
    
    // Fetch all comments for this file to return updated list
    $stmt = $db->prepare("
        SELECT 
            fc.id,
            fc.content,
            fc.created_at,
            u.name as user_name
        FROM file_comments fc
        JOIN users u ON fc.user_id = u.id
        WHERE fc.file_id = :file_id
        ORDER BY fc.created_at DESC
    ");
    
    $stmt->execute([':file_id' => $data['file_id']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully',
        'comments' => $comments
    ]);

} catch (PDOException $e) {
    error_log("Error adding comment: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?> 