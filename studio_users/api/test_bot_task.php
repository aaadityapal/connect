<?php
// =====================================================
// api/test_bot_task.php
// Diagnostic: Simulates exactly what save_leave_request.php
// does when creating a Conneqts Bot task, and surfaces any errors
// =====================================================
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$result = [];

try {
    // 1. Check if 'ArchitectsHive Back Office' project exists
    $projStmt = $pdo->prepare("SELECT id, title FROM projects WHERE LOWER(title) LIKE '%architectshive back office%' LIMIT 1");
    $projStmt->execute();
    $projRow = $projStmt->fetch(PDO::FETCH_ASSOC);
    $botProjectId = $projRow ? $projRow['id'] : null;
    $result['project_found'] = $projRow ?: 'NOT FOUND - will use NULL';

    // 2. Find a manager (use first Senior Manager for test)
    $mgrStmt = $pdo->query("SELECT id, username FROM users WHERE LOWER(role) LIKE '%senior manager%' LIMIT 1");
    $mgrRow = $mgrStmt->fetch(PDO::FETCH_ASSOC);
    $result['manager_found'] = $mgrRow ?: 'NOT FOUND';

    // 3. Find HR users
    $hrStmt = $pdo->query("SELECT id, username FROM users WHERE LOWER(role) = 'hr'");
    $hrRows = $hrStmt->fetchAll(PDO::FETCH_ASSOC);
    $result['hr_users_found'] = $hrRows ?: 'NONE FOUND';

    // 4. Find a test employee (user id 21 or first employee)
    $empStmt = $pdo->query("SELECT id, username FROM users WHERE id = 21 LIMIT 1");
    $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$empRow) {
        $empStmt = $pdo->query("SELECT id, username FROM users WHERE LOWER(role) = 'employee' LIMIT 1");
        $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
    }
    $result['employee_found'] = $empRow ?: 'NOT FOUND';

    // 5. Build assignedIds
    $assignedIds = [];
    $assignedNames = [];
    if ($mgrRow) {
        $assignedIds[] = $mgrRow['id'];
        $assignedNames[] = $mgrRow['username'];
    }
    foreach ($hrRows as $h) {
        if (!in_array($h['id'], $assignedIds)) {
            $assignedIds[] = $h['id'];
            $assignedNames[] = $h['username'];
        }
    }
    $result['assignedIds'] = $assignedIds;

    if (count($assignedIds) === 0) {
        $result['error'] = 'No managers or HR found to assign to!';
        echo json_encode($result);
        exit;
    }

    $assignedToCSV   = implode(',', $assignedIds);
    $assignedNamesCSV = implode(', ', $assignedNames);
    $employeeName     = $empRow ? $empRow['username'] : 'TestUser';
    $userId           = $empRow ? $empRow['id'] : 1;
    $taskDesc         = "[TEST] Please verify the Casual Leave request from $employeeName for 2026-04-09.";

    // 6. Attempt the actual INSERT
    $tStmt = $pdo->prepare("INSERT INTO studio_assigned_tasks 
        (project_id, project_name, stage_number, task_description, priority, assigned_to, assigned_names, due_date, due_time, status, created_by, created_at)
        VALUES 
        (?, 'ArchitectsHive Back Office', 'Verification', ?, 'High', ?, ?, CURDATE(), '18:00:00', 'Pending', ?, NOW())");
    
    $tStmt->execute([$botProjectId, $taskDesc, $assignedToCSV, $assignedNamesCSV, $userId]);
    $newTaskId = $pdo->lastInsertId();

    $result['status']      = 'SUCCESS';
    $result['new_task_id'] = $newTaskId;
    $result['message']     = "Test task inserted successfully! Task ID: $newTaskId";

    // Clean up test task
    $pdo->exec("DELETE FROM studio_assigned_tasks WHERE id = $newTaskId");
    $result['cleanup'] = 'Test task deleted after verification';

} catch (Exception $e) {
    $result['status'] = 'ERROR';
    $result['error']  = $e->getMessage();
    $result['code']   = $e->getCode();
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
