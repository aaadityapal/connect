<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Log access attempt
    error_log("Accessing get_leave_history.php");

    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Check for unique_id
    if (!isset($_GET['unique_id'])) {
        throw new Exception('Employee ID is required');
    }

    $employeeId = intval($_GET['unique_id']);

    // Debug log
    error_log("Fetching leaves for employee ID: " . $employeeId);

    // Updated query to include more user details
    $query = "
        SELECT 
            lr.*,
            m.username as manager_name,
            m.designation as manager_designation,
            m.department as manager_department,
            h.username as hr_name,
            h.designation as hr_designation,
            e.username as employee_name,
            e.designation as employee_designation,
            e.department as employee_department,
            e.unique_id as employee_code
        FROM leave_request lr
        LEFT JOIN users m ON m.id = lr.manager_action_by
        LEFT JOIN users h ON h.id = lr.hr_action_by
        LEFT JOIN users e ON e.id = lr.user_id
        WHERE lr.user_id = :unique_id
        ORDER BY lr.created_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['unique_id' => $employeeId]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug log
    error_log("Found " . count($leaves) . " leave records");

    // Format the leave data for frontend display
    $formattedLeaves = array_map(function($leave) {
        return [
            'id' => $leave['id'],
            'employee' => [
                'name' => $leave['employee_name'],
                'designation' => $leave['employee_designation'],
                'department' => $leave['employee_department'],
                'code' => $leave['employee_code']
            ],
            'leave_type' => ucfirst(str_replace('_', ' ', $leave['leave_type'])),
            'start_date' => $leave['start_date'],
            'end_date' => $leave['end_date'],
            'duration' => $leave['duration'],
            'reason' => htmlspecialchars($leave['reason']),
            'status' => getLeaveStatus($leave),
            'created_at' => $leave['created_at'],
            'approvals' => [
                'manager' => [
                    'status' => $leave['manager_approval'] ?? 'pending',
                    'by' => $leave['manager_name'] ?? 'Not Assigned',
                    'designation' => $leave['manager_designation'] ?? '',
                    'department' => $leave['manager_department'] ?? '',
                    'at' => $leave['manager_action_at'],
                    'reason' => $leave['manager_action_reason']
                ],
                'hr' => [
                    'status' => $leave['hr_approval'] ?? 'pending',
                    'by' => $leave['hr_name'] ?? 'Not Assigned',
                    'designation' => $leave['hr_designation'] ?? '',
                    'at' => $leave['hr_action_at'],
                    'reason' => $leave['hr_action_reason']
                ]
            ],
            'comments' => $leave['action_comments']
        ];
    }, $leaves);

    echo json_encode([
        'success' => true,
        'data' => $formattedLeaves
    ]);

} catch (Exception $e) {
    error_log("Error in get_leave_history.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Database error in get_leave_history.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred: ' . $e->getMessage()
    ]);
}

function getLeaveStatus($leave) {
    if (empty($leave['manager_approval']) || $leave['manager_approval'] === 'pending') {
        return 'Pending Manager Approval';
    }
    
    if ($leave['manager_approval'] === 'rejected') {
        return 'Rejected by Manager';
    }
    
    if ($leave['manager_approval'] === 'approved' && 
        (empty($leave['hr_approval']) || $leave['hr_approval'] === 'pending')) {
        return 'Pending HR Approval';
    }
    
    if ($leave['hr_approval'] === 'rejected') {
        return 'Rejected by HR';
    }
    
    if ($leave['manager_approval'] === 'approved' && $leave['hr_approval'] === 'approved') {
        return 'Approved';
    }
    
    return 'Pending';
} 