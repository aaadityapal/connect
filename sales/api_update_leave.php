<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

// Database connection
require_once '../config/db_connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    $leave_type_id = isset($_POST['leave_type']) ? intval($_POST['leave_type']) : 0;
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    // Validate required fields
    if (!$leave_id || empty($leave_type_id) || empty($start_date) || empty($end_date) || empty($reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }

    // Verify the leave request belongs to the user and is pending
    $stmt = $pdo->prepare("SELECT id, status FROM leave_request WHERE id = ? AND user_id = ?");
    $stmt->execute([$leave_id, $user_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit;
    }

    if ($leave['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only pending leave requests can be edited']);
        exit;
    }

    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    // Check if start date is more than 15 days in the past
    $fifteenDaysAgo = clone $today;
    $fifteenDaysAgo->modify('-15 days');

    if ($start < $fifteenDaysAgo) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Leave start date cannot be more than 15 days in the past. Please select a date from ' . $fifteenDaysAgo->format('Y-m-d') . ' onwards.'
        ]);
        exit;
    }

    if ($end < $start) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'End date cannot be before start date']);
        exit;
    }

    // Calculate duration
    $interval = $start->diff($end);
    $duration = $interval->days + 1;

    // Verify leave type exists and is active
    $stmt = $pdo->prepare("SELECT id, name FROM leave_types WHERE id = ? AND status = 'active'");
    $stmt->execute([$leave_type_id]);
    $leave_type = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave_type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid leave type selected']);
        exit;
    }

    // Update leave request
    $sql = "UPDATE leave_request 
            SET leave_type = ?,
                start_date = ?,
                end_date = ?,
                reason = ?,
                duration = ?,
                updated_at = NOW(),
                updated_by = ?
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $leave_type_id,
        $start_date,
        $end_date,
        $reason,
        $duration,
        $user_id,
        $leave_id
    ]);

    if ($result) {
        // Log the activity (optional - won't fail if table doesn't exist)
        try {
            $log_sql = "INSERT INTO activity_logs (user_id, action, description, created_at) 
                       VALUES (?, 'leave_request_updated', ?, NOW())";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([
                $user_id,
                "Leave request #$leave_id updated - {$duration} day(s) from {$start_date} to {$end_date}"
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("Failed to log activity (table may not exist): " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Leave request updated successfully',
            'leave_request_id' => $leave_id,
            'duration' => $duration,
            'leave_type' => $leave_type['name']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update leave request']);
    }

} catch (PDOException $e) {
    error_log("Database error in api_update_leave.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in api_update_leave.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>