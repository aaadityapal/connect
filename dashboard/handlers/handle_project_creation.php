<?php
session_start();
require_once 'config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has appropriate permissions
if (!isLoggedIn() || !hasPermission('create_project')) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get POST data
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate main project data
        if (empty($data['projectName']) || empty($data['projectType'])) {
            throw new Exception('Project name and type are required');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert main task/project
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                title,
                description,
                created_by,
                status_id,
                due_date,
                task_type,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $data['projectName'],
            $data['projectDescription'] ?? null,
            $_SESSION['user_id'],
            1, // Default status ID for new tasks
            $data['dueDate'],
            $data['projectType'] // architecture/interior/construction
        ]);

        $taskId = $pdo->lastInsertId();

        // Handle stages
        if (!empty($data['stages'])) {
            foreach ($data['stages'] as $stageIndex => $stage) {
                // Insert stage
                $stageStmt = $pdo->prepare("
                    INSERT INTO task_stages (
                        task_id,
                        stage_number,
                        assignee_id,
                        due_date,
                        status,
                        created_at,
                        updated_at,
                        start_date
                    ) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
                ");

                $stageStmt->execute([
                    $taskId,
                    $stageIndex + 1,
                    $stage['assignedTo'],
                    $stage['dueDate'],
                    'pending', // Default status
                    $stage['startDate']
                ]);

                $stageId = $pdo->lastInsertId();

                // Handle stage attachment if exists
                if (!empty($_FILES["stageFile{$stageIndex}"])) {
                    $file = $_FILES["stageFile{$stageIndex}"];
                    $fileInfo = handleFileUpload($file, 'stages');
                    
                    if ($fileInfo['status'] === 'success') {
                        $fileStmt = $pdo->prepare("
                            INSERT INTO stage_files (
                                stage_id,
                                file_name,
                                file_path,
                                original_name,
                                file_type,
                                file_size,
                                uploaded_by,
                                uploaded_at,
                                task_id
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                        ");

                        $fileStmt->execute([
                            $stageId,
                            $fileInfo['fileName'],
                            $fileInfo['filePath'],
                            $file['name'],
                            $file['type'],
                            $file['size'],
                            $_SESSION['user_id'],
                            $taskId
                        ]);
                    }
                }

                // Handle substages
                if (!empty($stage['substages'])) {
                    foreach ($stage['substages'] as $substageIndex => $substage) {
                        // Insert substage
                        $substageStmt = $pdo->prepare("
                            INSERT INTO task_substages (
                                stage_id,
                                description,
                                status,
                                created_at,
                                updated_at,
                                start_date,
                                end_date,
                                assignee_id
                            ) VALUES (?, ?, ?, NOW(), NOW(), ?, ?, ?)
                        ");

                        $substageStmt->execute([
                            $stageId,
                            $substage['title'], // Using title as description
                            'pending', // Default status
                            $substage['startDate'],
                            $substage['dueDate'],
                            $substage['assignedTo']
                        ]);

                        $substageId = $pdo->lastInsertId();

                        // Handle substage attachment if exists
                        if (!empty($_FILES["substageFile{$stageIndex}_{$substageIndex}"])) {
                            $file = $_FILES["substageFile{$stageIndex}_{$substageIndex}"];
                            $fileInfo = handleFileUpload($file, 'substages');
                            
                            if ($fileInfo['status'] === 'success') {
                                $fileStmt = $pdo->prepare("
                                    INSERT INTO substage_files (
                                        substage_id,
                                        file_name,
                                        file_path,
                                        original_name,
                                        file_type,
                                        file_size,
                                        uploaded_by,
                                        uploaded_at,
                                        task_id
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                                ");

                                $fileStmt->execute([
                                    $substageId,
                                    $fileInfo['fileName'],
                                    $fileInfo['filePath'],
                                    $file['name'],
                                    $file['type'],
                                    $file['size'],
                                    $_SESSION['user_id'],
                                    $taskId
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // Commit transaction
        $pdo->commit();

        // Send success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Project created successfully',
            'taskId' => $taskId
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Send error response
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    // Handle non-POST requests
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}

// Helper function for file uploads
function handleFileUpload($file, $type) {
    $uploadDir = "../uploads/{$type}/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'status' => 'success',
            'fileName' => $fileName,
            'filePath' => $targetPath
        ];
    }

    return [
        'status' => 'error',
        'message' => 'Failed to upload file'
    ];
}
?> 