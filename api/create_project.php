<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

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

function insertGlobalActivityLog(PDO $pdo, array $log): void {
    if (!tableExists($pdo, 'global_activity_logs')) {
        return;
    }

    $availableCols = [];
    $colsStmt = $pdo->query('SHOW COLUMNS FROM global_activity_logs');
    foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (!empty($col['Field'])) {
            $availableCols[$col['Field']] = true;
        }
    }

    $insertData = [
        'user_id' => (int)($log['user_id'] ?? 0),
        'action_type' => (string)($log['action_type'] ?? 'project_created'),
        'entity_type' => (string)($log['entity_type'] ?? 'project'),
        'entity_id' => isset($log['entity_id']) ? (int)$log['entity_id'] : null,
        'description' => (string)($log['description'] ?? 'Project activity'),
        'metadata' => isset($log['metadata'])
            ? json_encode($log['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null,
        'is_read' => 0,
        'is_dismissed' => 0
    ];

    $cols = [];
    $vals = [];
    $params = [];
    foreach ($insertData as $col => $val) {
        if (!isset($availableCols[$col])) continue;
        $cols[] = "`{$col}`";
        $vals[] = '?';
        $params[] = $val;
    }
    if (isset($availableCols['created_at'])) {
        $cols[] = '`created_at`';
        $vals[] = 'NOW()';
    }

    if (empty($cols)) {
        return;
    }

    $sql = 'INSERT INTO global_activity_logs (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

try {
    $pdo->exec("SET time_zone = '+05:30'");

    // Check if user is logged in
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

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Debug log
    error_log('Received project data: ' . json_encode($data));

    // Validate category ID
    if (empty($data['projectCategory'])) {
        throw new Exception('Category ID is required');
    }

    // Verify user exists and is active
    $userQuery = "SELECT id FROM users WHERE id = :user_id AND status = 'active'";
    $stmt = $pdo->prepare($userQuery);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);

    if (!$stmt->fetch()) {
        throw new Exception('Invalid user');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Insert into projects table
    $projectQuery = "INSERT INTO projects (
        title, 
        description, 
        project_type, 
        category_id, 
        start_date, 
        end_date, 
        created_by, 
        assigned_to, 
        status,
        created_at,
        client_name,
        client_address,
        project_location,
        plot_area,
        contact_number
    ) VALUES (
        :title,
        :description,
        :project_type,
        :category_id,
        :start_date,
        :end_date,
        :created_by,
        :assigned_to,
        'pending',
        NOW(),
        :client_name,
        :client_address,
        :project_location,
        :plot_area,
        :contact_number
    )";

    // Convert assignTo value 0 to NULL for database storage
    $assignedTo = (!empty($data['assignTo']) && $data['assignTo'] !== '0') ? $data['assignTo'] : null;
    $projectStartDate = normalizeDateTimeLocalToIst($data['startDate'] ?? null);
    $projectDueDate = normalizeDateTimeLocalToIst($data['dueDate'] ?? null);

    $stmt = $pdo->prepare($projectQuery);
    $result = $stmt->execute([
        ':title' => $data['projectTitle'],
        ':description' => $data['projectDescription'],
        ':project_type' => $data['projectType'],
        ':category_id' => $data['projectCategory'],
        ':start_date' => $projectStartDate,
        ':end_date' => $projectDueDate,
        ':created_by' => $_SESSION['user_id'] ?? 1,
        ':assigned_to' => $assignedTo,
        ':client_name' => $data['client_name'] ?? null,
        ':client_address' => $data['client_address'] ?? null,
        ':project_location' => $data['project_location'] ?? null,
        ':plot_area' => $data['plot_area'] ?? null,
        ':contact_number' => $data['contact_number'] ?? null
    ]);

    if (!$result) {
        throw new Exception('Failed to insert project: ' . json_encode($stmt->errorInfo()));
    }

    $projectId = $pdo->lastInsertId();

    // Log in project_activity_log
    $activityQuery = "INSERT INTO project_activity_log (
        project_id,
        activity_type,
        description,
        performed_by,
        performed_at
    ) VALUES (
        :project_id,
        'other',
        'Project created',
        :performed_by,
        NOW()
    )";

    $stmt = $pdo->prepare($activityQuery);
    $stmt->execute([
        ':project_id' => $projectId,
        ':performed_by' => $_SESSION['user_id']  // Use session user ID
    ]);

    // Log in project_history
    $historyQuery = "INSERT INTO project_history (
        project_id,
        action_type,
        new_value,
        changed_by,
        changed_at
    ) VALUES (
        :project_id,
        'created',
        :new_value,
        :changed_by,
        NOW()
    )";

    $stmt = $pdo->prepare($historyQuery);
    $stmt->execute([
        ':project_id' => $projectId,
        ':new_value' => json_encode($data),
        ':changed_by' => $_SESSION['user_id']  // Use session user ID
    ]);

    // Log detailed create event in global_activity_logs
    try {
        insertGlobalActivityLog($pdo, [
            'user_id' => (int)$_SESSION['user_id'],
            'action_type' => 'project_created',
            'entity_type' => 'project',
            'entity_id' => (int)$projectId,
            'description' => 'Project created: ' . (string)($data['projectTitle'] ?? ('Project #' . $projectId)),
            'metadata' => [
                'project_id' => (int)$projectId,
                'project' => [
                    'title' => $data['projectTitle'] ?? null,
                    'description' => $data['projectDescription'] ?? null,
                    'project_type' => $data['projectType'] ?? null,
                    'category_id' => $data['projectCategory'] ?? null,
                    'start_date' => $projectStartDate,
                    'end_date' => $projectDueDate,
                    'assigned_to' => $assignedTo,
                    'client_name' => $data['client_name'] ?? null,
                    'client_address' => $data['client_address'] ?? null,
                    'project_location' => $data['project_location'] ?? null,
                    'plot_area' => $data['plot_area'] ?? null,
                    'contact_number' => $data['contact_number'] ?? null
                ],
                'created_by' => (int)$_SESSION['user_id'],
                'source' => 'api/create_project.php'
            ]
        ]);
    } catch (Throwable $logError) {
        error_log('Project create activity log failed: ' . $logError->getMessage());
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Project created successfully',
        'project_id' => $projectId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Error creating project: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create project: ' . $e->getMessage()
    ]);
}
exit;