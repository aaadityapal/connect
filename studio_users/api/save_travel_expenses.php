<?php
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/travel_task_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// ─── Bot log helper ───────────────────────────────────────────────
$botLogFile = __DIR__ . '/../../logs/conneqts_travel_bot.log';
$botLog = function($msg) use ($botLogFile, $user_id) {
    $ts = date('Y-m-d H:i:s');
    file_put_contents($botLogFile, "[$ts] [user_id={$user_id}] $msg" . PHP_EOL, FILE_APPEND);
};

try {
    if (!isset($_POST['expenses']) || !is_array($_POST['expenses'])) {
        echo json_encode(['success' => false, 'message' => 'No expense data provided']);
        exit();
    }

    $expenses = $_POST['expenses'];
    $pdo->beginTransaction();

    $uploadDir = __DIR__ . '/../../uploads/travel_documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $insertedCount = 0;

    foreach ($expenses as $index => $data) {
        $travel_date = $data['date'] ?? null;
        $purpose    = $data['purpose'] ?? '';
        $from       = $data['from'] ?? '';
        $to         = $data['to'] ?? '';
        $mode       = $data['mode'] ?? '';
        $distance   = $data['distance'] ?? 0;
        $amount     = $data['amount'] ?? 0;
        $notes      = $data['notes'] ?? '';

        if (!$travel_date || !$from || !$to || !$mode) continue;

        // Server-side Date Validation (Max 15 days in past)
        $tDate = new DateTime($travel_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $interval = $today->diff($tDate);
        $daysDiff = $interval->days;

        if ($daysDiff > 15 && $tDate < $today) {
            echo json_encode(['success' => false, 'message' => "Expense on $travel_date is older than 15 days and cannot be submitted."]);
            $pdo->rollBack();
            exit();
        }

        // Handle File Uploads
        $billPath = null;
        $meterStartPath = null;
        $meterEndPath = null;

        if (isset($_FILES['expenses']['tmp_name'][$index])) {
            $fileData = $_FILES['expenses'];
            if (!empty($fileData['tmp_name'][$index]['bill']))
                $billPath = uploadTravelFile($fileData, $index, 'bill', $user_id, $uploadDir);
            if (!empty($fileData['tmp_name'][$index]['meter_start']))
                $meterStartPath = uploadTravelFile($fileData, $index, 'meter_start', $user_id, $uploadDir);
            if (!empty($fileData['tmp_name'][$index]['meter_end']))
                $meterEndPath = uploadTravelFile($fileData, $index, 'meter_end', $user_id, $uploadDir);
        }

        $stmt = $pdo->prepare("INSERT INTO travel_expenses (
                    user_id, travel_date, purpose, from_location, to_location,
                    mode_of_transport, distance, amount, notes, status,
                    bill_file_path, meter_start_photo_path, meter_end_photo_path,
                    created_at
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $travel_date, $purpose, $from, $to,
            $mode, $distance, $amount, $notes,
            $billPath, $meterStartPath, $meterEndPath]);
        $newId = $pdo->lastInsertId();

        $logStmt = $pdo->prepare("INSERT INTO global_activity_logs
            (user_id, action_type, entity_type, entity_id, description, metadata, created_at)
            VALUES (?, 'travel_added', 'travel', ?, ?, ?, NOW())");
        $logStmt->execute([$user_id, $newId,
            "Added travel expense #$newId for $purpose. Trip: $from to $to on $travel_date via $mode ($distance km). Total: Rs.$amount.",
            json_encode(['id'=>$newId,'date'=>$travel_date,'purpose'=>$purpose,'from'=>$from,'to'=>$to,'mode'=>$mode,'distance'=>$distance,'amount'=>$amount])
        ]);

        $insertedCount++;
    }

    $pdo->commit();

    // ═══════════════════════════════════════════════════════════════
    //  CONNEQTS BOT — Spawn / Update approval task for approvers
    // ═══════════════════════════════════════════════════════════════
    $botStatus = ['success' => false, 'message' => 'Bot did not run'];
    try {
        date_default_timezone_set('Asia/Kolkata');
        $botLog("--- Submission received: {$insertedCount} expense(s) ---");

        // 1. Submitting user name
        $uStmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$user_id]);
        $employeeName = $uStmt->fetchColumn() ?: 'Employee';
        $botLog("Employee: {$employeeName}");

        // 2. Travel expense mapping
        $mapStmt = $pdo->prepare("SELECT manager_id, hr_id, senior_manager_id FROM travel_expense_mapping WHERE employee_id = ? LIMIT 1");
        $mapStmt->execute([$user_id]);
        $mapping = $mapStmt->fetch(PDO::FETCH_ASSOC);

        if (!$mapping) {
            $botLog("SKIPPED: No travel_expense_mapping entry for user_id={$user_id}. Task NOT created.");
            $botStatus = ['success' => false, 'message' => 'No travel_expense_mapping entry found for user.'];
        } else {
            $botLog("Mapping — manager_id=" . ($mapping['manager_id'] ?? 'null') .
                    ", hr_id=" . ($mapping['hr_id'] ?? 'null') .
                    ", sr_manager_id=" . ($mapping['senior_manager_id'] ?? 'null'));

            $approverIds = array_values(array_filter([
                $mapping['manager_id'],
                $mapping['hr_id']
                // ⚠️ Senior Manager NOT included here.
                // They get a separate task ONLY after both Manager + HR approve.
            ]));

            if (empty($approverIds)) {
                $botLog("SKIPPED: All approver IDs are null/empty in mapping row.");
                $botStatus = ['success' => false, 'message' => 'Approver IDs in mapping are null or empty.'];
            } else {
                // 3. Fetch approver names
                $ph           = implode(',', array_fill(0, count($approverIds), '?'));
                $nameStmt     = $pdo->prepare("SELECT id, username FROM users WHERE id IN ($ph)");
                $nameStmt->execute($approverIds);
                $approverRows     = $nameStmt->fetchAll(PDO::FETCH_ASSOC);
                $assignedToCSV    = implode(',', array_column($approverRows, 'id'));
                $assignedNamesCSV = implode(', ', array_column($approverRows, 'username'));
                $botLog("Approvers: {$assignedNamesCSV} (IDs: {$assignedToCSV})");

                // 4. Get CUMULATIVE totals for today (all pending expenses submitted today)
                $todayStmt = $pdo->prepare("
                    SELECT COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount
                    FROM travel_expenses
                    WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'pending'
                ");
                $todayStmt->execute([$user_id]);
                $todayTotals = $todayStmt->fetch(PDO::FETCH_ASSOC);
                $totalCount  = (int)$todayTotals['total_count'];
                $totalAmount = number_format((float)$todayTotals['total_amount'], 2, '.', '');
                $botLog("Cumulative today: {$totalCount} entries, Rs.{$totalAmount}");

                // 5. Find next open approval window based on approvers' day schedules
                $window   = getNextApprovalWindow($pdo, $approverIds);
                $dueDate  = $window['due_date'];
                $dueTime  = $window['due_time'];
                $botLog("Next window: due_date={$dueDate}, due_time={$dueTime}");

                // 6. Build task description with actual Rs. symbol
                $entryWord = $totalCount > 1 ? 'entries' : 'entry';
                $taskDesc  = "Travel expense claim submitted by {$employeeName} — {$totalCount} {$entryWord} totalling Rs.{$totalAmount}. Please review and take action.";
                $taskDesc .= "\n[Conneqts Bot | " . date('d M Y, h:i A') . "]";

                // 7. Check same-day existing task
                $dupStmt = $pdo->prepare("
                    SELECT id FROM studio_assigned_tasks
                    WHERE project_name = 'ArchitectsHive Systems'
                      AND task_description LIKE ?
                      AND due_date = ?
                      AND status NOT IN ('Completed', 'Cancelled')
                    LIMIT 1
                ");
                $dupStmt->execute(["%Travel expense claim submitted by {$employeeName}%", $dueDate]);
                $dupRow = $dupStmt->fetch();

                if ($dupRow) {
                    // UPDATE existing task — refresh totals in description
                    $updStmt = $pdo->prepare("UPDATE studio_assigned_tasks SET task_description = ? WHERE id = ?");
                    $updStmt->execute([$taskDesc, $dupRow['id']]);
                    $botLog("UPDATED: Task ID {$dupRow['id']} — now {$totalCount} entries, Rs.{$totalAmount}");

                    // Also insert a fresh activity log so approvers see the updated amount
                    $logMeta = json_encode([
                        'task_id'          => $dupRow['id'],
                        'assigned_by_name' => 'Conneqts Bot',
                        'project_name'     => 'ArchitectsHive Systems',
                        'assigned_to'      => $assignedToCSV,
                        'assigned_names'   => $assignedNamesCSV,
                        'due_date'         => $dueDate,
                        'due_time'         => $dueTime,

                        'submitted_by'     => $employeeName,
                        'expense_count'    => $totalCount,
                        'total_amount'     => $totalAmount
                    ]);
                    $actUpdStmt = $pdo->prepare("
                        INSERT INTO global_activity_logs
                            (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
                        VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)
                    ");
                    foreach (array_column($approverRows, 'id') as $aUid) {
                        $actUpdStmt->execute([
                            $aUid, $dupRow['id'],
                            "Conneqts Bot: {$employeeName} submitted a travel expense claim ({$totalCount} {$entryWord}, Rs.{$totalAmount}). Please review by {$dueDate} 6:00 PM.",
                            $logMeta
                        ]);
                        $botLog("Activity log updated for approver user_id={$aUid} — Rs.{$totalAmount}");
                    }
                    
                    $botStatus = ['success' => true, 'message' => 'Successfully updated existing task.', 'task_id' => $dupRow['id']];

                } else {
                    // INSERT new task
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
                    $botLog("CREATED: Task ID {$newTaskId} — {$totalCount} entries, Rs.{$totalAmount} — due {$dueDate} — assigned to: {$assignedNamesCSV}");

                    // Activity log per approver
                    $logMeta = json_encode([
                        'task_id'          => $newTaskId,
                        'assigned_by_name' => 'Conneqts Bot',
                        'project_name'     => 'ArchitectsHive Systems',
                        'assigned_to'      => $assignedToCSV,
                        'assigned_names'   => $assignedNamesCSV,
                        'due_date'         => $dueDate,
                        'due_time'         => '18:00:00',
                        'submitted_by'     => $employeeName,
                        'expense_count'    => $totalCount,
                        'total_amount'     => $totalAmount
                    ]);

                    $actStmt = $pdo->prepare("
                        INSERT INTO global_activity_logs
                            (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
                        VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)
                    ");
                    foreach (array_column($approverRows, 'id') as $aUid) {
                        $actStmt->execute([
                            $aUid, $newTaskId,
                            "Conneqts Bot: {$employeeName} submitted a travel expense claim ({$totalCount} {$entryWord}, Rs.{$totalAmount}). Please review by {$dueDate} 6:00 PM.",
                            $logMeta
                        ]);
                        $botLog("Activity log written for approver user_id={$aUid}");
                    }
                    
                    $botStatus = ['success' => true, 'message' => 'Successfully created new task.', 'task_id' => $newTaskId];
                }
            }
        }
    } catch (Exception $botEx) {
        $botLog("CRITICAL ERROR: " . $botEx->getMessage());
        error_log('[Conneqts Bot] Travel task spawn failed: ' . $botEx->getMessage());
        $botStatus = ['success' => false, 'error' => $botEx->getMessage()];
    }
    $botLog("--- Done ---");
    // ═══════════════════════════════════════════════════════════════

    echo json_encode([
        'success' => true, 
        'message' => "$insertedCount expense" . ($insertedCount > 1 ? 's' : '') . " saved successfully",
        'bot_status' => $botStatus
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function uploadTravelFile($fileData, $index, $field, $user_id, $uploadDir) {
    $tmpName = $fileData['tmp_name'][$index][$field];
    $origin  = $fileData['name'][$index][$field];
    if (!$tmpName) return null;
    $ext     = pathinfo($origin, PATHINFO_EXTENSION);
    $newName = 'travel_' . $user_id . '_' . time() . '_' . $index . '_' . $field . '.' . $ext;
    $dest    = $uploadDir . $newName;
    if (move_uploaded_file($tmpName, $dest)) return 'uploads/travel_documents/' . $newName;
    return null;
}
?>
