<?php
/**
 * Mark Chat Messages Read
 * This file marks all messages in a stage or substage chat as read for the current user
 */

// Include config file
require_once "config/db_connect.php";

// Check if user is logged in
session_start();
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "User not logged in"]);
    exit;
}

$userId = $_SESSION["user_id"];

// Get POST data
$postData = json_decode(file_get_contents("php://input"), true);

if (!isset($postData["stage_id"])) {
    echo json_encode(["success" => false, "message" => "Stage ID is required"]);
    exit;
}

$stageId = $postData["stage_id"];
$substageId = isset($postData["substage_id"]) ? $postData["substage_id"] : null;

try {
    // Prepare SQL query based on whether it's a stage or substage
    if ($substageId) {
        // For substage
        $sql = "UPDATE stage_chat_messages SET message_read = 1, read_timestamp = NOW() 
                WHERE stage_id = ? AND substage_id = ? 
                AND user_id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $stageId, $substageId, $userId);
    } else {
        // For stage
        $sql = "UPDATE stage_chat_messages SET message_read = 1, read_timestamp = NOW() 
                WHERE stage_id = ? AND substage_id IS NULL 
                AND user_id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $stageId, $userId);
    }
    
    // Execute the query
    $stmt->execute();
    
    // Return success
    echo json_encode([
        "success" => true,
        "message" => "Messages marked as read"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?> 