<?php
// Save construction site task to database
header('Content-Type: application/json');
session_start(); // Start session to access $_SESSION

try {
    // Include database connection
    require_once '../config/db_connect.php';

    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }

    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        $data = $_POST; // Fallback to POST data
    }

    // Validate required fields (Only if creating new task)
    if (empty($data['id'])) {
        if (empty($data['title']) || empty($data['start_date']) || empty($data['end_date'])) {
            throw new Exception('Missing required fields: title, start_date, end_date');
        }
    }

    // Validate project_id
    if (empty($data['project_id'])) {
        throw new Exception('Project ID is required');
    }

    // Get user ID from session
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        throw new Exception('User not authenticated');
    }

    $createdBy = $userId;
    $updatedBy = $userId;

    // Get assigned user ID if assignee username is provided
    $assignedUserId = null;
    if (!empty($data['assign_to'])) {
        $userQuery = "SELECT id FROM users WHERE username = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$data['assign_to']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $assignedUserId = $user['id'] ?? null;
    }

    // Handle images
    $images = null;
    if (!empty($data['images'])) {
        if (is_array($data['images'])) {
            $images = json_encode($data['images']);
        } else {
            $images = $data['images'];
        }
    }

    if (!empty($data['id'])) {
        // Fetch old task data before updating
        $oldTaskQuery = "SELECT title, description, supervisor_notes, start_date, end_date, status, assign_to, assigned_user_id, images, project_id FROM construction_site_tasks WHERE id = ?";
        $oldTaskStmt = $pdo->prepare($oldTaskQuery);
        $oldTaskStmt->execute([$data['id']]);
        $oldTaskRow = $oldTaskStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldTaskRow) {
            throw new Exception('Task not found');
        }

        // Merge existing data if missing in request (Partial Update Support)
        if (!isset($data['title']))
            $data['title'] = $oldTaskRow['title'];
        if (!isset($data['start_date']))
            $data['start_date'] = $oldTaskRow['start_date'];
        if (!isset($data['end_date']))
            $data['end_date'] = $oldTaskRow['end_date'];
        if (!isset($data['description']))
            $data['description'] = $oldTaskRow['description'];
        if (!isset($data['assign_to']))
            $data['assign_to'] = $oldTaskRow['assign_to'];
        if (!isset($data['project_id']))
            $data['project_id'] = $oldTaskRow['project_id'];

        // Logic to preserve assigned_user_id if assign_to wasn't updated
        // $assignedUserId was calculated early (line 47) based on input assign_to.
        // If assign_to input was missing (partial update), $assignedUserId is null.
        // But we just restored $data['assign_to'] from old row. We must restore ID too.
        $finalAssignedUserId = $assignedUserId;
        // Check if we restored assign_to (meaning it wasn't in original data... check if $data['assign_to'] was set before merge? 
        // Too late, we modified $data. 
        // But we can check if $assignedUserId is null AND $data['assign_to'] is NOT empty (meaning we have an assignee).
        // If $assignedUserId is null but $data['assign_to'] has a value, it means we restored it (or user passed invalid username... assuming restored).
        // Better: check if we are in the "assign_to restored" case.
        // Let's re-query the logic:
        // We know $assignedUserId is null if input assign_to was empty/missing.
        // If we have an assignee string now ($data['assign_to']), and $assignedUserId is null, we should use the old ID.
        if (empty($assignedUserId) && !empty($oldTaskRow['assigned_user_id']) && $data['assign_to'] === $oldTaskRow['assign_to']) {
            $finalAssignedUserId = $oldTaskRow['assigned_user_id'];
        }

        // Preserve images if not sent
        if (!isset($data['images'])) {
            // If we calculated $images above from null input, it might be null.
            // But if the key wasn't in input, we shouldn't overwrite.
            // Logic: If 'images' key exists in input, we use it. If not, use DB.
            // But we parsed it earlier. Let's just re-fetch raw DB value here if needed.
            // actually $images variable handles input. If input has no images, $images is null or whatever.
            // Let's rely on explicit check.
            // If client didn't send 'images', keep old.
            // But accessing $data['images'] might be tricky if it wasn't sent.
            // Let's assume if it's disabled in frontend, it's not in $data.
            // But $images var was set at line 51.
            // If input didn't have images, $images is null.
            // If DB has images, we don't want to overwrite with null unless intended.
            $images = $oldTaskRow['images'];
        } else {
            // If user sent images (even empty), we update. 
            // But wait, the frontend sends everything usually. 
            // If we disable fields, 'images' wont be sent. 
            // So relying on $images variable from line 51 which defaults to null is dangerous if we want to preserve.
            // Let's re-evaluate $images below.
        }

        $oldStatus = $oldTaskRow['status'] ?? null;
        $newStatus = $data['status'] ?? 'planned';

        // Update existing task
        $query = "UPDATE construction_site_tasks 
                  SET title = ?, 
                      description = ?, 
                      supervisor_notes = ?,
                      start_date = ?, 
                      end_date = ?, 
                      status = ?, 
                      assign_to = ?,
                      assigned_user_id = ?,
                      images = ?,
                      updated_by = ?,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = ? AND deleted_at IS NULL";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['supervisor_notes'] ?? $oldTaskRow['supervisor_notes'] ?? null, // Use new if sent, else keep old
            $data['start_date'],
            $data['end_date'],
            $newStatus,
            $data['assign_to'] ?? null,
            $finalAssignedUserId,
            // If images were provided in input, use them. Else use old.
            // Check if key existed in original input? 
            // Better: if $data['images'] is set, use $images (processed), else $oldTaskRow['images']
            isset($data['images']) ? $images : $oldTaskRow['images'],
            $updatedBy,
            $data['id']
        ]);

        // Build log details
        $changes = [];

        // Check Status
        if ($oldStatus !== $newStatus) {
            $changes[] = "Status: '$oldStatus' -> '$newStatus'";
        }
        // Check Title
        if ($oldTaskRow['title'] !== $data['title']) {
            $changes[] = "Title updated";
        }
        // Check Start Date
        if ($oldTaskRow['start_date'] !== $data['start_date']) {
            $changes[] = "Start Date: '{$oldTaskRow['start_date']}' -> '{$data['start_date']}'";
        }
        // Check End Date
        if ($oldTaskRow['end_date'] !== $data['end_date']) {
            $changes[] = "End Date: '{$oldTaskRow['end_date']}' -> '{$data['end_date']}'";
        }
        // Check Description
        $oldDesc = $oldTaskRow['description'] ?? '';
        $newDesc = $data['description'] ?? '';
        if ($oldDesc !== $newDesc) {
            $changes[] = "Description updated";
        }
        // Check Assignee
        $oldAssign = $oldTaskRow['assign_to'] ?? '';
        $newAssign = $data['assign_to'] ?? '';
        if ($oldAssign !== $newAssign) {
            if (empty($oldAssign)) {
                $changes[] = "Assigned to '$newAssign'";
            } else if (empty($newAssign)) {
                $changes[] = "Unassigned from '$oldAssign'";
            } else {
                $changes[] = "Assignee: '$oldAssign' -> '$newAssign'";
            }
        }
        // Check Supervisor Notes
        $oldNotes = $oldTaskRow['supervisor_notes'] ?? '';
        $newNotes = $data['supervisor_notes'] ?? '';
        if ($oldNotes !== $newNotes && !empty($newNotes)) {
            $changes[] = "Supervisor Review: '$newNotes'";
        }

        if (!empty($changes)) {
            $action = ($oldStatus !== $newStatus && count($changes) == 1) ? 'STATUS_CHANGE' : 'UPDATED';
            $logDetails = implode(', ', $changes);

            $logQuery = "INSERT INTO construction_task_logs 
                        (task_id, action_type, old_status, new_status, performed_by, details) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([
                $data['id'],
                $action,
                $oldStatus,
                $newStatus,
                $userId,
                $logDetails
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Task updated successfully',
            'task_id' => $data['id']
        ]);
    } else {
        // Create new task
        $newStatus = $data['status'] ?? 'planned';

        $query = "INSERT INTO construction_site_tasks 
                  (project_id, title, description, start_date, end_date, status, assign_to, assigned_user_id, images, created_by, updated_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data['project_id'] ?? null,
            $data['title'],
            $data['description'] ?? null,
            $data['start_date'],
            $data['end_date'],
            $newStatus,
            $data['assign_to'] ?? null,
            $assignedUserId,
            $images,
            $createdBy,
            $updatedBy
        ]);

        $taskId = $pdo->lastInsertId();

        // Log creation
        $logQuery = "INSERT INTO construction_task_logs 
                    (task_id, action_type, old_status, new_status, performed_by, details) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([
            $taskId,
            'CREATED',
            null,
            $newStatus,
            $userId,
            "Task created"
        ]);

        $taskId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>