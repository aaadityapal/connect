<?php
// Enable error reporting for debugging
ini_set('display_errors', 0); // Don't display errors to the browser
ini_set('log_errors', 1);     // Log errors
error_log("Attendance approval API started");

// Ensure no output before JSON response
ob_start();

try {
    require_once __DIR__ . '/../../../config/db_connect.php';
    require_once __DIR__ . '/../../../includes/auth_check.php';
    require_once __DIR__ . '/../../../includes/role_check.php';
    
    // Initialize response array
    $response = [
        'success' => false,
        'message' => ''
    ];
    
    // Function to safely return JSON response
    function returnJsonResponse($response) {
        // Clear any previous output
        if (ob_get_length()) ob_clean();
        
        // Set headers
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Return JSON encoded response
        echo json_encode($response);
        exit;
    }
    
    // Check if user has required role
    checkUserRole(['admin', 'manager', 'senior manager (site)', 'senior manager (studio)', 'hr']);
    
    // Log request data for debugging
    error_log("POST data: " . print_r($_POST, true));
    
    // Check if required parameters are provided
    if (!isset($_POST['attendance_id']) || !isset($_POST['action'])) {
        $response['message'] = 'Missing required parameters';
        returnJsonResponse($response);
    }
    
    $attendance_id = intval($_POST['attendance_id']);
    $action = $_POST['action'];
    $comments = isset($_POST['comments']) ? $_POST['comments'] : '';
    $selected_points_raw = isset($_POST['selected_points']) ? trim($_POST['selected_points']) : '';
    $comment_word_count = count(preg_split('/\s+/', trim($comments), -1, PREG_SPLIT_NO_EMPTY));

    $actor_id = (int)$_SESSION['user_id'];
    $roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$roleStmt) {
        throw new Exception("Role check prepare failed: " . $conn->error);
    }
    $roleStmt->bind_param('i', $actor_id);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    if ($action === 'approve' || $action === 'reject') {
        $permTableCheck = $conn->query("SHOW TABLES LIKE 'attendance_action_permissions'");
        if (!$permTableCheck || $permTableCheck->num_rows === 0) {
            throw new Exception("attendance_action_permissions table not found. Please run 2026_04_07_create_attendance_action_permissions_table.sql first.");
        }

        $neededColumn = $action === 'approve' ? 'can_approve_attendance' : 'can_reject_attendance';
        $permSql = "SELECT {$neededColumn} AS allowed FROM attendance_action_permissions WHERE user_id = ? LIMIT 1";
        $permStmt = $conn->prepare($permSql);
        if (!$permStmt) {
            throw new Exception("Permission check prepare failed: " . $conn->error);
        }
        $permStmt->bind_param('i', $actor_id);
        $permStmt->execute();
        $permRow = $permStmt->get_result()->fetch_assoc();
        $isAllowed = $permRow && isset($permRow['allowed']) && (int)$permRow['allowed'] === 1;

        if (!$isAllowed) {
            throw new Exception($action === 'approve'
                ? 'You are not allowed to approve attendance geofence requests.'
                : 'You are not allowed to reject attendance geofence requests.');
        }
    }

    if ($action === 'reject' && $comment_word_count < 10) {
        throw new Exception('Rejection reason must contain at least 10 words.');
    }

    $selected_points = [];
    if ($selected_points_raw !== '') {
        foreach (explode(',', $selected_points_raw) as $pt) {
            $pt = trim($pt);
            if ($pt === 'punch_in' || $pt === 'punch_out') {
                $selected_points[] = $pt;
            }
        }
        $selected_points = array_values(array_unique($selected_points));
    }
    
    // Log parameters
    error_log("Processing attendance ID: $attendance_id, Action: $action");
    
    // Begin transaction
    $conn->begin_transaction();

    $manager_id = (int)$_SESSION['user_id'];
    $manager_name = 'Manager';
    $manager_stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    if ($manager_stmt) {
        $manager_stmt->bind_param('i', $manager_id);
        $manager_stmt->execute();
        $manager_res = $manager_stmt->get_result()->fetch_assoc();
        if (!empty($manager_res['username'])) {
            $manager_name = $manager_res['username'];
        }
    }

    $current_sql = "SELECT user_id, date, punch_in_outside_reason, punch_out_outside_reason FROM attendance WHERE id = ? LIMIT 1";
    $current_stmt = $conn->prepare($current_sql);
    if (!$current_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $current_stmt->bind_param('i', $attendance_id);
    $current_stmt->execute();
    $current_row = $current_stmt->get_result()->fetch_assoc();

    if (!$current_row) {
        throw new Exception('Attendance record not found');
    }

    $target_user_id = (int)$current_row['user_id'];
    $attendance_date = $current_row['date'];
    $target_user_name = 'Employee';
    $target_stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    if ($target_stmt) {
        $target_stmt->bind_param('i', $target_user_id);
        $target_stmt->execute();
        $target_res = $target_stmt->get_result()->fetch_assoc();
        if (!empty($target_res['username'])) {
            $target_user_name = $target_res['username'];
        }
    }

    $has_in_reason = !empty(trim((string)($current_row['punch_in_outside_reason'] ?? '')));
    $has_out_reason = !empty(trim((string)($current_row['punch_out_outside_reason'] ?? '')));

    // When selected points are sent from geofence modal, enforce at least one valid selected checkpoint
    if ($selected_points_raw !== '' && empty($selected_points)) {
        throw new Exception('Invalid checkpoint selection');
    }
    
    // Update attendance status based on action
    if ($action === 'approve') {
        // If checkpoint selection is provided, approve only selected outside-geofence checkpoint(s)
        if (!empty($selected_points)) {
            $approve_in = in_array('punch_in', $selected_points, true) && $has_in_reason;
            $approve_out = in_array('punch_out', $selected_points, true) && $has_out_reason;

            if (!$approve_in && !$approve_out) {
                throw new Exception('No selectable outside-geofence checkpoint found for approval');
            }

            $remaining_in_reason = $approve_in ? '' : (string)($current_row['punch_in_outside_reason'] ?? '');
            $remaining_out_reason = $approve_out ? '' : (string)($current_row['punch_out_outside_reason'] ?? '');
            $fully_resolved = (trim($remaining_in_reason) === '' && trim($remaining_out_reason) === '');
            $final_status = $fully_resolved ? 'approved' : 'pending';

            $sql = "UPDATE attendance SET 
                    approval_status = ?, 
                    manager_id = ?, 
                    approval_timestamp = NOW(), 
                    manager_comments = ?,
                    punch_in_outside_reason = CASE WHEN ? = 1 THEN NULL ELSE punch_in_outside_reason END,
                    punch_out_outside_reason = CASE WHEN ? = 1 THEN NULL ELSE punch_out_outside_reason END
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $approve_in_int = $approve_in ? 1 : 0;
            $approve_out_int = $approve_out ? 1 : 0;
            $stmt->bind_param('sisiii', $final_status, $manager_id, $comments, $approve_in_int, $approve_out_int, $attendance_id);

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $affected_rows = $stmt->affected_rows;
            error_log("Approve selective query affected rows: $affected_rows");
        } else {
            // Backward compatibility for existing approval flows
            $sql = "UPDATE attendance SET 
                    approval_status = 'approved', 
                    manager_id = ?, 
                    approval_timestamp = NOW(), 
                    manager_comments = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param('isi', $manager_id, $comments, $attendance_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $affected_rows = $stmt->affected_rows;
            error_log("Approve query affected rows: $affected_rows");
        }
    } elseif ($action === 'reject') {
        $sql = "UPDATE attendance SET 
                approval_status = 'rejected', 
                manager_id = ?, 
                approval_timestamp = NOW(), 
                manager_comments = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('isi', $manager_id, $comments, $attendance_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        error_log("Reject query affected rows: $affected_rows");
    } else {
        throw new Exception('Invalid action');
    }
    
    // Check if update was successful
    if ($affected_rows > 0) {
        $points_label = 'Punch In and Punch Out';
        if (!empty($selected_points)) {
            if (count($selected_points) === 1) {
                $points_label = $selected_points[0] === 'punch_in' ? 'Punch In' : 'Punch Out';
            }
        }

        $action_label = $action === 'approve' ? 'approved' : 'rejected';
        $base_description = "{$manager_name} has {$action_label} your attendance geofence request ({$points_label}) for " . date('d M Y', strtotime($attendance_date));
        $log_description = $base_description;
        $manager_description = "You have {$action_label} {$target_user_name}'s attendance geofence request ({$points_label}) for " . date('d M Y', strtotime($attendance_date));
        if (!empty($comments)) {
            $log_description .= ". Reason: {$comments}";
            $manager_description .= ". Reason: {$comments}";
        }

        $log_metadata = json_encode([
            'attendance_id' => $attendance_id,
            'attendance_date' => $attendance_date,
            'employee_user_id' => $target_user_id,
            'actor_user_id' => $manager_id,
            'actor_name' => $manager_name,
            'action' => $action,
            'selected_points' => !empty($selected_points) ? $selected_points : ['punch_in', 'punch_out'],
            'reason' => $comments
        ]);

        $log_action_type = $action === 'approve' ? 'attendance_geofence_approved' : 'attendance_geofence_rejected';
        $log_stmt = $conn->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed) VALUES (?, ?, 'attendance', ?, ?, ?, NOW(), 0, 0)");
        if (!$log_stmt) {
            throw new Exception("Global log prepare failed: " . $conn->error);
        }
        $log_stmt->bind_param('isiss', $target_user_id, $log_action_type, $attendance_id, $log_description, $log_metadata);
        if (!$log_stmt->execute()) {
            throw new Exception("Global log execute failed: " . $log_stmt->error);
        }

        // Also log for actor perspective so manager can see: "You have approved/rejected..."
        $actor_log_stmt = $conn->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed) VALUES (?, ?, 'attendance', ?, ?, ?, NOW(), 0, 0)");
        if ($actor_log_stmt) {
            $actor_log_stmt->bind_param('isiss', $manager_id, $log_action_type, $attendance_id, $manager_description, $log_metadata);
            $actor_log_stmt->execute();
        }

        // Commit transaction
        $conn->commit();
        error_log("Transaction committed");
        
        try {
            // Create notification for employee
            if ($target_user_id > 0) {
                $notification_title = "Attendance " . ucfirst($action === 'approve' ? 'approved' : 'rejected');
                $notification_content = $base_description;
                if (!empty($comments)) {
                    $notification_content .= ". Reason: " . $comments;
                }
                
                // Check if notifications table exists before trying to insert
                $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
                if ($check_table->num_rows > 0) {
                    $notify_sql = "INSERT INTO notifications (
                        user_id, title, content, link, type, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())";
                    
                    $notification_link = "view_attendance.php?id=" . $attendance_id;
                    $notification_type = "attendance_" . ($action === 'approve' ? 'approved' : 'rejected');
                    
                    $notify_stmt = $conn->prepare($notify_sql);
                    
                    if (!$notify_stmt) {
                        throw new Exception("Notification prepare failed: " . $conn->error);
                    }
                    
                    $notify_stmt->bind_param(
                        'issss',
                        $target_user_id,
                        $notification_title,
                        $notification_content,
                        $notification_link,
                        $notification_type
                    );
                    
                    $notify_stmt->execute();
                    error_log("Notification created");
                } else {
                    error_log("Notifications table doesn't exist, skipping notification creation");
                }
            }
        } catch (Exception $notifyError) {
            // Just log notification errors but don't fail the whole process
            error_log("Notification error: " . $notifyError->getMessage());
        }
        
        $response['success'] = true;
        $response['message'] = 'Attendance ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully';
    } else {
        // Rollback transaction
        $conn->rollback();
        error_log("Transaction rolled back - no rows affected");
        
        $response['message'] = 'No attendance record found with the provided ID or it has already been processed';
    }
    
    // Return JSON response
    returnJsonResponse($response);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in approve_attendance.php: " . $e->getMessage());
    
    // Rollback transaction if connection exists
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
            error_log("Transaction rolled back due to error");
        } catch (Exception $rollbackError) {
            error_log("Rollback failed: " . $rollbackError->getMessage());
        }
    }
    
    // Prepare error response
    $response = [
        'success' => false,
        'message' => 'Error processing request: ' . $e->getMessage()
    ];
    
    // Return error response
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
