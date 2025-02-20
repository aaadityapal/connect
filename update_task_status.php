<?php
// Prevent any output before our JSON response
ob_start();

// Set error handling to prevent HTML error output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON header first
header('Content-Type: application/json');

try {
    require_once 'config.php';
    session_start();

    // Log the raw input for debugging
    error_log("Raw input: " . file_get_contents("php://input"));

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received: ' . json_last_error_msg());
    }

    // Log decoded data
    error_log("Decoded data: " . print_r($data, true));

    // Validate required parameters
    if (!isset($data['type']) || !isset($data['id']) || !isset($data['status'])) {
        throw new Exception('Missing required parameters');
    }

    $type = $data['type'];
    $id = intval($data['id']);
    $status = $data['status'];

    // Validate status
    $valid_statuses = ['pending', 'in_progress', 'completed'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status value');
    }

    // Get database connection
    $pdo = getDBConnection();

    $task_id = null;

    if ($type === 'stage') {
        // Update stage status
        $stmt = $pdo->prepare("UPDATE task_stages SET status = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for update");
        }
        
        if (!$stmt->execute([$status, $id])) {
            throw new Exception("Execute failed for update");
        }

        // Get task_id
        $stmt = $pdo->prepare("SELECT task_id FROM task_stages WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for select");
        }
        
        if (!$stmt->execute([$id])) {
            throw new Exception("Execute failed for select");
        }
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("Stage not found");
        }
        $task_id = $row['task_id'];

    } elseif ($type === 'substage') {
        // Update substage status
        $stmt = $pdo->prepare("UPDATE task_substages SET status = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for update");
        }
        
        if (!$stmt->execute([$status, $id])) {
            throw new Exception("Execute failed for update");
        }

        // Get task_id
        $stmt = $pdo->prepare("
            SELECT ts.task_id 
            FROM task_substages tss 
            JOIN task_stages ts ON tss.stage_id = ts.id 
            WHERE tss.id = ?
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed for select");
        }
        
        if (!$stmt->execute([$id])) {
            throw new Exception("Execute failed for select");
        }
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("Substage not found");
        }
        $task_id = $row['task_id'];
    } else {
        throw new Exception('Invalid type value');
    }

    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'task_id' => $task_id
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Error in update_task_status.php: " . $e->getMessage());

    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Send error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

