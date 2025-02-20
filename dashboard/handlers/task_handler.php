<?php
session_start();

// Fix the path to config file - use absolute path
require_once __DIR__ . '/../../config/db_connect.php';

// Prevent any unwanted output
ob_start();

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);
error_log("Task handler called with action: " . ($_POST['action'] ?? 'none'));

error_log("Current directory: " . __DIR__);
error_log("Looking for config at: " . __DIR__ . '/../../config/db_connect.php');

if (!file_exists(__DIR__ . '/../../config/db_connect.php')) {
    die(json_encode(['status' => 'error', 'message' => 'Config file not found']));
}

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';

    // Debug log
    error_log("Action received: " . $action);
    error_log("POST data: " . print_r($_POST, true));

    switch ($action) {
        case 'create':
            ob_clean();
            
            $data = json_decode($_POST['data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON data: " . json_last_error_msg());
            }
            
            // Begin transaction
            $conn->begin_transaction();

            // Insert main task
            $taskQuery = "INSERT INTO tasks (
                title, 
                description,
                due_date,
                priority,
                status,
                created_by,
                category_id
            ) VALUES (?, ?, ?, ?, 'pending', ?, ?)";
            
            $stmt = $conn->prepare($taskQuery);
            $stmt->bind_param("ssssii", 
                $data['title'],
                $data['description'],
                $data['due_date'],
                $data['priority'],
                $_SESSION['user_id'],
                $data['category_id']
            );
            $stmt->execute();
            $taskId = $conn->insert_id;

            // Insert stages
            if (isset($data['stages']) && is_array($data['stages'])) {
                foreach ($data['stages'] as $index => $stage) {
                    $stageQuery = "INSERT INTO task_stages (
                        task_id,
                        stage_number,
                        assigned_to,
                        due_date,
                        status,
                        priority,
                        start_date,
                        assignee_id
                    ) VALUES (?, ?, ?, ?, 'not_started', ?, ?, ?)";
                    
                    try {
                        $stmt = $conn->prepare($stageQuery);
                        if (!$stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        
                        $stageNumber = $index + 1;
                        
                        $stmt->bind_param("iiisssi",
                            $taskId,
                            $stageNumber,
                            $stage['assigned_to'],
                            $stage['due_date'],
                            $stage['priority'],
                            $stage['start_date'],
                            $stage['assigned_to']  // Using assigned_to as assignee_id
                        );
                        
                        $stmt->execute();
                        if ($stmt->error) {
                            throw new Exception("Execute failed: " . $stmt->error);
                        }
                        
                        $stageId = $conn->insert_id;
                        error_log("Successfully inserted stage with ID: " . $stageId);
                    } catch (Exception $e) {
                        error_log("Error inserting stage: " . $e->getMessage());
                        throw $e;
                    }
                }
            }

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Task created successfully', 'task_id' => $taskId]);
            break;

        case 'fetch':
            ob_clean();
            $query = "SELECT t.*, 
                            u.username as created_by_name,
                            c.name as category_name
                     FROM tasks t
                     LEFT JOIN users u ON t.created_by = u.id
                     LEFT JOIN categories c ON t.category_id = c.id
                     ORDER BY t.created_at DESC";
            
            $result = $conn->query($query);
            $tasks = [];
            
            while ($row = $result->fetch_assoc()) {
                // Fetch stages for each task
                $stagesQuery = "SELECT * FROM task_stages WHERE task_id = ?";
                $stageStmt = $conn->prepare($stagesQuery);
                $stageStmt->bind_param("i", $row['id']);
                $stageStmt->execute();
                $stagesResult = $stageStmt->get_result();
                $stages = [];
                
                while ($stage = $stagesResult->fetch_assoc()) {
                    // Fetch substages for each stage
                    $substagesQuery = "SELECT * FROM task_substages WHERE task_id = ?";
                    $substageStmt = $conn->prepare($substagesQuery);
                    $substageStmt->bind_param("i", $stage['id']);
                    $substageStmt->execute();
                    $substagesResult = $substageStmt->get_result();
                    $stage['substages'] = $substagesResult->fetch_all(MYSQLI_ASSOC);
                    
                    $stages[] = $stage;
                }
                
                $row['stages'] = $stages;
                $tasks[] = $row;
            }
            
            echo json_encode(['status' => 'success', 'tasks' => $tasks]);
            break;

        case 'update_status':
            $id = $_POST['id'] ?? null;
            $type = $_POST['type'] ?? null; // 'task', 'stage', or 'substage'
            $status = $_POST['status'] ?? null;
            
            if (!$id || !$type || !$status) {
                throw new Exception('Missing required parameters');
            }
            
            $table = $type === 'task' ? 'tasks' : ($type === 'stage' ? 'task_stages' : 'task_substages');
            $query = "UPDATE $table SET status = ? WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();
            
            echo json_encode(['status' => 'success', 'message' => 'Status updated successfully']);
            break;

        default:
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// End output buffering and send response
ob_end_flush(); 