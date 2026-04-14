<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID not provided']);
    exit();
}

$user_id = $_SESSION['user_id'];
$id = $_POST['id'];

// Remove "EXP-" prefix if present
$numeric_id = str_replace('EXP-', '', $id);

try {
    // 1. Fetch current status to check if it's already approved
    $checkQuery = "SELECT id, status, manager_status, accountant_status, hr_status, purpose, amount, travel_date, from_location, to_location
                    FROM travel_expenses 
                    WHERE id = ? AND user_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$numeric_id, $user_id]);
    $expense = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        echo json_encode(['success' => false, 'message' => 'Expense not found or unauthorized']);
        exit();
    }

    // 2. STRICTURE: If ANY status is "approved", it CANNOT be deleted
    if ($expense['status'] === 'approved' || 
        $expense['manager_status'] === 'approved' || 
        $expense['accountant_status'] === 'approved' || 
        $expense['hr_status'] === 'approved'
    ) {
        echo json_encode(['success' => false, 'message' => 'This expense has already been approved and cannot be deleted.']);
        exit();
    }

    // 3. Perform Deletion
    $deleteQuery = "DELETE FROM travel_expenses WHERE id = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$numeric_id]);

    // 4. Log Activity
    $logDescription = "Deleted travel expense EXP-" . str_pad($numeric_id, 4, '0', STR_PAD_LEFT) . " for " . $expense['purpose'] . ". Trip: " . $expense['from_location'] . " to " . $expense['to_location'] . " on " . $expense['travel_date'] . ". Total: ₹" . $expense['amount'];
    $metadata = json_encode([
        'id' => $numeric_id,
        'action' => 'delete',
        'details' => [
            'date' => $expense['travel_date'],
            'purpose' => $expense['purpose'],
            'from' => $expense['from_location'],
            'to' => $expense['to_location'],
            'amount' => $expense['amount']
        ]
    ]);

    $logQuery = "INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at) 
                 VALUES (?, 'travel_deleted', 'travel', ?, ?, ?, NOW())";
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([$user_id, $numeric_id, $logDescription, $metadata]);

    // ═══════════════════════════════════════════════════════════════
    //  CONNEQTS BOT: Sync task after expense deletion
    // ═══════════════════════════════════════════════════════════════
    try {
        date_default_timezone_set('Asia/Kolkata');

        // Get submitting user's name
        $uStmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$user_id]);
        $employeeName = $uStmt->fetchColumn() ?: 'Employee';

        // Recalculate remaining TODAY's pending expenses after deletion
        $remStmt = $pdo->prepare("
            SELECT COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount
            FROM travel_expenses
            WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'pending'
        ");
        $remStmt->execute([$user_id]);
        $remaining    = $remStmt->fetch(PDO::FETCH_ASSOC);
        $totalCount   = (int)$remaining['total_count'];
        $totalAmount  = number_format((float)$remaining['total_amount'], 2, '.', '');

        // Find today's pending task for this employee
        $taskStmt = $pdo->prepare("
            SELECT id, assigned_to, assigned_names FROM studio_assigned_tasks
            WHERE project_name = 'ArchitectsHive Systems'
              AND task_description LIKE ?
              AND DATE(due_date) >= CURDATE()
              AND status NOT IN ('Completed', 'Cancelled')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $taskStmt->execute(["%Travel expense claim submitted by {$employeeName}%"]);
        $task = $taskStmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            if ($totalCount === 0) {
                // No remaining entries → DELETE the task entirely
                $delTask = $pdo->prepare("DELETE FROM studio_assigned_tasks WHERE id = ?");
                $delTask->execute([$task['id']]);

                // Also log the removal for each approver
                $approverIds = array_filter(explode(',', $task['assigned_to']));
                $actStmt = $pdo->prepare("
                    INSERT INTO global_activity_logs
                        (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
                    VALUES (?, 'task_cancelled', 'task', ?, ?, ?, NOW(), 0)
                ");
                $meta = json_encode(['task_id' => $task['id'], 'reason' => 'All travel expenses deleted', 'employee' => $employeeName]);
                foreach ($approverIds as $aUid) {
                    $actStmt->execute([
                        trim($aUid), $task['id'],
                        "Conneqts Bot: {$employeeName} deleted all travel expense entries. Review task has been removed.",
                        $meta
                    ]);
                }
            } else {
                // Remaining entries exist → UPDATE task with new reduced totals
                $entryWord  = $totalCount > 1 ? 'entries' : 'entry';
                $dueDate    = $task['due_date'] ?? date('Y-m-d');
                $newDesc    = "Travel expense claim submitted by {$employeeName} — {$totalCount} {$entryWord} totalling Rs.{$totalAmount}. Please review and take action.";
                $newDesc   .= "\n[Conneqts Bot | Updated after deletion on " . date('d M Y, h:i A') . "]";

                $updStmt = $pdo->prepare("UPDATE studio_assigned_tasks SET task_description = ? WHERE id = ?");
                $updStmt->execute([$newDesc, $task['id']]);

                // Log updated amount for each approver
                $approverIds = array_filter(explode(',', $task['assigned_to']));
                $logMeta = json_encode([
                    'task_id'       => $task['id'],
                    'assigned_by'   => 'Conneqts Bot',
                    'expense_count' => $totalCount,
                    'total_amount'  => $totalAmount,
                    'submitted_by'  => $employeeName
                ]);
                $actStmt = $pdo->prepare("
                    INSERT INTO global_activity_logs
                        (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
                    VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)
                ");
                foreach ($approverIds as $aUid) {
                    $actStmt->execute([
                        trim($aUid), $task['id'],
                        "Conneqts Bot: {$employeeName} deleted an expense entry. Updated claim: {$totalCount} {$entryWord}, Rs.{$totalAmount}.",
                        $logMeta
                    ]);
                }
            }
        }
    } catch (Exception $botEx) {
        error_log('[Conneqts Bot] Task sync on delete failed: ' . $botEx->getMessage());
    }
    // ═══════════════════════════════════════════════════════════════

    echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
