<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

$payload = json_decode(file_get_contents('php://input'), true);
$employeeId = isset($payload['employee_id']) ? (int)$payload['employee_id'] : 0;
$triggerSource = isset($payload['trigger_source']) ? trim((string)$payload['trigger_source']) : 'unknown';
$completionPercent = isset($payload['completion_percent']) ? (int)$payload['completion_percent'] : null;
if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee id'
    ]);
    exit();
}

try {
    require_once '../../../config/db_connect.php';

    $insertLog = function(
        int $userId,
        string $actionType,
        string $entityType,
        ?int $entityId,
        string $description,
        array $meta = []
    ) use ($pdo): void {
        $stmt = $pdo->prepare(
            "INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
             VALUES
                (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0, 0)"
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':action_type' => $actionType,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':description' => $description,
            ':metadata' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    };

    // Ensure assignee exists
    $userStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $employeeId]);
    $assignee = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }

    // Assigned by should be HR. Prefer current user if HR, else pick first HR.
    $createdBy = (int)($_SESSION['user_id'] ?? 0);
    $sessionRole = trim((string)($_SESSION['role'] ?? ''));

    if (strcasecmp($sessionRole, 'HR') !== 0) {
        $hrStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'HR' AND (status IS NULL OR LOWER(status) = 'active') ORDER BY id ASC LIMIT 1");
        $hrStmt->execute();
        $hrUser = $hrStmt->fetch(PDO::FETCH_ASSOC);
        if ($hrUser && !empty($hrUser['id'])) {
            $createdBy = (int)$hrUser['id'];
        }
    }

    if ($createdBy <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to resolve HR assignee'
        ]);
        exit();
    }

    $today = date('Y-m-d');
    $dueTime = '18:00:00';
    $projectName = 'ArchitectsHive Back Office';
    $taskDescription = 'Complete your profile as soon as possible';
    $lockKey = sprintf('profile_reminder_%d_%s', $employeeId, $today);
    $lockAcquired = false;

    $creatorName = (string)($_SESSION['username'] ?? 'HR');
    if (strcasecmp($sessionRole, 'HR') !== 0) {
        $creatorStmt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
        $creatorStmt->execute([':id' => $createdBy]);
        $creatorRow = $creatorStmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($creatorRow['username'])) {
            $creatorName = (string)$creatorRow['username'];
        }
    }

    // Concurrency-safe lock to prevent duplicate inserts for same user/day.
    $lockStmt = $pdo->prepare("SELECT GET_LOCK(:lock_key, 5) AS got_lock");
    $lockStmt->execute([':lock_key' => $lockKey]);
    $lockRow = $lockStmt->fetch(PDO::FETCH_ASSOC);
    $lockAcquired = isset($lockRow['got_lock']) && (int)$lockRow['got_lock'] === 1;

    if (!$lockAcquired) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Reminder is already being processed. Please try again.'
        ]);
        exit();
    }

        // Avoid duplicate reminder tasks for same user on same day
        // NOTE: assigned_to is TEXT and may be stored as single id or comma-separated ids.
        $checkStmt = $pdo->prepare("SELECT id FROM studio_assigned_tasks
                WHERE project_name = :project_name
                    AND task_description = :task_description
                    AND due_date = :due_date
                    AND deleted_at IS NULL
                    AND (
                                assigned_to = :assigned_to_exact
                                OR FIND_IN_SET(:assigned_to_csv, REPLACE(IFNULL(assigned_to, ''), ' ', '')) > 0
                    )
                LIMIT 1");
    $checkStmt->execute([
                ':assigned_to_exact' => (string)$employeeId,
                ':assigned_to_csv' => (string)$employeeId,
        ':project_name' => $projectName,
        ':task_description' => $taskDescription,
        ':due_date' => $today,
    ]);

    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $meta = [
            'reminder_type' => 'profile_completion',
            'event' => 'already_exists',
            'task_id' => (int)$existing['id'],
            'employee_id' => (int)$assignee['id'],
            'employee_name' => (string)$assignee['username'],
            'assigned_by_id' => $createdBy,
            'assigned_by_name' => $creatorName,
            'project_name' => $projectName,
            'task_description' => $taskDescription,
            'due_date' => $today,
            'due_time' => $dueTime,
            'trigger_source' => $triggerSource,
            'completion_percent' => $completionPercent,
        ];

        $insertLog(
            $createdBy,
            'profile_reminder_exists',
            'task',
            (int)$existing['id'],
            "Profile reminder already exists for {$assignee['username']} (today).",
            $meta
        );

        $unlockStmt = $pdo->prepare("SELECT RELEASE_LOCK(:lock_key)");
        $unlockStmt->execute([':lock_key' => $lockKey]);
        $lockAcquired = false;

        echo json_encode([
            'success' => true,
            'message' => 'Reminder already exists for today',
            'task_id' => (int)$existing['id']
        ]);
        exit();
    }

    $insertStmt = $pdo->prepare("INSERT INTO studio_assigned_tasks
        (project_id, project_name, stage_id, stage_number, task_description, priority, assigned_to, assigned_names, due_date, due_time, is_recurring, status, created_by, created_at)
        VALUES
        (NULL, :project_name, NULL, NULL, :task_description, 'Medium', :assigned_to, :assigned_names, :due_date, :due_time, 0, 'Pending', :created_by, NOW())");

    $insertStmt->execute([
        ':project_name' => $projectName,
        ':task_description' => $taskDescription,
        ':assigned_to' => (string)$employeeId,
        ':assigned_names' => (string)$assignee['username'],
        ':due_date' => $today,
        ':due_time' => $dueTime,
        ':created_by' => $createdBy,
    ]);

    $taskId = (int)$pdo->lastInsertId();

    $meta = [
        'reminder_type' => 'profile_completion',
        'event' => 'created',
        'task_id' => $taskId,
        'employee_id' => (int)$assignee['id'],
        'employee_name' => (string)$assignee['username'],
        'assigned_by_id' => $createdBy,
        'assigned_by_name' => $creatorName,
        'project_name' => $projectName,
        'task_description' => $taskDescription,
        'due_date' => $today,
        'due_time' => $dueTime,
        'trigger_source' => $triggerSource,
        'completion_percent' => $completionPercent,
    ];

    // log for creator
    $insertLog(
        $createdBy,
        'profile_reminder_sent',
        'task',
        $taskId,
        "You sent a profile completion reminder to {$assignee['username']}.",
        $meta
    );

    // log for assignee
    if ((int)$assignee['id'] !== $createdBy) {
        $insertLog(
            (int)$assignee['id'],
            'profile_reminder_received',
            'task',
            $taskId,
            "HR assigned you a profile completion reminder task.",
            $meta
        );
    }

    $unlockStmt = $pdo->prepare("SELECT RELEASE_LOCK(:lock_key)");
    $unlockStmt->execute([':lock_key' => $lockKey]);
    $lockAcquired = false;

    echo json_encode([
        'success' => true,
        'message' => 'Reminder task created successfully',
        'task_id' => $taskId
    ]);
} catch (Throwable $e) {
    try {
        if (isset($pdo) && $pdo instanceof PDO && isset($_SESSION['user_id'])) {
            if (isset($lockAcquired) && $lockAcquired === true && isset($lockKey)) {
                try {
                    $unlockStmt = $pdo->prepare("SELECT RELEASE_LOCK(:lock_key)");
                    $unlockStmt->execute([':lock_key' => $lockKey]);
                } catch (Throwable $ignore) {
                    // no-op
                }
            }

            $failStmt = $pdo->prepare(
                "INSERT INTO global_activity_logs
                    (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
                 VALUES
                    (:user_id, 'profile_reminder_failed', 'task', NULL, :description, :metadata, NOW(), 0, 0)"
            );

            $failStmt->execute([
                ':user_id' => (int)$_SESSION['user_id'],
                ':description' => 'Failed to create profile completion reminder task.',
                ':metadata' => json_encode([
                    'employee_id' => $employeeId,
                    'trigger_source' => $triggerSource,
                    'completion_percent' => $completionPercent,
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }
    } catch (Throwable $ignore) {
        // no-op
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create reminder task'
    ]);
}
