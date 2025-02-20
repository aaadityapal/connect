<?php
session_start();
require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $conn->begin_transaction();

        // Insert task - Updated table name to tasks
        $query = "INSERT INTO tasks (
            category_id, title, priority, start_date, due_date, 
            due_time, assigned_to, pending_attendance, repeat_task, 
            remarks, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "isssssiiisi",
            $_POST['category_id'],
            $_POST['title'],
            $_POST['priority'],
            $_POST['start_date'],
            $_POST['due_date'],
            $_POST['due_time'],
            $_POST['assigned_to'],
            isset($_POST['pending_attendance']) ? 1 : 0,
            isset($_POST['repeat_task']) ? 1 : 0,
            $_POST['remarks'],
            $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            $task_id = $stmt->insert_id;
            
            // Handle file uploads
            if (!empty($_FILES['files'])) {
                $upload_dir = 'uploads/tasks/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                    $file_name = $_FILES['files']['name'][$key];
                    $file_type = $_FILES['files']['type'][$key];
                    $file_size = $_FILES['files']['size'][$key];
                    $file_path = $upload_dir . uniqid() . '_' . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        // Updated table name to task_attachments
                        $file_query = "INSERT INTO task_attachments (
                            task_id, file_name, file_path, file_type, 
                            file_size, uploaded_by
                        ) VALUES (?, ?, ?, ?, ?, ?)";
                        $file_stmt = $conn->prepare($file_query);
                        $file_stmt->bind_param(
                            "issiii", 
                            $task_id, 
                            $file_name, 
                            $file_path, 
                            $file_type, 
                            $file_size, 
                            $_SESSION['user_id']
                        );
                        $file_stmt->execute();
                    }
                }
            }

            // Add initial history record - Updated table name to task_history
            $history_query = "INSERT INTO task_history (
                task_id, changed_by, previous_status, new_status, remarks
            ) VALUES (?, ?, NULL, 'Pending', 'Task created')";
            $history_stmt = $conn->prepare($history_query);
            $history_stmt->bind_param("ii", $task_id, $_SESSION['user_id']);
            $history_stmt->execute();
            
            $conn->commit();
            $response = ['success' => true, 'message' => 'Task added successfully'];
        }
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Get task counts
$user_id = $_SESSION['user_id'];

// Get total tasks
$total_query = "SELECT COUNT(*) as count FROM tasks WHERE created_by = ?";
$stmt = $conn->prepare($total_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_tasks = $stmt->get_result()->fetch_assoc()['count'];

// Get completed tasks
$completed_query = "SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = 'Completed'";
$stmt = $conn->prepare($completed_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'];

// Get pending tasks
$pending_query = "SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = 'Pending'";
$stmt = $conn->prepare($pending_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['count'];

// Get in progress tasks
$in_progress_query = "SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = 'In Progress'";
$stmt = $conn->prepare($in_progress_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$in_progress_count = $stmt->get_result()->fetch_assoc()['count'];

// Get on hold tasks
$on_hold_query = "SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = 'On Hold'";
$stmt = $conn->prepare($on_hold_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$on_hold_count = $stmt->get_result()->fetch_assoc()['count'];

// Get N/A tasks
$na_query = "SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = 'Not Applicable'";
$stmt = $conn->prepare($na_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$na_count = $stmt->get_result()->fetch_assoc()['count'];

// Get categories
$categories_query = "SELECT * FROM task_categories WHERE created_by = ?";
$stmt = $conn->prepare($categories_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
