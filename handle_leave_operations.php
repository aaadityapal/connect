<?php
session_start();
require_once 'config/db_connect.php';
require_once 'manage_leave_balance.php';

// Add this at the top for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the POST/GET data
error_log("Leave Operations data: " . print_r($_REQUEST, true));

try {
    // Check if it's a DELETE operation
    if (isset($_GET['action']) && $_GET['action'] === 'delete') {
        if (!isset($_GET['id'])) {
            throw new Exception('Leave ID is required for deletion');
        }

        $stmt = $conn->prepare("DELETE FROM leave_request WHERE id = ?");
        $stmt->bind_param('i', $_GET['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Leave record successfully deleted";
        } else {
            throw new Exception('Failed to delete leave record');
        }

        header('Location: edit_leave.php?month=' . date('Y-m'));
        exit();
    }

    // Handle POST requests (Add/Update operations)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if it's an update operation
        if (isset($_POST['action']) && $_POST['action'] === 'update') {
            $leave_id = $_POST['leave_id'];
            $user_id = $_POST['user_id'];
            $leave_type = $_POST['leave_type'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $duration = $_POST['duration'];
            $reason = $_POST['reason'];
            
            // Get the leave type name to check if it's a short leave
            $leave_type_query = "SELECT name FROM leave_types WHERE id = ?";
            $stmt = $conn->prepare($leave_type_query);
            $stmt->bind_param('i', $leave_type);
            $stmt->execute();
            $leave_type_result = $stmt->get_result();
            $leave_type_data = $leave_type_result->fetch_assoc();
            
            // Check if it's a short leave
            $is_short_leave = stripos($leave_type_data['name'], 'short') !== false;
            
            // Prepare time values
            $time_from = $is_short_leave ? $_POST['time_from'] : null;
            $time_to = $is_short_leave ? $_POST['time_to'] : null;
            
            // Update query
            $query = "UPDATE leave_request SET 
                        leave_type = ?,
                        start_date = ?,
                        end_date = ?,
                        duration = ?,
                        time_from = ?,
                        time_to = ?,
                        reason = ?,
                        status = 'pending',
                        manager_approval = 'pending',
                        hr_approval = 'pending'
                     WHERE id = ? AND user_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                'issdsssis',
                $leave_type,
                $start_date,
                $end_date,
                $duration,
                $time_from,
                $time_to,
                $reason,
                $leave_id,
                $user_id
            );
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Leave request updated successfully";
            } else {
                $_SESSION['error'] = "Error updating leave request: " . $conn->error;
            }
            
            header('Location: edit_leave.php');
            exit();
        }

        // Handle manager approval/rejection
        if (isset($_POST['action']) && $_POST['action'] === 'manager_action') {
            if (!isset($_POST['leave_id']) || !isset($_POST['manager_decision']) || !isset($_POST['manager_action_reason'])) {
                throw new Exception('Missing required fields for manager action');
            }

            $leave_id = $_POST['leave_id'];
            $decision = $_POST['manager_decision'];
            $reason = $_POST['manager_action_reason'];
            $manager_id = $_SESSION['user_id']; // Assuming you store user_id in session
            
            $query = "UPDATE leave_request SET 
                        manager_approval = ?,
                        manager_action_reason = ?,
                        manager_action_by = ?,
                        manager_action_at = NOW(),
                        status = CASE 
                            WHEN ? = 'rejected' THEN 'rejected'
                            ELSE status 
                        END
                     WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssisi', $decision, $reason, $manager_id, $decision, $leave_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Manager decision recorded successfully";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception('Failed to record manager decision');
            }
            
            header('Location: edit_leave_detail.php?id=' . $leave_id);
            exit();
        }

        // Handle HR approval/rejection
        if (isset($_POST['action']) && $_POST['action'] === 'hr_action') {
            if (!isset($_POST['leave_id']) || !isset($_POST['hr_decision']) || !isset($_POST['hr_action_reason'])) {
                throw new Exception('Missing required fields for HR action');
            }

            $leave_id = $_POST['leave_id'];
            $decision = $_POST['hr_decision'];
            $reason = $_POST['hr_action_reason'];
            $hr_id = $_SESSION['user_id']; // Assuming you store user_id in session
            
            $query = "UPDATE leave_request SET 
                        hr_approval = ?,
                        hr_action_reason = ?,
                        hr_action_by = ?,
                        hr_action_at = NOW(),
                        status = CASE 
                            WHEN ? = 'approved' THEN 'approved'
                            WHEN ? = 'rejected' THEN 'rejected'
                            ELSE status 
                        END
                     WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssissi', $decision, $reason, $hr_id, $decision, $decision, $leave_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "HR decision recorded successfully";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception('Failed to record HR decision');
            }
            
            header('Location: edit_leave_detail.php?id=' . $leave_id);
            exit();
        }

        // Existing code for insert operation
        $user_id = $_POST['employee'];
        $leave_type = $_POST['leave_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $duration = $_POST['duration'];
        $reason = $_POST['reason'];
        
        // Get the leave type name to check if it's a short leave
        $leave_type_query = "SELECT name FROM leave_types WHERE id = ?";
        $stmt = $conn->prepare($leave_type_query);
        $stmt->bind_param('i', $leave_type);
        $stmt->execute();
        $leave_type_result = $stmt->get_result();
        $leave_type_data = $leave_type_result->fetch_assoc();
        
        // Check if it's a short leave
        $is_short_leave = stripos($leave_type_data['name'], 'short') !== false;
        
        // Prepare time values
        $time_from = $is_short_leave ? $_POST['time_from'] : null;
        $time_to = $is_short_leave ? $_POST['time_to'] : null;
        
        // Insert query with time fields
        $query = "INSERT INTO leave_request (
            user_id,
            leave_type,
            start_date,
            end_date,
            duration,
            time_from,
            time_to,
            reason,
            status,
            manager_approval,
            hr_approval,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'pending', NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            'iissdsss',
            $user_id,
            $leave_type,
            $start_date,
            $end_date,
            $duration,
            $time_from,
            $time_to,
            $reason
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Leave request submitted successfully";
        } else {
            $_SESSION['error'] = "Error submitting leave request: " . $conn->error;
        }
        
        header('Location: edit_leave.php');
        exit();
    }

    // When approving a leave request:
    if (isset($_POST['action']) && $_POST['action'] === 'approve') {
        $leave_id = $_POST['leave_id'];
        
        // Get leave request details
        $query = "SELECT user_id, leave_type, duration, start_date FROM leave_request WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $leave_id);
        $stmt->execute();
        $leave_request = $stmt->get_result()->fetch_assoc();
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update leave status
            $update_query = "UPDATE leave_request SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('i', $leave_id);
            $stmt->execute();
            
            // Update leave balance
            $year = date('Y', strtotime($leave_request['start_date']));
            updateLeaveBalance(
                $leave_request['user_id'],
                $leave_request['leave_type'],
                $year,
                $leave_request['duration']
            );
            
            $conn->commit();
            $_SESSION['success'] = "Leave request approved successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error approving leave request";
        }
    }

    error_log("Form submitted with data: " . print_r($_POST, true));

} catch (Exception $e) {
    error_log("Leave Operations Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    
    // Redirect back with error
    header('Location: edit_leave.php?month=' . (isset($_POST['start_date']) ? date('Y-m', strtotime($_POST['start_date'])) : date('Y-m')));
    exit();
}

// If we get here, something went wrong
$_SESSION['error'] = "Invalid request";
header('Location: edit_leave.php');
exit(); 