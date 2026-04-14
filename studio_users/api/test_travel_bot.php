<?php
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/travel_task_helper.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'logs' => [],
    'errors' => [],
    'data' => []
];

function addLog($msg) {
    global $response;
    $response['logs'][] = "[" . date('H:i:s') . "] " . $msg;
}

function addError($msg) {
    global $response;
    $response['errors'][] = $msg;
}

try {
    $user_id = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);

    if (!$user_id) {
        addError("No user_id provided. Please pass ?user_id=XX in URL or login.");
    } else {
        $response['data']['user_id'] = $user_id;
        addLog("Testing for user_id = $user_id");

        // 1. Check User Name
        $uStmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$user_id]);
        $employeeName = $uStmt->fetchColumn();
        if (!$employeeName) {
            addError("User ID $user_id does not exist in users table.");
        } else {
            addLog("Employee Name: $employeeName");
            $response['data']['employee_name'] = $employeeName;
        }

        // 2. Check Mapping
        $mapStmt = $pdo->prepare("SELECT manager_id, hr_id, senior_manager_id FROM travel_expense_mapping WHERE employee_id = ? LIMIT 1");
        $mapStmt->execute([$user_id]);
        $mapping = $mapStmt->fetch(PDO::FETCH_ASSOC);

        if (!$mapping) {
            addError("SKIPPED: No travel_expense_mapping entry for user_id={$user_id}. Tasks will NOT be created.");
        } else {
            addLog("Found mapping: manager_id=" . ($mapping['manager_id'] ?? 'null') . 
                   ", hr_id=" . ($mapping['hr_id'] ?? 'null') . 
                   ", senior_manager_id=" . ($mapping['senior_manager_id'] ?? 'null'));
            $response['data']['mapping'] = $mapping;

            $approverIds = array_values(array_filter([
                $mapping['manager_id'],
                $mapping['hr_id']
            ]));

            if (empty($approverIds)) {
                addError("SKIPPED: Both manager_id and hr_id are null or empty.");
            } else {
                addLog("Approver IDs to check: " . implode(',', $approverIds));

                // 3. Fetch Approver Names
                $ph = implode(',', array_fill(0, count($approverIds), '?'));
                $nameStmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN ($ph)");
                $nameStmt->execute($approverIds);
                $approverRows = $nameStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($approverRows)) {
                    addError("Approvers not found in users table. Invalid IDs?");
                } else {
                    $assignedToCSV    = implode(',', array_column($approverRows, 'id'));
                    $assignedNamesCSV = implode(', ', array_column($approverRows, 'username'));
                    addLog("Approver Names: " . $assignedNamesCSV);
                }

                // 4. Test getNextApprovalWindow
                $dueDate = date('Y-m-d');
                $dueTime = '18:00:00';
                try {
                    $window = getNextApprovalWindow($pdo, $approverIds);
                    $dueDate = $window['due_date'];
                    $dueTime = $window['due_time'];
                    addLog("Next Approval Window: Date = {$dueDate}, Time = {$dueTime}");
                } catch (Exception $e) {
                    addError("Error in getNextApprovalWindow: " . $e->getMessage());
                }

                // 5. Test Cumulative Fetch Query
                $totalCount = 1;
                $totalAmount = "150.00";
                try {
                    $todayStmt = $pdo->prepare("
                        SELECT COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount
                        FROM travel_expenses
                        WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'pending'
                    ");
                    $todayStmt->execute([$user_id]);
                    $todayTotals = $todayStmt->fetch(PDO::FETCH_ASSOC);
                    $totalCount  = (int)$todayTotals['total_count'];
                    $totalAmount = number_format((float)$todayTotals['total_amount'], 2, '.', '');
                    addLog("Current Pending Expenses Today (DB Check): Count = {$totalCount}, Amount = Rs.{$totalAmount}");
                    
                    if ($totalCount === 0) {
                        $totalCount = 1; 
                        addLog("(Overriding to 1 for dummy task insertion test)");
                    }
                } catch (Exception $e) {
                    addError("Error fetching cumulative totals: " . $e->getMessage());
                }

                // 6. Test DB Inserts (inside a transaction so we roll it back)
                addLog("Starting DB Insert Mock Test (Transaction will be rolled back)...");
                $pdo->beginTransaction();
                try {
                    $entryWord = $totalCount > 1 ? 'entries' : 'entry';
                    $taskDesc  = "Travel expense claim submitted by {$employeeName} — {$totalCount} {$entryWord} totalling Rs.{$totalAmount}. Please review and take action.\n[Conneqts Bot TEST]";

                    $tStmt = $pdo->prepare("
                        INSERT INTO studio_assigned_tasks
                            (project_name, stage_number, task_description, priority,
                             assigned_to, assigned_names, due_date, due_time,
                             status, created_by, is_system_task, created_at)
                        VALUES ('ArchitectsHive Systems', 'Verification', ?, 'High',
                                ?, ?, ?, ?, 'Pending', ?, 1, NOW())
                    ");
                    $tStmt->execute([$taskDesc, $assignedToCSV, $assignedNamesCSV, $dueDate, $dueTime, $user_id]);
                    $newTaskId = $pdo->lastInsertId();
                    addLog("SUCCESS: Inserted dummy task ID: $newTaskId");

                    $actStmt = $pdo->prepare("
                        INSERT INTO global_activity_logs
                            (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
                        VALUES (?, 'task_assigned', 'task', ?, ?, '{}', NOW(), 0)
                    ");
                    foreach (array_column($approverRows, 'id') as $aUid) {
                        $actStmt->execute([$aUid, $newTaskId, "Test Activity Log"]);
                        addLog("SUCCESS: Activity log written for approver user_id={$aUid}");
                    }
                    
                    $pdo->rollBack();
                    addLog("DB transaction rolled back successfully.");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    addError("SQL ERROR during task simulation constraint check: " . $e->getMessage());
                }
            }
        }
        $response['success'] = empty($response['errors']);
    }

} catch (Exception $ex) {
    addError("CRITICAL EXCEPTION: " . $ex->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
