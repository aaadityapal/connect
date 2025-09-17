<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has HR role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Include database connection
require_once '../config/db_connect.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['filter_month']) || !isset($input['incremented_salary'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$user_id = $input['user_id'];
$filter_month = $input['filter_month'];
$incremented_salary = floatval($input['incremented_salary']);

// Validate inputs
if ($user_id <= 0 || $incremented_salary < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Validate filter_month format (YYYY-MM)
if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid month format']);
    exit;
}

try {
    // First, ensure the table exists
    $check_table = "SHOW TABLES LIKE 'incremented_salary_analytics'";
    $table_result = $pdo->query($check_table);
    
    if ($table_result->rowCount() == 0) {
        // Table doesn't exist, create it
        $create_sql = "CREATE TABLE incremented_salary_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            filter_month VARCHAR(7) NOT NULL,
            base_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            previous_incremented_salary DECIMAL(10,2) DEFAULT NULL,
            incremented_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            increment_amount DECIMAL(10,2) DEFAULT 0.00,
            actual_change_amount DECIMAL(10,2) DEFAULT 0.00,
            increment_percentage DECIMAL(5,2) DEFAULT 0.00,
            actual_change_percentage DECIMAL(5,2) DEFAULT 0.00,
            working_days INT DEFAULT 0,
            present_days INT DEFAULT 0,
            excess_days INT DEFAULT 0,
            late_punch_in_days INT DEFAULT 0,
            late_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
            leave_taken_days DECIMAL(4,1) DEFAULT 0.0,
            leave_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
            one_hour_late_days INT DEFAULT 0,
            one_hour_late_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
            fourth_saturday_penalty_amount DECIMAL(10,2) DEFAULT 0.00,
            total_deductions DECIMAL(10,2) DEFAULT 0.00,
            monthly_salary_after_deductions DECIMAL(10,2) DEFAULT 0.00,
            final_salary_percentage DECIMAL(5,2) DEFAULT 0.00,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes TEXT DEFAULT NULL,
            status ENUM('active', 'archived', 'cancelled') DEFAULT 'active',
            UNIQUE KEY unique_user_month (user_id, filter_month)
        )";
        $pdo->exec($create_sql);
    }
    
    // Check if user exists and is active
    $user_check_query = "SELECT id, username, base_salary FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL";
    $user_check_stmt = $pdo->prepare($user_check_query);
    $user_check_stmt->execute([$user_id]);
    $user = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found or inactive']);
        exit;
    }
    
    // Calculate effective date for this month (first day of the month)
    $effective_date = date('Y-m-01', strtotime($filter_month));
    
    // Check if there's already a salary increment for this user and month
    $existing_increment_query = "SELECT id, salary_after_increment FROM salary_increments 
                                WHERE user_id = ? 
                                AND DATE_FORMAT(effective_from, '%Y-%m') = ? 
                                AND status != 'cancelled'
                                ORDER BY effective_from DESC 
                                LIMIT 1";
    $existing_stmt = $pdo->prepare($existing_increment_query);
    $existing_stmt->execute([$user_id, $filter_month]);
    $existing_increment = $existing_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Store the previous incremented salary before updating
    $previous_incremented_salary = $existing_increment['salary_after_increment'] ?? null;
    
    if ($existing_increment) {
        // Update existing increment
        $update_query = "UPDATE salary_increments 
                        SET salary_after_increment = ? 
                        WHERE id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$incremented_salary, $existing_increment['id']]);
        
        $action = 'updated';
    } else {
        // Create new salary increment record - using only essential columns
        $insert_query = "INSERT INTO salary_increments 
                        (user_id, salary_after_increment, effective_from) 
                        VALUES (?, ?, ?)";
        $insert_stmt = $pdo->prepare($insert_query);
        $insert_stmt->execute([$user_id, $incremented_salary, $effective_date]);
        
        $action = 'created';
    }
    
    // Log the activity
    error_log("Salary increment {$action} for user {$user['username']} (ID: {$user_id}) - Month: {$filter_month}, New Salary: {$incremented_salary}");
    
    // Save comprehensive data to analytics table
    saveToAnalyticsTable($pdo, $user_id, $filter_month, $user['base_salary'], $incremented_salary, $previous_incremented_salary, $_SESSION['user_id'] ?? null);
    
    echo json_encode([
        'success' => true, 
        'message' => "Salary increment {$action} successfully",
        'data' => [
            'user_id' => $user_id,
            'filter_month' => $filter_month,
            'incremented_salary' => $incremented_salary,
            'action' => $action
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error saving incremented salary: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred',
        'debug_info' => [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'user_id' => $user_id ?? 'unknown',
            'filter_month' => $filter_month ?? 'unknown'
        ]
    ]);
}

// Function to save comprehensive analytics data
function saveToAnalyticsTable($pdo, $user_id, $filter_month, $base_salary, $incremented_salary, $previous_incremented_salary, $created_by) {
    try {
        // Get comprehensive salary calculation data
        $analytics_data = getComprehensiveSalaryData($pdo, $user_id, $filter_month, $incremented_salary);
        
        // Check if record exists
        $check_query = "SELECT id FROM incremented_salary_analytics WHERE user_id = ? AND filter_month = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$user_id, $filter_month]);
        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Update existing record
            $update_query = "UPDATE incremented_salary_analytics SET 
                            base_salary = ?, 
                            previous_incremented_salary = ?, 
                            incremented_salary = ?, 
                            working_days = ?, 
                            present_days = ?, 
                            excess_days = ?, 
                            late_punch_in_days = ?, 
                            late_deduction_amount = ?, 
                            leave_taken_days = ?, 
                            leave_deduction_amount = ?, 
                            one_hour_late_days = ?, 
                            one_hour_late_deduction_amount = ?, 
                            fourth_saturday_penalty_amount = ?, 
                            total_deductions = ?, 
                            monthly_salary_after_deductions = ?, 
                            created_by = ?, 
                            updated_at = NOW(),
                            notes = CONCAT(COALESCE(notes, ''), '\n[', NOW(), '] Updated via Analytics Dashboard')
                            WHERE user_id = ? AND filter_month = ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_params = [
                $base_salary, $previous_incremented_salary, $incremented_salary, 
                $analytics_data['working_days'], $analytics_data['present_days'], $analytics_data['excess_days'],
                $analytics_data['late_punch_in_days'], $analytics_data['late_deduction_amount'],
                $analytics_data['leave_taken_days'], $analytics_data['leave_deduction_amount'],
                $analytics_data['one_hour_late_days'], $analytics_data['one_hour_late_deduction_amount'],
                $analytics_data['fourth_saturday_penalty_amount'], $analytics_data['total_deductions'],
                $analytics_data['monthly_salary_after_deductions'], $created_by,
                $user_id, $filter_month
            ];
            $update_stmt->execute($update_params);
        } else {
            // Insert new record
            $insert_query = "INSERT INTO incremented_salary_analytics 
                            (user_id, filter_month, base_salary, previous_incremented_salary, incremented_salary, working_days, present_days, excess_days,
                             late_punch_in_days, late_deduction_amount, leave_taken_days, leave_deduction_amount,
                             one_hour_late_days, one_hour_late_deduction_amount, fourth_saturday_penalty_amount,
                             total_deductions, monthly_salary_after_deductions, created_by, notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $pdo->prepare($insert_query);
            $insert_params = [
                $user_id, $filter_month, $base_salary, $previous_incremented_salary, $incremented_salary,
                $analytics_data['working_days'], $analytics_data['present_days'], $analytics_data['excess_days'],
                $analytics_data['late_punch_in_days'], $analytics_data['late_deduction_amount'],
                $analytics_data['leave_taken_days'], $analytics_data['leave_deduction_amount'],
                $analytics_data['one_hour_late_days'], $analytics_data['one_hour_late_deduction_amount'],
                $analytics_data['fourth_saturday_penalty_amount'], $analytics_data['total_deductions'],
                $analytics_data['monthly_salary_after_deductions'], $created_by,
                '[' . date('Y-m-d H:i:s') . '] Created via Analytics Dashboard'
            ];
            $insert_stmt->execute($insert_params);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error saving to analytics table: " . $e->getMessage());
        return false;
    }
}

// Function to get comprehensive salary calculation data
function getComprehensiveSalaryData($pdo, $user_id, $filter_month, $incremented_salary) {
    $month_start = date('Y-m-01', strtotime($filter_month));
    $month_end = date('Y-m-t', strtotime($filter_month));
    
    // Get user's working days and attendance data
    $user_query = "SELECT u.*, us.weekly_offs, 
                   COALESCE(att.present_days, 0) as present_days,
                   COALESCE(att.late_days, 0) as late_days
                   FROM users u 
                   LEFT JOIN user_shifts us ON u.id = us.user_id AND 
                       (us.effective_to IS NULL OR us.effective_to >= ?)
                   LEFT JOIN (
                       SELECT user_id,
                              COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                              COUNT(CASE WHEN status = 'present' AND TIME(punch_in) >= TIME(DATE_ADD(TIME('09:00:00'), INTERVAL 15 MINUTE)) THEN 1 END) as late_days
                       FROM attendance 
                       WHERE DATE_FORMAT(date, '%Y-%m') = ?
                       GROUP BY user_id
                   ) att ON u.id = att.user_id
                   WHERE u.id = ?";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$month_end, $filter_month, $user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        return [
            'working_days' => 0, 'present_days' => 0, 'excess_days' => 0,
            'late_punch_in_days' => 0, 'late_deduction_amount' => 0,
            'leave_taken_days' => 0, 'leave_deduction_amount' => 0,
            'one_hour_late_days' => 0, 'one_hour_late_deduction_amount' => 0,
            'fourth_saturday_penalty_amount' => 0, 'total_deductions' => 0,
            'monthly_salary_after_deductions' => 0
        ];
    }
    
    // Calculate working days
    $working_days = 0;
    $weekly_offs = !empty($user_data['weekly_offs']) ? explode(',', $user_data['weekly_offs']) : [];
    
    $current_date = new DateTime($month_start);
    $end_date = new DateTime($month_end);
    
    while ($current_date <= $end_date) {
        $day_of_week = $current_date->format('l');
        if (!in_array($day_of_week, $weekly_offs)) {
            $working_days++;
        }
        $current_date->modify('+1 day');
    }
    
    $present_days = $user_data['present_days'];
    $excess_days = max(0, $present_days - $working_days);
    $daily_salary = $working_days > 0 ? ($incremented_salary / $working_days) : 0;
    
    // Calculate various deductions (simplified version)
    $late_deduction_amount = floor($user_data['late_days'] / 3) * 0.5 * $daily_salary;
    
    // Get leave data
    $leave_query = "SELECT SUM(CASE 
                           WHEN lr.duration_type = 'half_day' THEN 0.5 
                           ELSE 1 END) as leave_days
                   FROM leave_request lr
                   WHERE lr.user_id = ? AND lr.status = 'approved'
                   AND DATE_FORMAT(lr.start_date, '%Y-%m') = ?";
    $leave_stmt = $pdo->prepare($leave_query);
    $leave_stmt->execute([$user_id, $filter_month]);
    $leave_result = $leave_stmt->fetch(PDO::FETCH_ASSOC);
    $leave_taken_days = $leave_result['leave_days'] ?? 0;
    $leave_deduction_amount = max(0, ($leave_taken_days - 2)) * $daily_salary; // Simplified
    
    // 1-hour late calculation (simplified)
    $one_hour_late_query = "SELECT COUNT(*) as one_hour_late
                           FROM attendance 
                           WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
                           AND TIME(punch_in) > TIME(DATE_ADD(TIME('09:00:00'), INTERVAL 1 HOUR))";
    $one_hour_stmt = $pdo->prepare($one_hour_late_query);
    $one_hour_stmt->execute([$user_id, $filter_month]);
    $one_hour_result = $one_hour_stmt->fetch(PDO::FETCH_ASSOC);
    $one_hour_late_days = $one_hour_result['one_hour_late'] ?? 0;
    $one_hour_late_deduction_amount = $one_hour_late_days * 0.5 * $daily_salary;
    
    // 4th Saturday penalty (simplified)
    $fourth_saturday_penalty_amount = 0; // Would need complex logic to calculate
    
    $total_deductions = $late_deduction_amount + $leave_deduction_amount + 
                       $one_hour_late_deduction_amount + $fourth_saturday_penalty_amount;
    $monthly_salary_after_deductions = max(0, $incremented_salary - $total_deductions);
    
    return [
        'working_days' => $working_days,
        'present_days' => $present_days,
        'excess_days' => $excess_days,
        'late_punch_in_days' => $user_data['late_days'],
        'late_deduction_amount' => $late_deduction_amount,
        'leave_taken_days' => $leave_taken_days,
        'leave_deduction_amount' => $leave_deduction_amount,
        'one_hour_late_days' => $one_hour_late_days,
        'one_hour_late_deduction_amount' => $one_hour_late_deduction_amount,
        'fourth_saturday_penalty_amount' => $fourth_saturday_penalty_amount,
        'total_deductions' => $total_deductions,
        'monthly_salary_after_deductions' => $monthly_salary_after_deductions
    ];
}
?>