<?php
/**
 * DEBUG: Travel Expense Task Spawn Checker
 * Run this in browser: /studio_users/api/debug_travel_task.php?user_id=YOUR_USER_ID
 * Remove this file after debugging!
 */
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

$user_id = (int)($_GET['user_id'] ?? $_SESSION['user_id'] ?? 0);
if (!$user_id) {
    echo json_encode(['error' => 'Pass ?user_id=X in URL']);
    exit();
}

$report = [];

// ── 1. Does the user exist? ────────────────────────────────────────
$uStmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$uStmt->execute([$user_id]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC);
$report['1_user'] = $user ?: 'USER NOT FOUND';

// ── 2. Does travel_expense_mapping exist for this user? ────────────
$mapStmt = $pdo->prepare("
    SELECT tem.*, 
           m.username  AS manager_name,
           h.username  AS hr_name,
           s.username  AS sr_manager_name
    FROM travel_expense_mapping tem
    LEFT JOIN users m ON m.id = tem.manager_id
    LEFT JOIN users h ON h.id = tem.hr_id
    LEFT JOIN users s ON s.id = tem.senior_manager_id
    WHERE tem.employee_id = ?
");
$mapStmt->execute([$user_id]);
$mapping = $mapStmt->fetch(PDO::FETCH_ASSOC);
$report['2_travel_expense_mapping'] = $mapping ?: '⚠️ NO MAPPING FOUND — This is why no task is created!';

// ── 3. Recent travel expenses for this user ────────────────────────
$expStmt = $pdo->prepare("
    SELECT id, travel_date, purpose, amount, status, created_at
    FROM travel_expenses
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$expStmt->execute([$user_id]);
$report['3_recent_expenses'] = $expStmt->fetchAll(PDO::FETCH_ASSOC) ?: 'No expenses found';

// ── 4. Were any tasks spawned for this user? ──────────────────────
$taskStmt = $pdo->prepare("
    SELECT id, task_description, assigned_to, assigned_names, due_date, due_time, status, created_at
    FROM studio_assigned_tasks
    WHERE project_name = 'ArchitectsHive Systems'
      AND created_by = ?
      AND is_system_task = 1
    ORDER BY created_at DESC
    LIMIT 5
");
$taskStmt->execute([$user_id]);
$report['4_spawned_tasks'] = $taskStmt->fetchAll(PDO::FETCH_ASSOC) ?: '⚠️ No system tasks found for this user';

// ── 5. Dedup check — would the current code skip? ─────────────────
if ($user) {
    $name = $user['username'];
    $dupStmt = $pdo->prepare("
        SELECT id, status FROM studio_assigned_tasks
        WHERE project_name = 'ArchitectsHive Systems'
          AND task_description LIKE ?
          AND status NOT IN ('Completed', 'Cancelled')
        LIMIT 1
    ");
    $dupStmt->execute(["%Travel expense claim submitted by {$name}%"]);
    $dup = $dupStmt->fetch(PDO::FETCH_ASSOC);
    $report['5_dedup_check'] = $dup
        ? "⚠️ Dedup would SKIP — existing task ID {$dup['id']} (status: {$dup['status']})"
        : '✅ No dedup block — task would be created';
}

// ── 6. Are tasks visible in schedule? (is_system_task filter) ─────
if ($mapping) {
    $approverIds = array_filter([
        $mapping['manager_id'] ?? null,
        $mapping['hr_id'] ?? null,
        $mapping['senior_manager_id'] ?? null
    ]);
    if (!empty($approverIds)) {
        $ph = implode(',', array_fill(0, count($approverIds), '?'));
        $schedStmt = $pdo->prepare("
            SELECT id, task_description, assigned_to, due_date, due_time, status, is_system_task
            FROM studio_assigned_tasks
            WHERE project_name = 'ArchitectsHive Systems'
              AND is_system_task = 1
              AND FIND_IN_SET(?, assigned_to)
            ORDER BY created_at DESC LIMIT 5
        ");
        $schedStmt->execute([reset($approverIds)]);
        $report['6_approver_tasks_in_db'] = $schedStmt->fetchAll(PDO::FETCH_ASSOC) ?: '⚠️ No tasks found assigned to approvers';
    }
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
