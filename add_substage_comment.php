<?php
/**
 * API endpoint to add a comment to a substage
 */

session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$substageId = isset($data['substage_id']) ? intval($data['substage_id']) : 0;
$comment = isset($data['comment']) ? trim($data['comment']) : '';

// Validate required parameters
if (!$substageId || empty($comment)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

try {
    // Verify substage exists
    $checkSubstageQuery = "SELECT id FROM project_substages WHERE id = ? AND deleted_at IS NULL";
    $checkSubstageStmt = $conn->prepare($checkSubstageQuery);
    $checkSubstageStmt->bind_param("i", $substageId);
    $checkSubstageStmt->execute();
    $checkSubstageResult = $checkSubstageStmt->get_result();
    
    if ($checkSubstageResult->num_rows === 0) {
        throw new Exception('Substage not found');
    }
    
    // Get user's name for response
    $userQuery = "SELECT username FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userData = $userResult->fetch_assoc();
    $authorName = $userData['username'];
    
    // Insert comment
    $insertCommentQuery = "INSERT INTO comments (stage_id, substage_id, author_id, content, created_at) 
                           VALUES (NULL, ?, ?, ?, NOW())";
    $insertCommentStmt = $conn->prepare($insertCommentQuery);
    $insertCommentStmt->bind_param("iis", $substageId, $userId, $comment);
    $insertCommentStmt->execute();
    
    if ($insertCommentStmt->affected_rows === 0) {
        throw new Exception('Failed to add comment');
    }
    
    $commentId = $insertCommentStmt->insert_id;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully',
        'comment_id' => $commentId,
        'author_name' => $authorName
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
} 