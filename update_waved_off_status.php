<?php
// Include database connection
require_once 'includes/db_connect.php';

// Get the action and attendance ID from the request
$action = isset($_POST['action']) ? $_POST['action'] : '';
$attendance_id = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : 0;

// Validate input
if (empty($action) || $attendance_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

// Update the waved_off status based on the action
if ($action === 'wave_off') {
    $sql = "UPDATE attendance SET waved_off = 1 WHERE id = ?";
    $message = 'Late coming has been waved off successfully';
} elseif ($action === 'undo_wave_off') {
    $sql = "UPDATE attendance SET waved_off = 0 WHERE id = ?";
    $message = 'Wave off has been undone successfully';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $attendance_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>