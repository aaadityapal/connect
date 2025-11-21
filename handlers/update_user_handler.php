<?php
/**
 * Update User Handler
 * Updates user role, position, designation, and status
 */

session_start();
require_once __DIR__ . '/../config/db_connect.php';

// Set response header
header('Content-Type: application/json');

// Check if user is authenticated and is an Admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$userId = isset($_POST['userId']) ? intval($_POST['userId']) : null;
$position = isset($_POST['position']) ? trim($_POST['position']) : null;
$designation = isset($_POST['designation']) ? trim($_POST['designation']) : null;
$department = isset($_POST['department']) ? trim($_POST['department']) : null;
$role = isset($_POST['role']) ? trim($_POST['role']) : null;
$status = isset($_POST['status']) ? trim($_POST['status']) : null;

// Validate required fields
if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

if (!$position || !$designation || !$department || !$role || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate role
$validRoles = ['Admin', 'Manager', 'Purchase Manager', 'Employee', 'HR', 'Finance', 'Supervisor'];
if (!in_array($role, $validRoles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
    exit;
}

// Validate status
$validStatuses = ['active', 'inactive'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status selected']);
    exit;
}

try {
    // Check if user exists
    $checkQuery = "SELECT id FROM users WHERE id = ? AND deleted_at IS NULL";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$userId]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Prepare update query
    $updateQuery = "
        UPDATE users 
        SET 
            position = ?,
            designation = ?,
            department = ?,
            role = ?,
            status = ?,
            modified_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ";

    $stmt = $pdo->prepare($updateQuery);
    $result = $stmt->execute([
        $position,
        $designation,
        $department,
        $role,
        $status,
        $userId
    ]);

    if ($result) {
        // Log the change (optional)
        $logQuery = "
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, changes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        $changes = json_encode([
            'position' => $position,
            'designation' => $designation,
            'department' => $department,
            'role' => $role,
            'status' => $status
        ]);
        
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([
            $_SESSION['user_id'],
            'UPDATE',
            'user',
            $userId,
            $changes
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'userId' => $userId,
                'position' => $position,
                'designation' => $designation,
                'department' => $department,
                'role' => $role,
                'status' => $status
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
