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

// Get leave request ID
$leave_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$leave_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Leave request ID is required']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];

    // Fetch leave request details
    $sql = "SELECT 
                lr.id,
                lr.user_id,
                lr.leave_type,
                lt.name as leave_type_name,
                lt.color_code,
                lr.start_date,
                lr.end_date,
                lr.reason,
                lr.duration,
                lr.duration_type,
                lr.day_type,
                lr.status,
                lr.created_at,
                lr.action_reason,
                lr.action_by,
                lr.action_at,
                lr.manager_approval,
                lr.manager_action_reason,
                lr.manager_action_by,
                lr.manager_action_at,
                lr.hr_approval,
                lr.hr_action_reason,
                lr.hr_action_by,
                lr.hr_action_at,
                u_action.username as action_by_name,
                u_manager.username as manager_action_by_name,
                u_hr.username as hr_action_by_name
            FROM leave_request lr
            LEFT JOIN leave_types lt ON lr.leave_type = lt.id
            LEFT JOIN users u_action ON lr.action_by = u_action.id
            LEFT JOIN users u_manager ON lr.manager_action_by = u_manager.id
            LEFT JOIN users u_hr ON lr.hr_action_by = u_hr.id
            WHERE lr.id = ? AND lr.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$leave_id, $user_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit;
    }

    // Format dates
    $leave['start_date_formatted'] = date('M d, Y', strtotime($leave['start_date']));
    $leave['end_date_formatted'] = date('M d, Y', strtotime($leave['end_date']));
    $leave['created_at_formatted'] = date('M d, Y \a\t h:i A', strtotime($leave['created_at']));
    $leave['duration_display'] = $leave['duration'] . ($leave['duration'] == 1 ? ' day' : ' days');

    // Status badge class
    $status_class_map = [
        'pending' => 'status-new',
        'approved' => 'status-qualified',
        'rejected' => 'status-lost',
        'cancelled' => 'status-contacted'
    ];
    $leave['status_class'] = isset($status_class_map[$leave['status']]) ? $status_class_map[$leave['status']] : 'status-new';
    $leave['status_display'] = ucfirst($leave['status']);

    // Build activity timeline
    $timeline = [];

    // Submitted
    $timeline[] = [
        'date' => date('M d, Y - h:i A', strtotime($leave['created_at'])),
        'action' => 'Leave request submitted.'
    ];

    // Manager approval
    if ($leave['manager_action_at']) {
        $action_text = $leave['manager_approval'] === 'approved' ? 'approved by manager' : 'rejected by manager';
        $timeline[] = [
            'date' => date('M d, Y - h:i A', strtotime($leave['manager_action_at'])),
            'action' => 'Request ' . $action_text . ($leave['manager_action_by_name'] ? ' (' . $leave['manager_action_by_name'] . ')' : '') . '.',
            'reason' => $leave['manager_action_reason']
        ];
    }

    // HR approval
    if ($leave['hr_action_at']) {
        $action_text = $leave['hr_approval'] === 'approved' ? 'approved by HR' : 'rejected by HR';
        $timeline[] = [
            'date' => date('M d, Y - h:i A', strtotime($leave['hr_action_at'])),
            'action' => 'Request ' . $action_text . ($leave['hr_action_by_name'] ? ' (' . $leave['hr_action_by_name'] . ')' : '') . '.',
            'reason' => $leave['hr_action_reason']
        ];
    }

    // Final action
    if ($leave['action_at']) {
        $timeline[] = [
            'date' => date('M d, Y - h:i A', strtotime($leave['action_at'])),
            'action' => 'Request ' . $leave['status'] . ($leave['action_by_name'] ? ' by ' . $leave['action_by_name'] : '') . '.',
            'reason' => $leave['action_reason']
        ];
    } elseif ($leave['status'] === 'pending') {
        $timeline[] = [
            'date' => 'Pending',
            'action' => 'Awaiting manager approval.'
        ];
    }

    $leave['timeline'] = $timeline;

    echo json_encode([
        'success' => true,
        'data' => $leave
    ]);

} catch (PDOException $e) {
    error_log("Database error in api_get_leave_detail.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
} catch (Exception $e) {
    error_log("Error in api_get_leave_detail.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred.']);
}
?>