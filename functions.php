<?php
function getRoleSpecificTasks($pdo, $role) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tasks 
                              WHERE assigned_role = ? 
                              AND status != 'completed' 
                              ORDER BY due_date ASC 
                              LIMIT 5");
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getRoleSpecificTasks: " . $e->getMessage());
        return [];
    }
}

function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'pending':
            return 'warning';
        case 'in_progress':
            return 'info';
        case 'completed':
            return 'success';
        default:
            return 'secondary';
    }
}

function getDashboardByRole($role) {
    switch($role) {
        case 'admin':
            return 'admin_dashboard.php';
        case 'HR':
            return 'hr_dashboard.php';
        case 'Senior Manager (Studio)':
            return 'studio_manager_dashboard.php';
        // Add other role-specific dashboards here
        default:
            return 'employee_dashboard.php';
    }
}

function getValidProfileImagePath($profileImage) {
    $defaultImage = 'assets/default-profile.png';
    
    if (empty($profileImage)) {
        return $defaultImage;
    }

    $imagePath = 'uploads/profile_images/' . $profileImage;
    return file_exists($imagePath) ? $imagePath : $defaultImage;
}

function sendLeaveNotification($managerId, $userId, $leaveType, $startDate, $endDate, $reason) {
    global $pdo;
    
    // Get manager's email
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$managerId]);
    $managerEmail = $stmt->fetchColumn();
    
    // Get HR email from configuration
    $hrEmail = HR_EMAIL; // Define this in your config.php
    
    // Get employee details
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $employee = $stmt->fetch();
    
    $subject = "Leave Application - {$employee['name']}";
    $message = "A new leave application has been submitted:\n\n";
    $message .= "Employee: {$employee['name']}\n";
    $message .= "Leave Type: $leaveType\n";
    $message .= "Duration: $startDate to $endDate\n";
    $message .= "Reason: $reason\n\n";
    $message .= "Please login to the system to approve or reject this request.";
    
    // Send to manager
    mail($managerEmail, $subject, $message);
    
    // Send to HR
    mail($hrEmail, $subject, $message);
}

function updateLeaveBalance($pdo, $leave_id, $new_status) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get leave details
        $stmt = $pdo->prepare("
            SELECT user_id, leave_type, start_date, days_count 
            FROM leaves 
            WHERE id = ?
        ");
        $stmt->execute([$leave_id]);
        $leave = $stmt->fetch();
        
        // Update leave status
        $stmt = $pdo->prepare("UPDATE leaves SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $leave_id]);
        
        if ($new_status === 'Approved') {
            // Get or create leave balance record
            $month = date('n', strtotime($leave['start_date']));
            $year = date('Y', strtotime($leave['start_date']));
            
            ensureLeaveBalance($pdo, $leave['user_id'], $month, $year);
            
            // Update balance based on leave type
            $days = $leave['days_count'] > 0 ? $leave['days_count'] : 1;
            
            switch($leave['leave_type']) {
                case 'Casual':
                    $stmt = $pdo->prepare("
                        UPDATE leave_balances 
                        SET casual_leaves = casual_leaves - ?
                        WHERE user_id = ? AND month = ? AND year = ?
                    ");
                    break;
                    
                case 'Medical':
                    $stmt = $pdo->prepare("
                        UPDATE leave_balances 
                        SET medical_leaves = medical_leaves - ?
                        WHERE user_id = ? AND month = ? AND year = ?
                    ");
                    break;
                    
                case 'Short':
                    // Short leaves are counted as instances, not days
                    $stmt = $pdo->prepare("
                        UPDATE leave_balances 
                        SET short_leaves = short_leaves - 1
                        WHERE user_id = ? AND month = ? AND year = ?
                    ");
                    $days = 1; // Override days for short leaves
                    break;
            }
            
            if (isset($stmt)) {
                $stmt->execute([$days, $leave['user_id'], $month, $year]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Leave balance update error: " . $e->getMessage());
        return false;
    }
}

function getPendingLeaveDetails($pdo) {
    $query = "SELECT 
        lr.*,
        u.username,
        u.employee_id,
        DATEDIFF(lr.end_date, lr.start_date) + 1 as leave_duration
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    WHERE lr.status = 'Pending'
    ORDER BY lr.created_at DESC";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getPendingLeaveDetails: " . $e->getMessage());
        return array();
    }
}

function getCurrentLeaveDetails($pdo) {
    $today = date('Y-m-d');
    $query = "
        SELECT 
            l.start_date,
            l.end_date,
            l.leave_type,
            l.reason,
            u.username,
            u.employee_id
        FROM leaves l
        JOIN users u ON l.user_id = u.id
        WHERE l.status = 'approved'
        AND ? BETWEEN l.start_date AND l.end_date
        ORDER BY l.start_date ASC
    ";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$today]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching current leave details: " . $e->getMessage());
        return [];
    }
}
?>
