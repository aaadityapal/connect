<?php
/**
 * Get Unread Messages Count
 * This file returns the count of unread messages for a stage or substage
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

// Check if stage_id is provided
if (!isset($_GET["stage_id"])) {
    echo json_encode(["success" => false, "message" => "Stage ID is required"]);
    exit;
}

$stageId = $_GET["stage_id"];
$substageId = isset($_GET["substage_id"]) ? $_GET["substage_id"] : null;

try {
    // Prepare SQL query based on whether it's a stage or substage
    if ($substageId) {
        // For substage
        $sql = "SELECT COUNT(*) as unread_count FROM stage_chat_messages 
                WHERE stage_id = ? AND substage_id = ? 
                AND user_id != ? AND message_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $stageId, $substageId, $userId);
    } else {
        // For stage
        $sql = "SELECT COUNT(*) as unread_count FROM stage_chat_messages 
                WHERE stage_id = ? AND substage_id IS NULL 
                AND user_id != ? AND message_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $stageId, $userId);
    }
    
    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Return the unread count
    echo json_encode([
        "success" => true,
        "unread_count" => (int)$row["unread_count"]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?> 