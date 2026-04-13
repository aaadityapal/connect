<?php
/**
 * debug_ot_bot.php
 * Diagnostic file for Conneqts Bot OT task creation.
 * Tests every condition that could cause the task to NOT be created.
 *
 * Usage: https://yoursite.com/studio_users/overtime_page/debug_ot_bot.php?user_id=XX&manager_id=YY&attendance_id=ZZ
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

// ─── Accept params from GET or fallback to session ───────────────────────
$test_user_id     = isset($_GET['user_id'])     ? (int)$_GET['user_id']     : (int)($_SESSION['user_id'] ?? 0);
$test_manager_id  = isset($_GET['manager_id'])  ? (int)$_GET['manager_id']  : 0;
$test_att_id      = isset($_GET['attendance_id']) ? (int)$_GET['attendance_id'] : 0;

$results = [];
$allPassed = true;

function pass(&$results, &$allPassed, $check, $detail = '') {
    $results[] = ['status' => '✅ PASS', 'check' => $check, 'detail' => $detail];
}
function fail(&$results, &$allPassed, $check, $detail = '') {
    $allPassed = false;
    $results[] = ['status' => '❌ FAIL', 'check' => $check, 'detail' => $detail];
}
function warn(&$results, $check, $detail = '') {
    $results[] = ['status' => '⚠️  WARN', 'check' => $check, 'detail' => $detail];
}

// ═══════════════════════════════════════════════════════════
// CHECK 0: Input parameters
// ═══════════════════════════════════════════════════════════
if (!$test_user_id) {
    fail($results, $allPassed, 'Input: user_id provided', 'Pass ?user_id=XX in the URL. Also optionally ?manager_id=YY&attendance_id=ZZ');
} else {
    pass($results, $allPassed, 'Input: user_id provided', "user_id = $test_user_id");
}

if (!$test_manager_id) {
    warn($results, 'Input: manager_id provided', 'Not provided — fallback mapping check will be used. Add ?manager_id=YY to test form fallback.');
}
if (!$test_att_id) {
    warn($results, 'Input: attendance_id provided', 'Not provided — calculated OT hours will be 0.0h in test task description.');
}

// ═══════════════════════════════════════════════════════════
// CHECK 1: DB Connection
// ═══════════════════════════════════════════════════════════
try {
    $pdo->query("SELECT 1");
    pass($results, $allPassed, 'DB: Connection active');
} catch (Exception $e) {
    fail($results, $allPassed, 'DB: Connection active', $e->getMessage());
}

// ═══════════════════════════════════════════════════════════
// CHECK 2: Employee exists in users table
// ═══════════════════════════════════════════════════════════
$empName = null;
if ($test_user_id) {
    $empStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $empStmt->execute([$test_user_id]);
    $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
    if ($empRow) {
        $empName = $empRow['username'];
        pass($results, $allPassed, 'Employee: Found in users table', "id={$empRow['id']}, username={$empRow['username']}");
    } else {
        fail($results, $allPassed, 'Employee: Found in users table', "No user with id=$test_user_id found in users table.");
    }
}

// ═══════════════════════════════════════════════════════════
// CHECK 3: Manager mapping in overtime_approval_mapping
// ═══════════════════════════════════════════════════════════
$mgrRow = null;
if ($test_user_id) {
    $mgrStmt = $pdo->prepare("
        SELECT u.id, u.username 
        FROM overtime_approval_mapping oam 
        JOIN users u ON oam.manager_id = u.id 
        WHERE oam.employee_id = ? 
        LIMIT 1
    ");
    $mgrStmt->execute([$test_user_id]);
    $mgrRow = $mgrStmt->fetch(PDO::FETCH_ASSOC);

    if ($mgrRow) {
        pass($results, $allPassed, 'Manager: Found in overtime_approval_mapping', "Mapped to: {$mgrRow['username']} (id={$mgrRow['id']})");
    } else {
        warn($results, 'Manager: Found in overtime_approval_mapping', "No mapping found for employee_id=$test_user_id — will fallback to form manager_id.");
        
        // CHECK 3b: Fallback manager
        if ($test_manager_id) {
            $fbStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
            $fbStmt->execute([$test_manager_id]);
            $fbRow = $fbStmt->fetch(PDO::FETCH_ASSOC);
            if ($fbRow) {
                $mgrRow = $fbRow;
                pass($results, $allPassed, 'Manager: Fallback manager_id found in users', "Fallback to: {$fbRow['username']} (id={$fbRow['id']})");
            } else {
                fail($results, $allPassed, 'Manager: Fallback manager_id found in users', "manager_id=$test_manager_id not in users table either. ⬅️ BOT STOPS HERE — no task created.");
            }
        } else {
            fail($results, $allPassed, 'Manager: Fallback manager_id', "No mapping AND no manager_id provided. ⬅️ BOT STOPS HERE — no task created.");
        }
    }
}

// ═══════════════════════════════════════════════════════════
// CHECK 4: All rows in overtime_approval_mapping for this employee
// ═══════════════════════════════════════════════════════════
if ($test_user_id) {
    $allMapStmt = $pdo->prepare("SELECT oam.*, u.username as manager_name FROM overtime_approval_mapping oam LEFT JOIN users u ON oam.manager_id = u.id WHERE oam.employee_id = ?");
    $allMapStmt->execute([$test_user_id]);
    $allMappings = $allMapStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($allMappings)) {
        warn($results, 'Manager Mapping: All rows for this employee', 'No rows in overtime_approval_mapping for this employee_id at all.');
    } else {
        pass($results, $allPassed, 'Manager Mapping: All rows for this employee', json_encode($allMappings));
    }
}

// ═══════════════════════════════════════════════════════════
// CHECK 5: Projects table — ArchitectsHive Systems
// ═══════════════════════════════════════════════════════════
$botProjectId = null;
$projStmt = $pdo->prepare("SELECT id, title FROM projects WHERE LOWER(title) LIKE '%architectshive systems%' LIMIT 1");
$projStmt->execute();
$projRow = $projStmt->fetch(PDO::FETCH_ASSOC);
if ($projRow) {
    $botProjectId = $projRow['id'];
    pass($results, $allPassed, 'Project: ArchitectsHive Systems found', "id={$projRow['id']}, title={$projRow['title']}");
} else {
    warn($results, 'Project: ArchitectsHive Systems found', "Project not found — botProjectId will be NULL. If project_id FK is strict (NOT NULL), INSERT will fail!");
    
    // Check if project_id is nullable
    $colStmt = $pdo->prepare("SHOW COLUMNS FROM studio_assigned_tasks LIKE 'project_id'");
    $colStmt->execute();
    $colDef = $colStmt->fetch(PDO::FETCH_ASSOC);
    if ($colDef) {
        if ($colDef['Null'] === 'YES') {
            warn($results, 'Project: project_id column nullable?', "YES — NULL is allowed, INSERT will proceed. But task won't link to a project.");
        } else {
            fail($results, $allPassed, 'Project: project_id column nullable?', "NO — project_id is NOT NULL. INSERT WILL FAIL because project is missing. ⬅️ Create the project first!");
        }
    }
}

// ═══════════════════════════════════════════════════════════
// CHECK 6: studio_assigned_tasks — required columns exist
// ═══════════════════════════════════════════════════════════
$requiredCols = ['project_id', 'project_name', 'stage_number', 'task_description', 
                 'priority', 'assigned_to', 'assigned_names', 'due_date', 'due_time', 
                 'status', 'created_by', 'is_system_task', 'created_at'];

$colsStmt = $pdo->query("SHOW COLUMNS FROM studio_assigned_tasks");
$existingCols = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

$missingCols = array_diff($requiredCols, $existingCols);
if (empty($missingCols)) {
    pass($results, $allPassed, 'Table: studio_assigned_tasks has all required columns', implode(', ', $requiredCols));
} else {
    fail($results, $allPassed, 'Table: studio_assigned_tasks MISSING columns', "Missing: " . implode(', ', $missingCols) . " ⬅️ ALTER TABLE needed!");
}

// ═══════════════════════════════════════════════════════════
// CHECK 7: global_activity_logs — required columns exist
// ═══════════════════════════════════════════════════════════
$logCols = ['user_id', 'action_type', 'entity_type', 'entity_id', 'description', 'metadata', 'created_at', 'is_read'];
$logColStmt = $pdo->query("SHOW COLUMNS FROM global_activity_logs");
$existingLogCols = array_column($logColStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
$missingLogCols = array_diff($logCols, $existingLogCols);
if (empty($missingLogCols)) {
    pass($results, $allPassed, 'Table: global_activity_logs has all required columns');
} else {
    fail($results, $allPassed, 'Table: global_activity_logs MISSING columns', "Missing: " . implode(', ', $missingLogCols));
}

// ═══════════════════════════════════════════════════════════
// CHECK 8: Dry-run INSERT — OUTSIDE transaction (isolation test)
// ═══════════════════════════════════════════════════════════
if ($mgrRow && $test_user_id) {
    try {
        $pdo->beginTransaction();

        $testDesc = "[DEBUG TEST] Review and approve the overtime submission from " . ($empName ?? 'TestUser') . " for " . date('Y-m-d') . ". Calculated OT: 2.0h. Report: \"This is a diagnostic test task.\"";

        $tStmt = $pdo->prepare("
            INSERT INTO studio_assigned_tasks
                (project_id, project_name, stage_number, task_description, priority,
                 assigned_to, assigned_names, due_date, due_time, status, created_by, is_system_task, created_at)
            VALUES
                (?, 'ArchitectsHive Systems', 'Verification', ?, 'High',
                 ?, ?, CURDATE(), '17:45:00', 'Pending', ?, 1, NOW())
        ");
        $tStmt->execute([
            $botProjectId,
            $testDesc,
            (string)$mgrRow['id'],
            $mgrRow['username'],
            $test_user_id
        ]);
        $newTestTaskId = $pdo->lastInsertId();
        $pdo->rollBack();

        pass($results, $allPassed, 'Dry-run (outside tx): INSERT into studio_assigned_tasks', "Would have created task ID=$newTestTaskId — rolled back. INSERT WORKS outside a transaction.");

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fail($results, $allPassed, 'Dry-run (outside tx): INSERT FAILED', $e->getMessage() . " | Line: " . $e->getLine());
    }
} else {
    warn($results, 'Dry-run: INSERT skipped', 'Fix manager/user failures above first.');
}

// ═══════════════════════════════════════════════════════════
// CHECK 8b: Simulate EXACT real context — inside a transaction
// This matches how api_submit_overtime.php runs the bot block
// ═══════════════════════════════════════════════════════════
if ($mgrRow && $test_user_id) {
    try {
        // Simulate outer transaction (like api_submit_overtime.php does)
        $pdo->beginTransaction();

        // Simulate an UPDATE inside the transaction (like the attendance update)
        $simulateUpdate = $pdo->prepare("UPDATE users SET updated_at = updated_at WHERE id = ?");
        $simulateUpdate->execute([$test_user_id]);

        // Now run the EXACT bot block code inside this transaction
        $innerError = null;
        try {
            $empStmt2 = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $empStmt2->execute([$test_user_id]);
            $empRow2 = $empStmt2->fetch(PDO::FETCH_ASSOC);
            $empName2 = $empRow2 ? $empRow2['username'] : 'Employee';

            $mgrStmt2 = $pdo->prepare("SELECT u.id, u.username FROM overtime_approval_mapping oam JOIN users u ON oam.manager_id = u.id WHERE oam.employee_id = ? LIMIT 1");
            $mgrStmt2->execute([$test_user_id]);
            $mgrRow2 = $mgrStmt2->fetch(PDO::FETCH_ASSOC);

            if (!$mgrRow2 && $test_manager_id) {
                $fbStmt2 = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
                $fbStmt2->execute([$test_manager_id]);
                $mgrRow2 = $fbStmt2->fetch(PDO::FETCH_ASSOC);
            }

            if ($mgrRow2) {
                $projStmt2 = $pdo->prepare("SELECT id FROM projects WHERE LOWER(title) LIKE '%architectshive systems%' LIMIT 1");
                $projStmt2->execute();
                $projRow2 = $projStmt2->fetch(PDO::FETCH_ASSOC);
                $pid2 = $projRow2 ? $projRow2['id'] : null;

                $desc2 = "[DEBUG INSIDE TX] Review and approve OT from {$empName2} for " . date('Y-m-d') . ". Calculated OT: 2.0h.";

                $tStmt2 = $pdo->prepare("
                    INSERT INTO studio_assigned_tasks
                        (project_id, project_name, stage_number, task_description, priority,
                         assigned_to, assigned_names, due_date, due_time, status, created_by, is_system_task, created_at)
                    VALUES (?, 'ArchitectsHive Systems', 'Verification', ?, 'High',
                            ?, ?, CURDATE(), '17:45:00', 'Pending', ?, 1, NOW())
                ");
                $tStmt2->execute([$pid2, $desc2, (string)$mgrRow2['id'], $mgrRow2['username'], $test_user_id]);
                $tid2 = $pdo->lastInsertId();

                pass($results, $allPassed, 
                    'Dry-run (INSIDE tx): INSERT into studio_assigned_tasks',
                    "Works INSIDE a transaction too. task_id=$tid2 (rolled back). Manager: {$mgrRow2['username']}, project_id=" . ($pid2 ?? 'NULL'));
            } else {
                fail($results, $allPassed, 'Dry-run (INSIDE tx): No manager resolved', 'Bot block would silently skip — no task created.');
            }
        } catch (Throwable $inner) {
            $innerError = $inner->getMessage() . ' | Line: ' . $inner->getLine() . ' | File: ' . basename($inner->getFile());
            fail($results, $allPassed, 'Dry-run (INSIDE tx): Exception thrown in bot block', $innerError . ' ← THIS IS THE REAL ERROR BEING SWALLOWED IN PRODUCTION');
        }

        $pdo->rollBack(); // Always rollback — never save test data

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fail($results, $allPassed, 'Dry-run (INSIDE tx): Outer transaction failed', $e->getMessage());
    }
} else {
    warn($results, 'Dry-run (INSIDE tx): Skipped', 'Fix manager/user failures above first.');
}

// ═══════════════════════════════════════════════════════════
// CHECK 9: Recent error_log entries for ConneqtsBot OT
// ═══════════════════════════════════════════════════════════
$logPaths = [
    '/home/newblogs/public_html/logs/cron_overtime_bot.log',
    __DIR__ . '/../../logs/cron_overtime_bot.log',
    '/var/log/apache2/error.log',
];

$errorLogFound = false;
foreach ($logPaths as $logPath) {
    if (file_exists($logPath) && is_readable($logPath)) {
        $lines = file($logPath);
        $relevant = array_filter($lines, fn($l) => str_contains($l, 'ConneqtsBot') || str_contains($l, 'OT ERROR'));
        $last10 = array_slice(array_values($relevant), -10);
        if (!empty($last10)) {
            warn($results, "Recent ConneqtsBot errors found in: $logPath", implode(' | ', array_map('trim', $last10)));
        } else {
            pass($results, $allPassed, "Log file readable: $logPath", 'No ConneqtsBot OT errors found in this log.');
        }
        $errorLogFound = true;
        break;
    }
}
if (!$errorLogFound) {
    warn($results, 'Log file check', 'No readable log file found in checked paths. Check PHP error_log manually.');
}

// ═══════════════════════════════════════════════════════════
// CHECK 10: Last 3 tasks created by bot for this user (if any succeeded before)
// ═══════════════════════════════════════════════════════════
if ($test_user_id) {
    $prevStmt = $pdo->prepare("SELECT id, task_description, assigned_names, due_date, status, created_at FROM studio_assigned_tasks WHERE created_by = ? AND is_system_task = 1 AND project_name = 'ArchitectsHive Systems' ORDER BY id DESC LIMIT 3");
    $prevStmt->execute([$test_user_id]);
    $prevTasks = $prevStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($prevTasks)) {
        pass($results, $allPassed, 'Previous bot tasks: Found for this employee', json_encode($prevTasks));
    } else {
        warn($results, 'Previous bot tasks: None found for this employee', 'No previous is_system_task=1 tasks with this created_by. Either never succeeded or wrong user_id.');
    }
}

// ═══════════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════════
echo json_encode([
    'summary'    => $allPassed ? '✅ ALL CHECKS PASSED — bot should work' : '❌ SOME CHECKS FAILED — see details below',
    'user_id_tested'    => $test_user_id,
    'manager_id_tested' => $test_manager_id,
    'attendance_id_tested' => $test_att_id,
    'checks'     => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
