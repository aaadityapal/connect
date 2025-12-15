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

try {
    $user_id = $_SESSION['user_id'];

    // Get filter parameters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $type_filter = isset($_GET['type']) ? intval($_GET['type']) : 0;
    $start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    // If no date filters provided, default to current month
    if (empty($start_date_filter) && empty($end_date_filter)) {
        $start_date_filter = date('Y-m-01'); // First day of current month
        $end_date_filter = date('Y-m-t');    // Last day of current month
    }

    // Build query
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
                lr.hr_approval,
                u_action.username as action_by_name
            FROM leave_request lr
            LEFT JOIN leave_types lt ON lr.leave_type = lt.id
            LEFT JOIN users u_action ON lr.action_by = u_action.id
            WHERE lr.user_id = ?";

    $params = [$user_id];

    // Apply filters
    if (!empty($status_filter)) {
        $sql .= " AND lr.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($type_filter)) {
        $sql .= " AND lr.leave_type = ?";
        $params[] = $type_filter;
    }

    // Date range filter - check for any overlap with the filter range
    if (!empty($start_date_filter) && !empty($end_date_filter)) {
        // Leave overlaps with filter range if:
        // Leave starts before filter ends AND leave ends after filter starts
        $sql .= " AND lr.start_date <= ? AND lr.end_date >= ?";
        $params[] = $end_date_filter;
        $params[] = $start_date_filter;
    } elseif (!empty($start_date_filter)) {
        // Only start date filter - show leaves that end on or after this date
        $sql .= " AND lr.end_date >= ?";
        $params[] = $start_date_filter;
    } elseif (!empty($end_date_filter)) {
        // Only end date filter - show leaves that start on or before this date
        $sql .= " AND lr.start_date <= ?";
        $params[] = $end_date_filter;
    }

    $sql .= " ORDER BY lr.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates and add additional info
    foreach ($leave_requests as &$request) {
        $request['start_date_formatted'] = date('M d, Y', strtotime($request['start_date']));
        $request['end_date_formatted'] = date('M d, Y', strtotime($request['end_date']));
        $request['created_at_formatted'] = date('M d, Y', strtotime($request['created_at']));
        $request['duration_display'] = $request['duration'] . ($request['duration'] == 1 ? ' day' : ' days');

        // Status badge class
        $status_class_map = [
            'pending' => 'status-new',
            'approved' => 'status-qualified',
            'rejected' => 'status-lost',
            'cancelled' => 'status-contacted'
        ];
        $request['status_class'] = isset($status_class_map[$request['status']]) ? $status_class_map[$request['status']] : 'status-new';
        $request['status_display'] = ucfirst($request['status']);
    }

    echo json_encode([
        'success' => true,
        'data' => $leave_requests,
        'count' => count($leave_requests),
        'filter_applied' => [
            'start_date' => $start_date_filter,
            'end_date' => $end_date_filter
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in api_get_leave_requests.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
} catch (Exception $e) {
    error_log("Error in api_get_leave_requests.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred.']);
}
?>