<?php
/**
 * Update Substage Status API Endpoint
 * This file handles updating the status of a substage
 */

// Prevent any PHP warnings or notices from being output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON content type header first thing
header('Content-Type: application/json');

// Buffer output to catch any unexpected output
ob_start();

try {
    // Include database connection - fixing the path to use the correct file
    require_once 'config/db_connect.php';
    
    // Check if user is logged in
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }
    
    // Get the current user ID
    $userId = $_SESSION['user_id'];
    
    // Get user role
    $userRole = $_SESSION['role'] ?? '';
    $isAdmin = in_array($userRole, ['admin', 'HR', 'Senior Manager (Studio)']);
    
    // Check if request is POST and has JSON content
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
        !isset($_SERVER['CONTENT_TYPE']) || 
        strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
        throw new Exception('Invalid request method or content type');
    }
    
    // Get POST data
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Check if required fields are present
    if (!isset($data['substage_id']) || !isset($data['status'])) {
        throw new Exception('Missing required fields');
    }
    
    $substageId = (int)$data['substage_id'];
    $newStatus = $data['status'];
    
    // Validate status value
    $validStatuses = ['not_started', 'in_progress', 'completed'];
    $adminOnlyStatuses = ['in_review', 'on_hold'];
    
    // Only admins can set in_review or on_hold statuses
    if (in_array($newStatus, $adminOnlyStatuses) && !$isAdmin) {
        throw new Exception('You do not have permission to set this status');
    }
    
    // Add admin-only statuses to valid statuses if admin
    if ($isAdmin) {
        $validStatuses = array_merge($validStatuses, $adminOnlyStatuses);
    }
    
    if (!in_array($newStatus, $validStatuses)) {
        throw new Exception('Invalid status value');
    }
    
    // Test DB connection
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Database connection failed');
    }
    
    // Check if user is authorized to update this substage
    $checkQuery = "
        SELECT ps.*, s.assigned_to as stage_assigned_to, p.assigned_to as project_assigned_to 
        FROM project_substages ps
        JOIN project_stages s ON ps.stage_id = s.id
        JOIN projects p ON s.project_id = p.id
        WHERE ps.id = ?
    ";
    
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$substageId]);
    $substage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$substage) {
        throw new Exception('Substage not found');
    }
    
    // Check if trying to change from admin-only status to another status
    if (!$isAdmin && in_array($substage['status'], $adminOnlyStatuses)) {
        throw new Exception('This substage is currently in an admin-controlled status and cannot be modified');
    }
    
    // Verify user has permission to update the substage
    $hasPermission = (
        $isAdmin || 
        $userId == $substage['assigned_to'] || 
        $userId == $substage['stage_assigned_to'] || 
        $userId == $substage['project_assigned_to']
    );
    
    if (!$hasPermission) {
        throw new Exception('You do not have permission to update this substage');
    }
    
    // Update the status
    $updateQuery = "UPDATE project_substages SET status = ?, updated_at = NOW() WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $result = $updateStmt->execute([$newStatus, $substageId]);
    
    if (!$result) {
        throw new Exception('Failed to update status');
    }
    
    // Clear any buffered output
    ob_clean();
    
    // Return success response
    echo json_encode(['success' => true, 'message' => 'Substage status updated successfully']);

} catch (Exception $e) {
    // Clear any buffered output
    ob_clean();
    
    // Log the error
    error_log("Error in update_substage_status.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// End the script to prevent any additional output
exit;
?>