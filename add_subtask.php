<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['category_id', 'title', 'priority', 'start_date', 'due_date', 'assigned_to'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Sanitize and prepare data
        $category_id = intval($_POST['category_id']);
        $title = trim($_POST['title']);
        $priority = trim($_POST['priority']);
        $start_date = trim($_POST['start_date']);
        $due_date = trim($_POST['due_date']);
        $due_time = !empty($_POST['due_time']) ? trim($_POST['due_time']) : null;
        $assigned_to = intval($_POST['assigned_to']);
        $pending_attendance = isset($_POST['pending_attendance']) ? 1 : 0;
        $repeat_task = isset($_POST['repeat_task']) ? trim($_POST['repeat_task']) : 'No Repeat';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
        $project_type = isset($_POST['project_type']) ? trim($_POST['project_type']) : '';
        $created_by = $_SESSION['user_id'];

        // Start transaction
        $conn->begin_transaction();

        // Insert task
        $task_query = "INSERT INTO tasks (
            category_id, title, priority, start_date, due_date, due_time,
            assigned_to, pending_attendance, repeat_task, remarks,
            created_by, status, created_at, project_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)";

        $stmt = $conn->prepare($task_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("issssssississ",
            $category_id,
            $title,
            $priority,
            $start_date,
            $due_date,
            $due_time,
            $assigned_to,
            $pending_attendance,
            $repeat_task,
            $remarks,
            $created_by,
            $project_type
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $task_id = $conn->insert_id;

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $task_id
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in add_subtask.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
