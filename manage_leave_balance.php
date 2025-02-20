<?php
require_once 'config/db_connect.php';

function initializeUserLeaveBalance($user_id, $year) {
    global $conn;
    
    // Get all active leave types
    $query = "SELECT id, max_days, carry_forward FROM leave_types WHERE status = 'active'";
    $result = $conn->query($query);
    $leave_types = $result->fetch_all(MYSQLI_ASSOC);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // For each leave type, create or update user balance
        foreach ($leave_types as $leave_type) {
            // Check if balance already exists
            $check_query = "SELECT id, carried_forward_days FROM user_leave_balance 
                          WHERE user_id = ? AND leave_type_id = ? AND year = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('iii', $user_id, $leave_type['id'], $year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Calculate carried forward days from previous year if applicable
                $carried_days = 0;
                if ($leave_type['carry_forward']) {
                    $prev_year = $year - 1;
                    $prev_balance_query = "SELECT (total_days - used_days) as remaining_days 
                                         FROM user_leave_balance 
                                         WHERE user_id = ? AND leave_type_id = ? AND year = ?";
                    $stmt = $conn->prepare($prev_balance_query);
                    $stmt->bind_param('iii', $user_id, $leave_type['id'], $prev_year);
                    $stmt->execute();
                    $prev_result = $stmt->get_result();
                    if ($prev_row = $prev_result->fetch_assoc()) {
                        $carried_days = max(0, $prev_row['remaining_days']);
                    }
                }
                
                // Insert new balance
                $insert_query = "INSERT INTO user_leave_balance 
                               (user_id, leave_type_id, year, total_days, carried_forward_days) 
                               VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $total_days = $leave_type['max_days'] + $carried_days;
                $stmt->bind_param('iiidi', $user_id, $leave_type['id'], $year, $total_days, $carried_days);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error initializing leave balance: " . $e->getMessage());
        return false;
    }
}

function updateLeaveBalance($user_id, $leave_type_id, $year, $days_used) {
    global $conn;
    
    $query = "UPDATE user_leave_balance 
              SET used_days = used_days + ? 
              WHERE user_id = ? AND leave_type_id = ? AND year = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('diii', $days_used, $user_id, $leave_type_id, $year);
    return $stmt->execute();
}

function getLeaveBalance($user_id, $year = null) {
    global $conn;
    
    if ($year === null) {
        $year = date('Y');
    }
    
    // Initialize balance for user if not exists
    initializeUserLeaveBalance($user_id, $year);
    
    $query = "SELECT 
        SUM(CASE 
            WHEN lt.name = 'Short Leave' THEN 1
            ELSE lr.duration 
        END) as used_days
    FROM leave_request lr
    JOIN leave_types lt ON lr.leave_type = lt.id
    WHERE lr.user_id = ? 
    AND lr.status = 'approved'
    AND lr.leave_type = ?
    AND YEAR(lr.start_date) = YEAR(CURRENT_DATE())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $user_id, $year);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?> 