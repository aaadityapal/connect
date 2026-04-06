<?php
date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';
session_start();

function normalizeDateTimeLocalToIst(?string $value): ?string {
    $raw = trim((string)($value ?? ''));
    if ($raw === '') {
        return null;
    }

    $tz = new DateTimeZone('Asia/Kolkata');
    $formats = ['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $raw, $tz);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    try {
        $dt = new DateTime($raw, $tz);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function hasCreateProjectPermission(PDO $pdo, int $userId): bool {
    if (!tableExists($pdo, 'project_permissions')) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT can_create_project FROM project_permissions WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return isset($row['can_create_project']) && (int)$row['can_create_project'] === 1;
}

function getColumnsMap(PDO $pdo, string $table): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (!empty($col['Field'])) {
            $map[$col['Field']] = $col;
        }
    }
    return $map;
}

try {
    $pdo->exec("SET time_zone = '+05:30'");

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    if (!hasCreateProjectPermission($pdo, (int)$_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'You do not have permission to create projects.'
        ]);
        exit;
    }
    
    if (!isset($data['project_id']) || !isset($data['stages'])) {
        throw new Exception('Missing required data');
    }

    // Get the user ID from the data, or use a default
    $userId = isset($data['user_id']) ? $data['user_id'] : ($_SESSION['user_id'] ?? 1);

    $pdo->beginTransaction();

    $substageTable = null;
    if (tableExists($pdo, 'project_substages')) {
        $substageTable = 'project_substages';
    } elseif (tableExists($pdo, 'project_susbatges')) {
        $substageTable = 'project_susbatges';
    }
    if ($substageTable === null) {
        throw new Exception('Substage table not found');
    }
    $substageCols = getColumnsMap($pdo, $substageTable);
    $substageIdentifierWritable = isset($substageCols['substage_identifier'])
        && stripos((string)($substageCols['substage_identifier']['Extra'] ?? ''), 'GENERATED') === false;
    
    // --- TASK INTEGRATION ADDITIONS ---
    // Fetch project name for the task
    $stmtProj = $pdo->prepare("SELECT title FROM projects WHERE id = ?");
    $stmtProj->execute([$data['project_id']]);
    $projectName = $stmtProj->fetchColumn() ?: 'Unnamed Project';
    
    $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $taskQuery = "INSERT INTO studio_assigned_tasks (
        project_id, project_name, stage_id, stage_number, task_description, priority,
        assigned_to, assigned_names, due_date, due_time, is_recurring, status, created_by, created_at
    ) VALUES (
        :project_id, :project_name, :stage_id, :stage_number, :task_description, 'Medium',
        :assigned_to, :assigned_names, :due_date, :due_time, 0, 'Pending', :created_by, NOW()
    )";
    $stmtTask = $pdo->prepare($taskQuery);
    
    $logStmt = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed) VALUES (:user_id, 'task_assigned', 'task', :entity_id, :description, :metadata, NOW(), 0, 0)");
    // -----------------------------------
    
    foreach ($data['stages'] as $stageIndex => $stage) {
        // Convert assignTo value 0 to NULL for database storage
        $stageAssignedTo = (!empty($stage['assignTo']) && $stage['assignTo'] !== '0') ? $stage['assignTo'] : null;
        $stageStartDate = normalizeDateTimeLocalToIst($stage['startDate'] ?? null);
        $stageDueDate = normalizeDateTimeLocalToIst($stage['dueDate'] ?? null);
        
        // Insert stage
        $stageQuery = "INSERT INTO project_stages (
            project_id,
            stage_number,
            assigned_to,
            start_date,
            end_date,
            status,
            created_at,
            created_by,
            updated_by
        ) VALUES (
            :project_id,
            :stage_number,
            :assigned_to,
            :start_date,
            :end_date,
            'pending',
            NOW(),
            :created_by,
            :updated_by
        )";
        
        $stmt = $pdo->prepare($stageQuery);
        $stmt->execute([
            ':project_id' => $data['project_id'],
            ':stage_number' => $stageIndex + 1,
            ':assigned_to' => $stageAssignedTo,
            ':start_date' => $stageStartDate,
            ':end_date' => $stageDueDate,
            ':created_by' => $userId,
            ':updated_by' => $userId
        ]);
        
        $stageId = $pdo->lastInsertId();
        
        // Handle stage files
        if (!empty($stage['files'])) {
            foreach ($stage['files'] as $file) {
                $stageFileQuery = "INSERT INTO stage_files (
                    stage_id,
                    file_name,
                    file_path,
                    original_name,
                    file_type,
                    file_size,
                    uploaded_by,
                    uploaded_at
                ) VALUES (
                    :stage_id,
                    :file_name,
                    :file_path,
                    :original_name,
                    :file_type,
                    :file_size,
                    :uploaded_by,
                    NOW()
                )";
                
                $stmt = $pdo->prepare($stageFileQuery);
                $stmt->execute([
                    ':stage_id' => $stageId,
                    ':file_name' => $file['name'],
                    ':file_path' => $file['path'],
                    ':original_name' => $file['originalName'],
                    ':file_type' => $file['type'],
                    ':file_size' => $file['size'],
                    ':uploaded_by' => $userId
                ]);
            }
        }
        
        // Handle substages
        if (!empty($stage['substages'])) {
            foreach ($stage['substages'] as $substageIndex => $substage) {
                // Convert assignTo value 0 to NULL for database storage
                $substageAssignedTo = (!empty($substage['assignTo']) && $substage['assignTo'] !== '0') ? $substage['assignTo'] : null;
                $substageStartDate = normalizeDateTimeLocalToIst($substage['startDate'] ?? null);
                $substageDueDate = normalizeDateTimeLocalToIst($substage['dueDate'] ?? null);
                
                $substageIdentifier = "S{$stageIndex}SS{$substageIndex}";

                $insertData = [
                    'stage_id' => $stageId,
                    'substage_number' => $substageIndex + 1,
                    'title' => $substage['title'] ?? null,
                    'assigned_to' => $substageAssignedTo,
                    'start_date' => $substageStartDate,
                    'end_date' => $substageDueDate,
                    'status' => 'pending',
                    'created_by' => $userId,
                    'drawing_number' => $substage['drawingNumber'] ?? null,
                    'updated_by' => $userId,
                    'is_task_created' => 0
                ];

                if ($substageIdentifierWritable) {
                    $insertData['substage_identifier'] = $substageIdentifier;
                }

                $insertColumns = [];
                $insertValues = [];
                $params = [];
                foreach ($insertData as $col => $val) {
                    if (!isset($substageCols[$col])) continue;
                    $insertColumns[] = "`{$col}`";
                    $insertValues[] = ':' . $col;
                    $params[':' . $col] = $val;
                }
                if (isset($substageCols['created_at'])) {
                    $insertColumns[] = '`created_at`';
                    $insertValues[] = 'NOW()';
                }
                if (isset($substageCols['updated_at'])) {
                    $insertColumns[] = '`updated_at`';
                    $insertValues[] = 'NOW()';
                }

                $substageQuery = "INSERT INTO {$substageTable} (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";

                $stmt = $pdo->prepare($substageQuery);
                $stmt->execute($params);
                
                $substageId = $pdo->lastInsertId();
                
                // --- ADD SUBSTAGE AS TASK IF ASSIGNED ---
                if ($substageAssignedTo) {
                    $stmtUser->execute([$substageAssignedTo]);
                    $assignedName = $stmtUser->fetchColumn() ?: 'Unknown';
                    
                    $dueDateTime = !empty($substageDueDate)
                        ? new DateTime($substageDueDate, new DateTimeZone('Asia/Kolkata'))
                        : null;
                    $dueDateStr = $dueDateTime ? $dueDateTime->format('Y-m-d') : null;
                    $dueTimeStr = $dueDateTime ? $dueDateTime->format('H:i') : null;
                    
                    $substageNumberStr = ($stageIndex + 1) . '.' . ($substageIndex + 1);
                    
                    $stmtTask->execute([
                        ':project_id' => $data['project_id'],
                        ':project_name' => $projectName,
                        ':stage_id' => $stageId,
                        ':stage_number' => $substageNumberStr,
                        ':task_description' => $substage['title'],
                        ':assigned_to' => $substageAssignedTo,
                        ':assigned_names' => $assignedName,
                        ':due_date' => $dueDateStr,
                        ':due_time' => $dueTimeStr,
                        ':created_by' => $userId
                    ]);
                    
                    $taskId = $pdo->lastInsertId();
                    
                    // Log task assignment
                    $taskLabel = $projectName . ' — Substage ' . $substageNumberStr;
                    $logDesc = "Task assigned: \"{$taskLabel}\" → {$assignedName}";
                    $logMeta = json_encode([
                        'task_id'          => $taskId,
                        'project_name'     => $projectName,
                        'stage_number'     => $substageNumberStr,
                        'task_description' => $substage['title'],
                        'priority'         => 'Medium',
                        'assigned_names'   => $assignedName,
                        'due_date'         => $dueDateStr,
                    ]);
                    
                    // Log for creator (manager) — "You assigned..."
                    $logStmt->execute([
                        'user_id'     => $userId,
                        'entity_id'   => $taskId,
                        'description' => "You assigned: \"{$taskLabel}\" → {$assignedName}",
                        'metadata'    => $logMeta,
                    ]);
                    
                    // Log for the assignee — "You have been assigned..."
                    $logStmtAssignee = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed) VALUES (:user_id, 'task_assigned', 'task', :entity_id, :description, :metadata, NOW(), 0, 0)");
                    $logStmtAssignee->execute([
                        'user_id'     => $substageAssignedTo,
                        'entity_id'   => $taskId,
                        'description' => "You have been assigned a task: \"{$taskLabel}\"",
                        'metadata'    => $logMeta,
                    ]);
                    
                    // Mark as task created to prevent reassignment on future updates
                    if (isset($substageCols['is_task_created'])) {
                        $stmtUpdateFlag = $pdo->prepare("UPDATE {$substageTable} SET is_task_created = 1 WHERE id = ?");
                        $stmtUpdateFlag->execute([$substageId]);
                    }
                }
                // ----------------------------------------
                
                // Handle substage files
                if (!empty($substage['files'])) {
                    foreach ($substage['files'] as $file) {
                        $substageFileQuery = "INSERT INTO substage_files (
                            substage_id,
                            file_name,
                            file_path,
                            type,
                            uploaded_by,
                            uploaded_at,
                            status,
                            created_at
                        ) VALUES (
                            :substage_id,
                            :file_name,
                            :file_path,
                            :type,
                            :uploaded_by,
                            NOW(),
                            'active',
                            NOW()
                        )";
                        
                        $stmt = $pdo->prepare($substageFileQuery);
                        $stmt->execute([
                            ':substage_id' => $substageId,
                            ':file_name' => $file['name'],
                            ':file_path' => $file['path'],
                            ':type' => $file['type'],
                            ':uploaded_by' => $userId
                        ]);
                    }
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Stages and substages created successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create stages: ' . $e->getMessage()
    ]);
}
exit; 