<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get and decode JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['type']) || !isset($data['id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$type = $data['type'];
$id = intval($data['id']);
$status = $data['status'];

// Validate status
$valid_statuses = ['pending', 'in_progress', 'completed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $conn->begin_transaction();

    if ($type === 'stage') {
        // Update stage status
        $stmt = $conn->prepare("UPDATE task_stages SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        // Get task_id for the stage
        $stmt = $conn->prepare("SELECT task_id FROM task_stages WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $task_id = $result->fetch_assoc()['task_id'];

    } elseif ($type === 'substage') {
        // Update substage status
        $stmt = $conn->prepare("UPDATE task_substages SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        // Get task_id for the substage
        $stmt = $conn->prepare("
            SELECT ts.task_id 
            FROM task_substages tss 
            JOIN task_stages ts ON tss.stage_id = ts.id 
            WHERE tss.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $task_id = $result->fetch_assoc()['task_id'];
    } else {
        throw new Exception('Invalid type');
    }

    // Log the status change
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        INSERT INTO status_change_log 
        (user_id, type, item_id, old_status, new_status, changed_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isiss", $user_id, $type, $id, $data['old_status'], $status);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Status updated successfully',
        'task_id' => $task_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?> 