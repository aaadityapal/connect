<?php
require_once 'config.php';

function getTaskTimeline($taskId, $stageId = null) {
    global $conn;
    
    if ($stageId) {
        // First get the stage details
        $stageQuery = "
            SELECT 
                ts.id as stage_id,
                ts.stage_number,
                ts.status as stage_status,
                ts.created_at as stage_created,
                ts.updated_at as stage_updated,
                ts.start_date as stage_start,
                ts.due_date as stage_due,
                u.username as assigned_to
            FROM task_stages ts
            LEFT JOIN users u ON ts.assigned_to = u.id
            WHERE ts.id = ?
        ";
        
        $stageStmt = $conn->prepare($stageQuery);
        $stageStmt->bind_param("i", $stageId);
        $stageStmt->execute();
        $stageResult = $stageStmt->get_result();
        $stageData = $stageResult->fetch_assoc();

        // Then get files specifically for this stage
        $filesQuery = "
            SELECT 
                sf.id as file_id,
                sf.file_name,
                sf.file_path,
                sf.original_name,
                sf.file_type,
                sf.file_size,
                sf.uploaded_at,
                sf.uploaded_by
            FROM stage_files sf
            WHERE sf.stage_id = ?
        ";
        
        $filesStmt = $conn->prepare($filesQuery);
        $filesStmt->bind_param("i", $stageId);
        $filesStmt->execute();
        $filesResult = $filesStmt->get_result();
        
        $files = [];
        while ($file = $filesResult->fetch_assoc()) {
            $files[] = $file;
        }

        // Add status history query here
        $statusHistoryQuery = "
            SELECT 
                tsh.id,
                tsh.entity_type,
                tsh.entity_id,
                tsh.old_status,
                tsh.new_status,
                tsh.changed_at,
                u.username as changed_by
            FROM task_status_history tsh
            LEFT JOIN users u ON tsh.changed_by = u.id
            WHERE tsh.entity_type = 'stage' 
            AND tsh.entity_id = ?
            ORDER BY tsh.changed_at DESC";
        
        $statusStmt = $conn->prepare($statusHistoryQuery);
        $statusStmt->bind_param("i", $stageId);
        $statusStmt->execute();
        $statusResult = $statusStmt->get_result();
        
        $statusHistory = [];
        while ($status = $statusResult->fetch_assoc()) {
            $statusHistory[] = $status;
        }

        // Construct the timeline response
        $timeline = [
            'stage' => $stageData,
            'files' => $files,
            'status_history' => $statusHistory
        ];
        
        return $timeline;
    } else {
        // Get entire task timeline
        $query = "
            SELECT 
                t.id as task_id,
                t.title as task_title,
                t.status as task_status,
                t.created_at as task_created,
                t.updated_at as task_updated,
                ts.id as stage_id,
                ts.stage_number,
                ts.status as stage_status,
                ts.created_at as stage_created,
                ts.updated_at as stage_updated,
                ts.start_date as stage_start,
                ts.due_date as stage_due,
                tss.id as substage_id,
                tss.description as substage_desc,
                tss.status as substage_status,
                tss.created_at as substage_created,
                tss.updated_at as substage_updated,
                tss.start_date as substage_start,
                tss.end_date as substage_end,
                u.username as assigned_to
            FROM tasks t
            LEFT JOIN task_stages ts ON t.id = ts.task_id
            LEFT JOIN task_substages tss ON ts.id = tss.stage_id
            LEFT JOIN users u ON ts.assigned_to = u.id
            WHERE t.id = ?
            ORDER BY ts.created_at, tss.created_at
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $taskId);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $timeline = [];
        $files = [];
        
        while ($row = $result->fetch_assoc()) {
            // Organize stage data
            if (!isset($timeline['stage'])) {
                $timeline['stage'] = [
                    'id' => $row['stage_id'],
                    'stage_number' => $row['stage_number'],
                    'status' => $row['stage_status'],
                    'created_at' => $row['stage_created'],
                    'updated_at' => $row['stage_updated'],
                    'start_date' => $row['stage_start'],
                    'due_date' => $row['stage_due'],
                    'assigned_to' => $row['assigned_to']
                ];
            }
            
            // Collect substages if they exist
            if ($row['substage_id']) {
                if (!isset($timeline['substages'])) {
                    $timeline['substages'] = [];
                }
                $substageId = $row['substage_id'];
                if (!isset($timeline['substages'][$substageId])) {
                    $timeline['substages'][$substageId] = [
                        'id' => $substageId,
                        'description' => $row['substage_desc'],
                        'status' => $row['substage_status'],
                        'created_at' => $row['substage_created'],
                        'updated_at' => $row['substage_updated'],
                        'start_date' => $row['substage_start'],
                        'end_date' => $row['substage_end']
                    ];
                }
            }
        }
        
        // Convert substages array to indexed array
        if (isset($timeline['substages'])) {
            $timeline['substages'] = array_values($timeline['substages']);
        }
        
        // Get stage files
        $files_query = "
            SELECT 
                sf.*,
                ts.stage_number,
                ts.status as stage_status
            FROM stage_files sf
            INNER JOIN task_stages ts ON sf.stage_id = ts.id
            WHERE sf.stage_id = ?
            AND ts.stage_number = (
                SELECT stage_number 
                FROM task_stages 
                WHERE id = ?
            )";

        $files_stmt = $conn->prepare($files_query);
        $files_stmt->bind_param("ii", $stageId, $stageId);
        $files_stmt->execute();
        $files = $files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get substage files
        $substageFilesQuery = "
            SELECT 
                sf.*,
                tss.description as substage_description
            FROM substage_files sf
            INNER JOIN task_substages tss ON sf.substage_id = tss.id
            WHERE sf.substage_id = ?
            AND tss.id = ?";

        $substageFilesStmt = $conn->prepare($substageFilesQuery);
        $substageFilesStmt->bind_param("ii", $substage_id, $substage_id);
        $substageFilesStmt->execute();
        $substageFiles = $substageFilesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Add status history for all stages in the task
        $statusHistoryQuery = "
            SELECT 
                tsh.id,
                tsh.entity_type,
                tsh.entity_id,
                tsh.old_status,
                tsh.new_status,
                tsh.changed_at,
                u.username as changed_by,
                CASE 
                    WHEN tsh.entity_type = 'stage' THEN ts.stage_number
                    WHEN tsh.entity_type = 'substage' THEN tss.description
                END as entity_name
            FROM task_status_history tsh
            LEFT JOIN users u ON tsh.changed_by = u.id
            LEFT JOIN task_stages ts ON (tsh.entity_type = 'stage' AND tsh.entity_id = ts.id)
            LEFT JOIN task_substages tss ON (tsh.entity_type = 'substage' AND tsh.entity_id = tss.id)
            WHERE ts.task_id = ?
            ORDER BY tsh.changed_at DESC";
        
        $statusStmt = $conn->prepare($statusHistoryQuery);
        $statusStmt->bind_param("i", $taskId);
        $statusStmt->execute();
        $statusResult = $statusStmt->get_result();
        
        $statusHistory = [];
        while ($status = $statusResult->fetch_assoc()) {
            $statusHistory[] = $status;
        }

        // Add status_history to the existing timeline array
        $timeline['status_history'] = $statusHistory;
        
        return $timeline;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $taskId = $_GET['task_id'] ?? null;
    $stageId = $_GET['stage_id'] ?? null;
    
    if ($taskId) {
        $timeline = getTaskTimeline($taskId, $stageId);
        echo json_encode(['success' => true, 'timeline' => $timeline]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Task ID is required']);
    }
}
?>