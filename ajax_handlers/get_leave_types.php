<?php
session_start();
header('Content-Type: application/json');

// Allow all authenticated users regardless of role
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/db_connect.php';

try {
    // Get all active leave types with their restrictions
    $query = "SELECT id, name, max_days FROM leave_types WHERE status = 'active' ORDER BY name ASC";
    $stmt = $pdo->query($query);
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the current month's casual leave usage for this user
    $user_id = $_SESSION['user_id'];
    $current_month = date('m');
    $current_year = date('Y');
    
    $casual_leave_query = "
        SELECT COUNT(*) as count_used, SUM(duration) as total_used
        FROM leave_request lr
        JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = ?
        AND MONTH(lr.start_date) = ?
        AND YEAR(lr.start_date) = ?
        AND lt.name LIKE '%Casual%'
        AND (lr.status = 'approved' OR lr.status = 'pending')
    ";
    
    $stmt = $pdo->prepare($casual_leave_query);
    $stmt->execute([$user_id, $current_month, $current_year]);
    $casual_usage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get compensate leave balance
    $comp_leave_query = "
        SELECT lt.id, lt.name, lt.max_days
        FROM leave_types lt
        WHERE lt.name LIKE '%Compensate%' OR lt.name LIKE '%Comp%Off%'
        AND lt.status = 'active'
        LIMIT 1
    ";
    
    $comp_leave_type = $pdo->query($comp_leave_query)->fetch(PDO::FETCH_ASSOC);
    $comp_leave_balance = 0;
    
    if ($comp_leave_type) {
        // Get earned comp-off days (from working on weekly offs)
        $earned_query = "
            SELECT COUNT(*) as earned_days
            FROM attendance a
            JOIN user_shifts us ON us.user_id = a.user_id
                AND a.date >= us.effective_from
                AND (us.effective_to IS NULL OR a.date <= us.effective_to)
            WHERE a.user_id = ?
            AND YEAR(a.date) = ?
            AND (a.is_weekly_off = 1 OR DAYNAME(a.date) = us.weekly_offs)
            AND (a.punch_in IS NOT NULL OR a.punch_out IS NOT NULL)
        ";
        
        $stmt = $pdo->prepare($earned_query);
        $stmt->execute([$user_id, $current_year]);
        $earned_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $earned_days = (int)($earned_result['earned_days'] ?? 0);
        
        // Get used comp-off days
        $used_query = "
            SELECT SUM(duration) as used_days
            FROM leave_request lr
            WHERE lr.user_id = ?
            AND YEAR(lr.start_date) = ?
            AND lr.leave_type = ?
            AND (lr.status = 'approved' OR lr.status = 'pending')
        ";
        
        $stmt = $pdo->prepare($used_query);
        $stmt->execute([$user_id, $current_year, $comp_leave_type['id']]);
        $used_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $used_days = (float)($used_result['used_days'] ?? 0);
        
        $comp_leave_balance = max(0, $earned_days - $used_days);
    }
    
    // Add policy information to the response
    $policy = [
        'casual_leave_monthly_limit' => 2,
        'casual_leave_used_this_month' => (int)($casual_usage['count_used'] ?? 0),
        'casual_leave_days_used_this_month' => (float)($casual_usage['total_used'] ?? 0),
        'compensate_leave_balance' => $comp_leave_balance,
        'compensate_leave_type_id' => $comp_leave_type ? $comp_leave_type['id'] : null,
        'current_month' => $current_month,
        'current_year' => $current_year
    ];
    
    echo json_encode([
        'success' => true,
        'leave_types' => $leave_types,
        'policy' => $policy
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching leave types: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch leave types']);
}
?>
