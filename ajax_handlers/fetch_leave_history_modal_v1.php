<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Site Supervisor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/db_connect.php';

// Error logging in production should be minimal

$leave_type_id = $_GET['leave_type_id'] ?? null;
$user_id = $_SESSION['user_id'];
$current_year = date('Y');

if (!$leave_type_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Leave type ID required']);
    exit();
}

try {
    // Special case for 'all' leave types
    if ($leave_type_id === 'all') {
        try {
            // Get all leave requests for this user for the current year
            // Include leave type name from leave_types table
            $query = "
                SELECT 
                    lr.id,
                    lr.leave_type,
                    lt.name as leave_type_name,
                    lr.start_date,
                    lr.end_date,
                    lr.duration,
                    lr.reason,
                    lr.status,
                    lr.created_at as applied_date,
                    lr.action_at as approved_date,
                    lr.action_by as approved_by,
                    lr.action_comments as admin_comments,
                    u.username as approver_name
                FROM leave_request lr
                LEFT JOIN users u ON lr.action_by = u.id
                LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.user_id = ? 
                AND YEAR(lr.start_date) = ?
                ORDER BY lr.start_date DESC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id, $current_year]);
            $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summary totals across all leave types
            $total_approved = 0;
            $total_pending = 0;
            $total_rejected = 0;
            
            foreach ($leave_requests as $request) {
                $status = strtolower($request['status']);
                switch ($status) {
                    case 'approved':
                        $total_approved += $request['duration'];
                        break;
                    case 'pending':
                        $total_pending += $request['duration'];
                        break;
                    case 'rejected':
                        $total_rejected += $request['duration'];
                        break;
                }
            }
            
            $response = [
                'success' => true,
                'requests' => $leave_requests,
                'summary' => [
                    'total_approved' => $total_approved,
                    'total_pending' => $total_pending,
                    'total_rejected' => $total_rejected
                ],
                'year' => $current_year
            ];
            
            echo json_encode($response);
            exit();
        } catch (Exception $e) {
            error_log("Error fetching all leave history: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch leave history']);
            exit();
        }
    }
    
    // Regular case - specific leave type
    $stmt = $pdo->prepare("SELECT name, max_days FROM leave_types WHERE id = ? AND status = 'active'");
    $stmt->execute([$leave_type_id]);
    $leave_type = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leave_type) {
        throw new Exception('Leave type not found');
    }
    
    // Get all leave requests for this user and leave type for the current year
    // Use username for approver name
    $query = "
        SELECT 
            lr.id,
            lr.start_date,
            lr.end_date,
            lr.duration,
            lr.reason,
            lr.status,
            lr.created_at as applied_date,
            lr.action_at as approved_date,
            lr.action_by as approved_by,
            lr.action_comments as admin_comments,
            u.username as approver_name
        FROM leave_request lr
        LEFT JOIN users u ON lr.action_by = u.id
        WHERE lr.user_id = ? 
        AND lr.leave_type = ? 
        AND YEAR(lr.start_date) = ?
        ORDER BY lr.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $leave_type_id, $current_year]);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_approved = 0;
    $total_pending = 0;
    $total_rejected = 0;
    
    foreach ($leave_requests as $request) {
        // Convert status to lowercase for case-insensitive comparison
        $status = strtolower($request['status']);
        switch ($status) {
            case 'approved':
                $total_approved += $request['duration'];
                break;
            case 'pending':
                $total_pending += $request['duration'];
                break;
            case 'rejected':
                $total_rejected += $request['duration'];
                break;
        }
    }
    
    $total_used = $total_approved + $total_pending;

    // Special handling for Compensate Leave: compute earned dates on weekly off
    $isCompOff = stripos($leave_type['name'] ?? '', 'comp') !== false; // matches Compensate/Comp Off
    $earned_dates = [];
    $earned_count = null;
    $effective_max = (int)($leave_type['max_days'] ?? 0);
    if ($isCompOff) {
        $q = $pdo->prepare("\n            SELECT a.date\n            FROM attendance a\n            JOIN user_shifts us\n              ON us.user_id = a.user_id\n             AND a.date >= us.effective_from\n             AND (us.effective_to IS NULL OR a.date <= us.effective_to)\n            WHERE a.user_id = ?\n              AND YEAR(a.date) = ?\n              AND (a.is_weekly_off = 1 OR DAYNAME(a.date) = us.weekly_offs)\n              AND (a.punch_in IS NOT NULL OR a.punch_out IS NOT NULL)\n            ORDER BY a.date DESC\n        ");
        $q->execute([$user_id, $current_year]);
        $earned_dates = array_map(function($r){ return $r['date']; }, $q->fetchAll(PDO::FETCH_ASSOC));
        $earned_count = count($earned_dates);
        $effective_max = $earned_count; // override max with earned comp-off days
    }

    $remaining = ($effective_max > 0) ? max(0, $effective_max - $total_used) : ($isCompOff ? 0 : 'Unlimited');
    
    $response = [
        'success' => true,
        'leave_type' => $leave_type,
        'summary' => [
            'total_approved' => $total_approved,
            'total_pending' => $total_pending,
            'total_rejected' => $total_rejected,
            'total_used' => $total_used,
            'remaining' => $remaining,
            'max_days' => $effective_max,
            'is_unlimited' => (!$isCompOff && ($leave_type['max_days'] == 0))
        ],
        'requests' => $leave_requests,
        'year' => $current_year
    ];
    if ($isCompOff) {
        $response['comp_off_earned_dates'] = $earned_dates;
        $response['comp_off_earned_count'] = $earned_count;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching leave history: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch leave history']);
}
?>
