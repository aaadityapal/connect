<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Define upload constants
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Debug function
function debug_log($message, $data = null) {
    error_log(print_r($message, true));
    if ($data !== null) {
        error_log(print_r($data, true));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, u.username as created_by_name
            FROM tasks t
            LEFT JOIN users u ON t.created_by = u.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch stages and substages for each task
        foreach ($tasks as &$task) {
            $stageStmt = $pdo->prepare("
                SELECT * FROM task_stages 
                WHERE task_id = :task_id 
                ORDER BY stage_number
            ");
            $stageStmt->execute([':task_id' => $task['id']]);
            $stages = $stageStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($stages as &$stage) {
                $substageStmt = $pdo->prepare("
                    SELECT * FROM task_substages 
                    WHERE stage_id = :stage_id
                ");
                $substageStmt->execute([':stage_id' => $stage['id']]);
                $stage['substages'] = $substageStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $task['stages'] = $stages;
        }

        echo json_encode([
            'success' => true,
            'tasks' => $tasks
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching tasks: ' . $e->getMessage()
        ]);
        exit;
    }
}

try {
    debug_log("Starting task creation process");
    debug_log("Received POST data:", $_POST);
    debug_log("Received FILES data:", $_FILES);

    // Handle form data
    $taskTitle = $_POST['taskTitle'];
    $taskDescription = $_POST['taskDescription'];
    $taskType = $_POST['projectType'];
    
    // Decode the JSON string into an array
    debug_log("Stages JSON received:", $_POST['stages']);
    $stages = json_decode($_POST['stages'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid stages data: ' . json_last_error_msg());
    }

    debug_log("Decoded stages data:", $stages);
    
    if (empty($taskTitle)) {
        throw new Exception('Task title is required');
    }

    if (empty($taskType)) {
        throw new Exception('Project type is required');
    }

    $pdo->beginTransaction();
    debug_log("Transaction started");

    // Insert task
    $stmt = $pdo->prepare("
        INSERT INTO tasks (title, description, created_by, category_id, task_type)
        VALUES (:title, :description, :created_by, :category_id, :task_type)
    ");

    $stmt->execute([
        ':title' => $taskTitle,
        ':description' => $taskDescription,
        ':created_by' => $_SESSION['user_id'],
        ':category_id' => 1,
        ':task_type' => $taskType
    ]);

    $taskId = $pdo->lastInsertId();
    debug_log("Created task with ID:", $taskId);

    // Process stages
    if (!empty($stages)) {
        debug_log("Number of stages to process:", count($stages));
        
        foreach ($stages as $stageNum => $stage) {
            debug_log("Processing stage:", $stage);
            
            try {
                // Insert stage
                $stageStmt = $pdo->prepare("
                    INSERT INTO task_stages (
                        task_id, stage_number, assigned_to, assignee_id,
                        start_date, due_date, priority, status
                    ) VALUES (
                        :task_id, :stage_number, :assigned_to, :assignee_id,
                        :start_date, :due_date, :priority, :status
                    )
                ");

                $stageStmt->execute([
                    ':task_id' => $taskId,
                    ':stage_number' => $stage['stage_number'],
                    ':assigned_to' => $stage['assigned_to'],
                    ':assignee_id' => $stage['assignee_id'],
                    ':start_date' => $stage['start_date'],
                    ':due_date' => $stage['due_date'],
                    ':priority' => $stage['priority'],
                    ':status' => 'not_started'
                ]);

                $stageId = $pdo->lastInsertId();
                debug_log("Created stage with ID:", $stageId);

                // Handle stage files
                if (isset($_FILES['stageFiles'])) {
                    debug_log("Stage files found:", $_FILES['stageFiles']);
                    
                    // Restructure files array for stages
                    foreach ($_FILES['stageFiles']['name'] as $stageIndex => $files) {
                        if (!is_array($files)) continue;
                        
                        // Only process files for the current stage
                        if ($stageIndex == $stageNum) {
                            // Create stage upload directory
                            $stageUploadDir = UPLOAD_DIR . "stage_{$stageId}/";
                            if (!file_exists($stageUploadDir)) {
                                mkdir($stageUploadDir, 0777, true);
                            }

                            // Process each file in this stage
                            foreach ($files as $fileIndex => $fileName) {
                                if ($_FILES['stageFiles']['error'][$stageIndex][$fileIndex] === UPLOAD_ERR_OK) {
                                    $tmpName = $_FILES['stageFiles']['tmp_name'][$stageIndex][$fileIndex];
                                    $fileType = $_FILES['stageFiles']['type'][$stageIndex][$fileIndex];
                                    $fileSize = $_FILES['stageFiles']['size'][$stageIndex][$fileIndex];
                                    
                                    $newFileName = uniqid() . '_' . basename($fileName);
                                    $filePath = $stageUploadDir . $newFileName;

                                    if (move_uploaded_file($tmpName, $filePath)) {
                                        $stmt = $pdo->prepare("
                                            INSERT INTO stage_files (
                                                stage_id, task_id, file_name, file_path, original_name, 
                                                file_type, file_size, uploaded_by, uploaded_at
                                            ) VALUES (
                                                :stage_id, :task_id, :file_name, :file_path, :original_name, 
                                                :file_type, :file_size, :uploaded_by, NOW()
                                            )
                                        ");

                                        debug_log("Inserting file for stage_id: " . $stageId . " and stage_number: " . ($stageNum + 1));

                                        $stmt->execute([
                                            ':stage_id' => $stageId,
                                            ':task_id' => $taskId,
                                            ':file_name' => $newFileName,
                                            ':file_path' => $filePath,
                                            ':original_name' => $fileName,
                                            ':file_type' => $fileType,
                                            ':file_size' => $fileSize,
                                            ':uploaded_by' => $_SESSION['user_id']
                                        ]);
                                        
                                        debug_log("Inserted stage file:", [
                                            'fileName' => $newFileName,
                                            'stageId' => $stageId,
                                            'stageNumber' => $stageNum + 1
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                // Process substages
                if (!empty($stage['substages'])) {
                    debug_log("Number of substages to process:", count($stage['substages']));
                    
                    foreach ($stage['substages'] as $substage) {
                        $substageStmt = $pdo->prepare("
                            INSERT INTO task_substages (
                                stage_id, description, status,
                                priority, start_date, end_date,
                                assignee_id
                            ) VALUES (
                                :stage_id, :description, :status,
                                :priority, :start_date, :end_date,
                                :assignee_id
                            )
                        ");

                        $substageStmt->execute([
                            ':stage_id' => $stageId,
                            ':description' => $substage['description'],
                            ':status' => 'not_started',
                            ':priority' => $substage['priority'],
                            ':start_date' => $substage['start_date'],
                            ':end_date' => $substage['end_date'],
                            ':assignee_id' => $substage['assignee_id']
                        ]);

                        debug_log("Created substage with ID:", $pdo->lastInsertId());
                    }
                }
            } catch (Exception $e) {
                debug_log("Error creating stage:", $e->getMessage());
                throw $e;
            }
        }
    } else {
        debug_log("No stages data found in the request");
    }

    $pdo->commit();
    debug_log("Transaction committed successfully");

    echo json_encode([
        'success' => true,
        'message' => 'Task created successfully',
        'taskId' => $taskId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        debug_log("Transaction rolled back");
    }
    debug_log("Error occurred:", $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating task: ' . $e->getMessage()
    ]);
}
?>

